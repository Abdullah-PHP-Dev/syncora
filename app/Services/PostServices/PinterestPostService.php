<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Pinterest API v5 (api.pinterest.com/v5/) - endpoints/flow verified
 * against developers.pinterest.com and Pinterest's own Postman collection
 * this session.
 *
 * Unlike every other platform in this module, a Pin always belongs to a
 * board (board_id is required on every Create Pin call) - Pinterest has
 * no equivalent of a plain profile feed. Rather than adding a
 * board-picker to the composer (a bigger UX change affecting every
 * platform's shared fields), each connected PostAccount gets one default
 * board resolved/created at connect time and stored in `settings.
 * board_id` (see PostAccountController::callbackPinterest) - matching how
 * this app already stashes platform-specific extras (WhatsApp's waba_id)
 * in that same JSON column rather than bespoke tables.
 *
 * Pinterest also has no text-only Pin type - every Pin needs an image or
 * video, so store() rejects posts with no media (unlike Threads/Facebook/
 * Instagram, which all support text-only).
 */
class PinterestPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.pinterest.base_url') ?: 'https://api.pinterest.com/v5/';
    }

    protected function ensureValidToken($post)
    {
        $account = $post->postAccount;

        if (!empty($account->expires_in) && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))) {
            return true;
        }

        if (empty($account->refresh_token)) {
            return false;
        }

        $credentials = base64_encode(adminSetting('posts.pinterest.client_id') . ':' . adminSetting('posts.pinterest.client_secret'));

        $response = $this->api->request('post', adminSetting('posts.pinterest.token_url'), [
            'Authorization' => "Basic {$credentials}",
        ], [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ], 'form');

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token' => $tokenData['access_token'],
            'expires_in'   => now()->addSeconds($tokenData['expires_in'] ?? 2592000),
        ]);

        $account->refresh();

        return true;
    }

    public function store($data, $pages)
    {
        $results = [];
        $errors = [];
        $successCount = 0;

        if (empty($data['media'])) {
            return ['success' => false, 'message' => 'Pinterest Pins require an image or video - text-only posts are not supported on Pinterest.'];
        }

        $uploadResult = $this->uploadMediaToS3($data['media']);

        if (!$uploadResult['success']) {
            return ['success' => false, 'message' => $uploadResult['message']];
        }

        foreach ($pages as $page) {
            try {
                $post = $this->post::create([
                    'title'            => $data['title'] ?? Auth::user()->name,
                    'post_id'          => null,
                    'platform'         => 'pinterest',
                    'visibility'       => 'public',
                    'user_id'          => Auth::id(),
                    'post_account_id'  => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'content'          => $data['content'] ?? null,
                    'post_url'         => $data['url'] ?? null,
                    'schedule_mode'    => $data['schedule_mode'] ?? 0,
                    'schedule_at'      => $data['schedule_at'] ?? null,
                    'expiry_mode'      => $data['expiry_mode'] ?? 0,
                    'expiry_at'        => $data['expiry_at'] ?? null,
                    'status'           => 'pending',
                ]);

                // Pinterest allows exactly one media item per Pin (no
                // carousel support here - Pinterest's multi-image Pin
                // shape wasn't confirmed precisely enough to build against
                // without risking a fabricated field name), so only the
                // first uploaded file is used.
                $media = $uploadResult['media'][0];

                $this->media::create([
                    'platform'         => 'pinterest',
                    'post_id'          => $post->id,
                    'visibility'       => 'public',
                    'user_id'          => Auth::id(),
                    'post_account_id'  => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'media_url'        => $media['url'],
                    'media_type'       => $media['media_type'],
                    'file_name'        => $media['file_name'],
                    'file_size'        => $media['file_size'],
                    'width'            => $media['width'],
                    'height'           => $media['height'],
                    'alt_text'         => $media['alt_text'],
                ]);

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
                ? "Pin created for {$successCount} board(s) and will be processed in background."
                : 'Failed to create Pins.',
        ];
    }

    public function publishPost($post)
    {
        if (!$this->ensureValidToken($post)) {
            $post->update(['status' => 'failed', 'error_message' => 'Failed to refresh access token']);

            return ['success' => false];
        }

        $account = $post->postAccount;
        $boardId = $account->settings['board_id'] ?? null;

        if (!$boardId) {
            $post->update(['status' => 'failed', 'error_message' => 'This Pinterest account has no default board configured.']);

            return ['success' => false];
        }

        $mediaItem = $post->media->first();

        if (!$mediaItem) {
            $post->update(['status' => 'failed', 'error_message' => 'No media attached to this Pin.']);

            return ['success' => false];
        }

        if ($mediaItem->media_type === 'video') {
            $mediaSource = $this->registerAndUploadVideo($mediaItem, $post);

            if (!($mediaSource['success'] ?? false)) {
                $post->update(['status' => 'failed', 'error_message' => $mediaSource['message'] ?? 'Video upload to Pinterest failed.']);

                return $mediaSource;
            }

            $mediaSource = $mediaSource['media_source'];
        } else {
            $mediaSource = [
                'source_type' => 'image_url',
                'url'         => $mediaItem->media_url,
            ];
        }

        $payload = array_filter([
            'board_id'     => $boardId,
            'title'        => \Illuminate\Support\Str::limit($post->title ?? '', 100, ''),
            'description'  => $post->content,
            'link'         => $post->post_url,
            'media_source' => $mediaSource,
        ]);

        $response = $this->api->request('post', $this->baseUrl . 'pins', [
            'Authorization' => 'Bearer ' . $account->access_token,
        ], $payload);

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $post->update([
            'post_id'       => $response->json()['id'],
            'status'        => 'completed',
            'error_message' => null,
        ]);

        return ['success' => true, 'id' => $post->post_id];
    }

    /**
     * Pinterest's video Pin flow is a 4-step async process: register the
     * upload (POST /media), upload the actual file to the returned
     * pre-signed S3 URL using the returned upload_parameters as form
     * fields, poll GET /media/{id} until status=succeeded, then reference
     * the media_id in the Pin's media_source.
     */
    private function registerAndUploadVideo($mediaItem, $post): array
    {
        $registerResponse = $this->api->request('post', $this->baseUrl . 'media', [
            'Authorization' => 'Bearer ' . $post->postAccount->access_token,
        ], ['media_type' => 'video']);

        if (!$registerResponse->successful()) {
            return ['success' => false, 'message' => $registerResponse->json()['message'] ?? 'Failed to register video upload with Pinterest.'];
        }

        $registration = $registerResponse->json();
        $mediaId = $registration['media_id'];
        $uploadUrl = $registration['upload_url'];
        $uploadParameters = $registration['upload_parameters'] ?? [];

        $videoContents = @file_get_contents($mediaItem->media_url);

        if ($videoContents === false) {
            return ['success' => false, 'message' => 'Could not fetch the uploaded video from storage to forward to Pinterest.'];
        }

        // The pre-signed upload target isn't Pinterest's own API and needs
        // its documented form fields alongside the file, not a Bearer
        // token - ApiPostService doesn't support multipart uploads, so
        // this goes straight through the HTTP client.
        $uploadResponse = Http::asMultipart()
            ->attach('file', $videoContents, $mediaItem->file_name ?: 'video.mp4')
            ->post($uploadUrl, $uploadParameters);

        if (!$uploadResponse->successful()) {
            return ['success' => false, 'message' => 'Failed to upload video binary to Pinterest.'];
        }

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $statusResponse = $this->api->request('get', $this->baseUrl . "media/{$mediaId}", [
                'Authorization' => 'Bearer ' . $post->postAccount->access_token,
            ]);

            $status = $statusResponse->json()['status'] ?? null;

            if ($status === 'succeeded') {
                return [
                    'success'      => true,
                    'media_source' => [
                        'source_type'    => 'video_id',
                        'media_id'       => $mediaId,
                        'cover_image_url' => $mediaItem->thumbnail_url ?: $mediaItem->media_url,
                    ],
                ];
            }

            if ($status === 'failed') {
                return ['success' => false, 'message' => 'Pinterest reported video processing failed.'];
            }

            sleep(4);
        }

        return ['success' => false, 'message' => 'Video processing on Pinterest timed out.'];
    }

    public function destroy($post)
    {
        $this->ensureValidToken($post);

        $response = $this->api->request('delete', $this->baseUrl . "pins/{$post->post_id}", [
            'Authorization' => 'Bearer ' . $post->postAccount->access_token,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Unknown error',
                'status'  => $response->status(),
            ];
        }

        return ['success' => true, 'status' => $response->status()];
    }

    protected function uploadMediaToS3($files)
    {
        try {
            $media = [];

            foreach ($files as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileName = time() . '_' . uniqid() . '.' . $extension;
                $s3Path = "uploads/pinterest/media/{$fileName}";

                Storage::disk('s3')->put($s3Path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

                $videoExtensions = ['mp4', 'mov', 'm4v'];
                $mediaType = in_array($extension, $videoExtensions) ? 'video' : 'image';

                $width = null;
                $height = null;

                if ($mediaType === 'image') {
                    $imageInfo = @getimagesize($file->getRealPath());
                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }

                $media[] = [
                    'media_type' => $mediaType,
                    'file_name'  => $fileName,
                    'file_size'  => $file->getSize(),
                    'width'      => $width,
                    'height'     => $height,
                    'alt_text'   => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'url'        => Storage::disk('s3')->url($s3Path),
                ];
            }

            return ['success' => true, 'media' => $media];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function errorResponse($model, $response)
    {
        $data = $response->json() ?? [];
        $message = $data['message'] ?? $data['error_description'] ?? 'Unknown Pinterest API error';

        $model->status = 'failed';
        $model->error_message = $message;
        $model->save();

        return [
            'success' => false,
            'status'  => $response->status(),
            'message' => $message,
        ];
    }
}
