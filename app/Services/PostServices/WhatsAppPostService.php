<?php

namespace App\Services\PostServices;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostWhatsappRecipient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * WhatsApp has no public feed to "post" to - unlike every other platform
 * in this module, there's no officially supported API for WhatsApp
 * Channels (Meta's own docs: "there is no API for the standard Channel
 * feature itself"). This reinterprets a WhatsApp "post" as a broadcast: an
 * approved WhatsApp Message Template sent individually to a list of
 * recipient numbers via the Cloud API (graph.facebook.com), which is the
 * only officially sanctioned way to reach WhatsApp users outside an active
 * 24-hour customer-service conversation window.
 *
 * The template itself must already exist and be approved in Meta Business
 * Manager - this doesn't create/submit templates for approval (that's an
 * asynchronous review process Meta controls, not something a "publish now"
 * flow can wait on) - the composer just needs the template's exact name
 * and language code. The post's own `content` fills the template's body
 * text variable and, if a media file is attached, its S3 URL fills the
 * header image variable.
 *
 * Follows the exact store()/publishPost()/destroy() contract every other
 * *PostService class in this module implements, so PostController and the
 * PublishPosts scheduled command can dispatch to it identically.
 */
class WhatsAppPostService
{
    protected string $baseUrl;

    public function __construct(protected ApiPostService $api, protected Post $post, protected PostMedia $media)
    {
        $this->baseUrl = adminSetting('posts.whatsapp.base_url') ?: 'https://graph.facebook.com/v21.0/';
    }

    /**
     * Creates the Post (+ PostMedia + one PostWhatsappRecipient row per
     * number) records only - matches every sibling service's store(): the
     * actual send happens later via publishPost(), called by the
     * PublishPosts scheduled command whether or not the post was
     * explicitly scheduled (see that command for why "immediate" posts
     * still go through it).
     */
    public function store($data, $pages)
    {
        if (empty($data['content'])) {
            return ['success' => false, 'message' => 'Post content is required'];
        }

        if (empty($data['whatsapp_template_name'])) {
            return ['success' => false, 'message' => 'A WhatsApp template name is required - it must already be approved in Meta Business Manager.'];
        }

        $recipients = $this->parseRecipients($data['whatsapp_recipients'] ?? '');

        if (empty($recipients)) {
            return ['success' => false, 'message' => 'At least one recipient phone number is required.'];
        }

        $mediaUrl = null;
        $uploadedMedia = null;

        if (!empty($data['media'][0])) {
            $uploadResult = $this->uploadMediaToS3($data['media'][0]);

            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['message']];
            }

            $uploadedMedia = $uploadResult['media'];
            $mediaUrl = $uploadedMedia['url'];
        }

        $results = [];
        $errors = [];
        $successCount = 0;

        foreach ($pages as $page) {
            try {
                $post = $this->post::create([
                    'title'            => $data['title'] ?? Auth::user()->name,
                    'post_id'          => null,
                    'platform'         => 'whatsapp',
                    'visibility'       => 'public',
                    'user_id'          => Auth::id(),
                    'post_account_id'  => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'content'          => $data['content'] ?? null,
                    'media_url'        => $mediaUrl,
                    'schedule_mode'    => $data['schedule_mode'] ?? 0,
                    'schedule_at'      => $data['schedule_at'] ?? null,
                    'expiry_mode'      => $data['expiry_mode'] ?? 0,
                    'expiry_at'        => $data['expiry_at'] ?? null,
                    'status'           => 'pending',
                    'platform_metadata' => [
                        'template_name'     => $data['whatsapp_template_name'],
                        'template_language' => $data['whatsapp_template_language'] ?? 'en_US',
                    ],
                ]);

                if ($uploadedMedia) {
                    $this->media::create([
                        'platform'         => 'whatsapp',
                        'post_id'          => $post->id,
                        'visibility'       => 'public',
                        'user_id'          => Auth::id(),
                        'post_account_id'  => $page->id,
                        'post_category_id' => $data['category_id'] ?? 1,
                        'media_url'        => $uploadedMedia['url'],
                        'media_type'       => $uploadedMedia['media_type'],
                        'file_name'        => $uploadedMedia['file_name'],
                        'file_size'        => $uploadedMedia['file_size'],
                        'width'            => $uploadedMedia['width'],
                        'height'           => $uploadedMedia['height'],
                        'alt_text'         => $uploadedMedia['alt_text'],
                    ]);
                }

                foreach ($recipients as $phoneNumber) {
                    PostWhatsappRecipient::create([
                        'post_id'      => $post->id,
                        'phone_number' => $phoneNumber,
                        'status'       => 'pending',
                    ]);
                }

                $successCount++;
                $results[] = $post;
            } catch (\Exception $e) {
                $errors[] = [
                    'page_id'   => $page->account_id,
                    'page_name' => $page->name,
                    'message'   => $e->getMessage(),
                ];
            }
        }

        return [
            'success'       => $successCount > 0,
            'total_pages'   => count($pages),
            'success_count' => $successCount,
            'error_count'   => count($errors),
            'data'          => $results,
            'errors'        => $errors,
            'message'       => $successCount > 0
                ? "Broadcast queued to {$this->countRecipients($results)} recipient(s) and will be sent in background."
                : 'Failed to create broadcast.',
        ];
    }

    private function countRecipients(array $posts): int
    {
        return collect($posts)->sum(fn($post) => $post->whatsappRecipients()->count());
    }

    /**
     * Accepts comma or newline separated numbers, normalizes to
     * digits-only E.164-style (WhatsApp's `to` field wants the number
     * without a leading `+`).
     */
    private function parseRecipients(string $raw): array
    {
        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn($number) => preg_replace('/[^0-9]/', '', $number))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sends the template message to every recipient still pending for
     * this post. Unlike the single-post_id platforms, there's no one
     * external ID to store on $post itself - success/failure is tracked
     * per row on whatsappRecipients, and the Post's own status reflects
     * the aggregate outcome (matches store()'s "at least one succeeded"
     * convention).
     */
    public function publishPost($post)
    {
        $account = $post->postAccount;

        if (!$account || empty($account->access_token) || empty($account->account_id)) {
            $post->update(['status' => 'failed', 'error_message' => 'WhatsApp account is not connected or missing its phone number ID.']);

            return ['success' => false, 'message' => 'WhatsApp account is not connected.'];
        }

        $metadata = $post->platform_metadata ?? [];
        $templateName = $metadata['template_name'] ?? null;
        $templateLanguage = $metadata['template_language'] ?? 'en_US';

        if (!$templateName) {
            $post->update(['status' => 'failed', 'error_message' => 'Missing WhatsApp template name.']);

            return ['success' => false, 'message' => 'Missing WhatsApp template name.'];
        }

        $components = [];

        if ($post->media_url) {
            $components[] = [
                'type'       => 'header',
                'parameters' => [['type' => 'image', 'image' => ['link' => $post->media_url]]],
            ];
        }

        if ($post->content) {
            $components[] = [
                'type'       => 'body',
                'parameters' => [['type' => 'text', 'text' => $post->content]],
            ];
        }

        $endpoint = $this->baseUrl . $account->account_id . '/messages';
        $recipients = $post->whatsappRecipients()->where('status', 'pending')->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $recipient->phone_number,
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => $templateLanguage],
                    'components' => $components,
                ],
            ];

            $response = $this->api->request('post', $endpoint, [
                'Authorization' => 'Bearer ' . $account->access_token,
                'Content-Type'  => 'application/json',
            ], $payload);

            if ($response->successful()) {
                $recipient->update([
                    'status'               => 'sent',
                    'external_message_id'  => $response->json()['messages'][0]['id'] ?? null,
                    'sent_at'              => now(),
                ]);
                $sentCount++;
            } else {
                $error = $response->json();
                $recipient->update([
                    'status'        => 'failed',
                    'error_message' => $error['error']['message'] ?? 'WhatsApp API request failed.',
                ]);
                $failedCount++;
            }
        }

        if ($sentCount > 0) {
            $post->update([
                'status'        => 'completed',
                'error_message' => $failedCount > 0 ? "{$failedCount} recipient(s) failed - see whatsappRecipients." : null,
            ]);

            return ['success' => true, 'sent_count' => $sentCount, 'failed_count' => $failedCount];
        }

        $post->update(['status' => 'failed', 'error_message' => 'All recipients failed to receive the broadcast.']);

        return ['success' => false, 'message' => 'All recipients failed.'];
    }

    /**
     * WhatsApp messages can't be recalled/deleted from a recipient's chat
     * once delivered via the Business Platform (there's no equivalent to
     * X's DELETE /tweets/:id) - this only cleans up the local record.
     */
    public function destroy($post)
    {
        $post->whatsappRecipients()->delete();

        return ['success' => true, 'message' => 'Removed locally - already-delivered WhatsApp messages cannot be recalled.'];
    }

    protected function uploadMediaToS3($file)
    {
        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $s3Path = "uploads/whatsapp/media/{$fileName}";

            Storage::disk('r2')->put($s3Path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

            $imageInfo = @getimagesize($file->getRealPath());

            return [
                'success' => true,
                'media'   => [
                    'media_type' => 'image',
                    'file_name'  => $fileName,
                    'file_size'  => $file->getSize(),
                    'width'      => $imageInfo[0] ?? null,
                    'height'     => $imageInfo[1] ?? null,
                    'alt_text'   => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'url'        => Storage::disk('r2')->url($s3Path),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
