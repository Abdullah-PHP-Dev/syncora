<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * Threads (Meta) - graph.threads.net, a separate API/App from the regular
 * Facebook Graph API this codebase's MetaPostService/InstagramPostService
 * talk to, but the same two-step "create a media container, then publish
 * it" mechanic as Instagram - endpoints/flow verified against
 * developers.facebook.com/docs/threads this session. Picked as the
 * platform to add here for its combination of real, dedicated
 * content-publishing API and global scale (300M+ MAU).
 *
 * Production publishing to other people's accounts requires Meta Tech
 * Provider Verification (a manual review process on Meta's side, same
 * category of prerequisite as the Ads module's developer_token or the
 * WhatsApp module's App Review) - this builds the real integration now
 * per this app's established pattern, but it needs that verification
 * (and the Threads app ID/secret - ThreadsDriver::clientId/clientSecret) to actually post.
 */
class ThreadsPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.threads.base_url') ?: 'https://graph.threads.net/v1.0/';
    }

    /**
     * Threads' long-lived token refresh (grant_type=th_refresh_token) only
     * needs the current token itself, not client_id/secret - unlike the
     * initial short->long-lived exchange, which does.
     */
    protected function ensureValidToken($post)
    {
        $failed = $this->refreshAccountToken($post->socialAccount);

        return $failed === null ? true : $this->errorResponse($post, $failed);
    }

    /**
     * Renews the account's long-lived token when it expires within 5
     * minutes. Returns the failed response, or null when the token is good -
     * without touching any post (a failed reply must not fail its post).
     */
    private function refreshAccountToken(SocialAccount $account): ?\Illuminate\Http\Client\Response
    {
        if (!empty($account->expires_at) && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))) {
            return null;
        }

        $response = $this->api->request('get', 'https://graph.threads.net/refresh_access_token', [], [
            'grant_type'   => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if (!$response->successful()) {
            return $response;
        }

        $tokenData = $response->json();

        $account->update([
            'access_token' => $tokenData['access_token'],
            'expires_at'   => now()->addSeconds($tokenData['expires_in'] ?? 5184000), // 60 days
        ]);

        $account->refresh();

        return null;
    }

    public function publishPost($post)
    {
        $owner = \App\Models\User::findOrFail($post->user_id);
        app(\App\Services\PackageAccessService::class)->authorize($owner, 'publishing');
        // Recheck pending legacy records too; the ledger prevents retry double charging.
        app(\App\Services\PackageUsageService::class)->reserve($owner, 'scheduled_published_posts', 1, 'post:'.$post->id);

        if (!$this->ensureValidToken($post)) {
            $post->update(['status' => 'failed', 'error_message' => 'Failed to refresh access token']);

            return ['success' => false];
        }

        $container = $this->createMediaContainer($post);

        if (!$container['success']) {
            $post->update([
                'status'        => 'failed',
                'error_message' => $container['message'] ?? 'Threads media container creation failed.',
            ]);

            return $container;
        }

        // Meta's own guidance for Threads is to wait rather than poll a
        // status endpoint (unlike Instagram's documented status_code
        // polling loop) - "recommended to wait on average 30 seconds
        // before publishing a Threads media container".
        sleep(30);

        $account = $post->socialAccount;
        $endpoint = $this->baseUrl . $account->platform_account_id . '/threads_publish';

        $response = $this->api->request('post', $endpoint, [], [
            'creation_id'  => $container['container_id'],
            'access_token' => $account->access_token,
        ], 'form');

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
     * Threads' container creation mirrors Instagram's shape closely -
     * media_type TEXT/IMAGE/VIDEO/CAROUSEL, with carousel items created
     * individually first (is_carousel_item=true) then bundled via a
     * children list.
     */
    protected function createMediaContainer($post)
    {
        try {
            $account = $post->socialAccount;
            $endpoint = $this->baseUrl . $account->platform_account_id . '/threads';
            $mediaCount = count($post->media);

            if ($mediaCount === 0) {
                $response = $this->api->request('post', $endpoint, [], [
                    'media_type'   => 'TEXT',
                    'text'         => $post->content,
                    'access_token' => $account->access_token,
                ], 'form');

                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }

                return ['success' => true, 'container_id' => $response->json()['id']];
            }

            if ($mediaCount === 1) {
                $mediaItem = $post->media->first();

                $payload = [
                    'text'         => $post->content,
                    'access_token' => $account->access_token,
                ];

                if ($mediaItem->media_type === 'image') {
                    $payload['media_type'] = 'IMAGE';
                    $payload['image_url'] = $mediaItem->media_url;
                } else {
                    $payload['media_type'] = 'VIDEO';
                    $payload['video_url'] = $mediaItem->media_url;
                }

                $response = $this->api->request('post', $endpoint, [], $payload, 'form');

                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }

                return ['success' => true, 'container_id' => $response->json()['id']];
            }

            // CAROUSEL (2-20 items)
            $children = [];

            foreach ($post->media as $media) {
                $payload = [
                    'is_carousel_item' => 'true',
                    'access_token'     => $account->access_token,
                ];

                if ($media->media_type === 'image') {
                    $payload['media_type'] = 'IMAGE';
                    $payload['image_url'] = $media->media_url;
                } else {
                    $payload['media_type'] = 'VIDEO';
                    $payload['video_url'] = $media->media_url;
                }

                $response = $this->api->request('post', $endpoint, [], $payload, 'form');

                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }

                $children[] = $response->json()['id'];
            }

            $response = $this->api->request('post', $endpoint, [], [
                'media_type'   => 'CAROUSEL',
                'children'     => implode(',', $children),
                'text'         => $post->content,
                'access_token' => $account->access_token,
            ], 'form');

            if (!$response->successful()) {
                return $this->errorResponse($post, $response);
            }

            return ['success' => true, 'container_id' => $response->json()['id']];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function destroy($post)
    {
        $this->ensureValidToken($post);

        $endpoint = $this->baseUrl . $post->post_id;

        $response = $this->api->request('delete', $endpoint, [], [
            'access_token' => $post->socialAccount->access_token,
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error',
                'status'  => $response->status(),
            ];
        }

        return ['success' => true, 'data' => $response->json(), 'status' => $response->status()];
    }

    /**
     * Same shape as XPostService/InstagramPostService's upload helper.
     */
    protected function uploadMediaToS3($files)
    {
        try {
            $media = [];

            foreach ($files as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileName = time() . '_' . uniqid() . '.' . $extension;
                $s3Path = "uploads/threads/media/{$fileName}";

                Storage::disk('r2')->put($s3Path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

                $imageExtensions = ['jpg', 'jpeg', 'png'];
                $mediaType = in_array($extension, $imageExtensions) ? 'image' : 'video';

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
                    'url'        => Storage::disk('r2')->url($s3Path),
                ];
            }

            return ['success' => true, 'media' => $media];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Replies (developers.facebook.com/docs/threads/retrieve-and-manage-replies)
    // ------------------------------------------------------------------

    /** Fields of {media-id}/conversation that saveReply() stores. */
    private const REPLY_FIELDS = 'id,text,username,timestamp,replied_to,root_post,is_reply_owned_by_me,profile_picture_url,hide_status';

    /**
     * Pull the post's replies - top level and nested - into post_comments
     * (GET {media-id}/conversation, scope threads_read_replies). Oldest
     * first so a reply's parent is saved before it. Returns how many were
     * stored, or null when Threads refused.
     */
    public function importReplies(Post $post, int $maxPages = 5): ?int
    {
        $account = $post->socialAccount;
        if (!$account || !ctype_digit((string) $post->post_id) || $this->refreshAccountToken($account)) {
            return null; // not on Threads yet, or the connection expired
        }

        $webhook = app(ThreadsReplyWebhookService::class);
        $params = ['fields' => self::REPLY_FIELDS, 'reverse' => 'false', 'access_token' => $account->access_token];
        $stored = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->api->request('get', $this->baseUrl . $post->post_id . '/conversation', [], $params);

            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::warning('Threads replies import failed.', ['post_id' => $post->id, 'error' => $response->json('error.message') ?? $response->status()]);

                return $page === 0 ? null : $stored;
            }

            foreach ((array) $response->json('data') as $reply) {
                // Hidden by the owner on Threads: keep them out of the inbox.
                if (($reply['hide_status'] ?? null) === 'HIDDEN') {
                    continue;
                }
                $stored += $webhook->saveReply($account, $reply, $post) ? 1 : 0;
            }

            $after = $response->json('paging.cursors.after');
            if (!$after || !$response->json('paging.next')) {
                break;
            }
            $params['after'] = $after;
        }

        return $stored;
    }

    /**
     * Reply as the business: to the post itself ($type 'comment', $target a
     * Post) or to a reply ($type 'reply', $target a PostComment). A TEXT
     * container with reply_to_id, then threads_publish (scope
     * threads_manage_replies). Same contract as the other platforms'
     * publishComment(): ['success', 'data' => PostComment] or a message.
     */
    public function publishComment($data, $target, $type = 'reply')
    {
        $post = $target instanceof Post ? $target : $target->post;
        $account = $post?->socialAccount;
        $replyTo = (string) ($target instanceof Post ? $target->post_id : $target->comment_id);
        $text = trim((string) ($data['body'] ?? ''));

        // Only a published post has a numeric Threads media id.
        if (!$account || $text === '' || $replyTo === '' || !ctype_digit((string) $post->post_id)) {
            return ['success' => false, 'message' => 'This Threads post is not published yet, so it cannot be replied to.'];
        }
        if ($failed = $this->refreshAccountToken($account)) {
            return ['success' => false, 'message' => 'Threads connection expired - reconnect Threads in Connections. (' . ($failed->json('error.message') ?? 'HTTP ' . $failed->status()) . ')'];
        }

        $container = $this->api->request('post', $this->baseUrl . 'me/threads', [], [
            'media_type'   => 'TEXT',
            'text'         => $text,
            'reply_to_id'  => $replyTo,
            'access_token' => $account->access_token,
        ], 'form');

        if (!$container->successful() || !$container->json('id')) {
            return ['success' => false, 'message' => $this->replyError($container)];
        }

        $this->waitForContainer((string) $container->json('id'), $account);

        $published = $this->api->request('post', $this->baseUrl . $account->platform_account_id . '/threads_publish', [], [
            'creation_id'  => $container->json('id'),
            'access_token' => $account->access_token,
        ], 'form');

        if (!$published->successful() || !$published->json('id')) {
            return ['success' => false, 'message' => $this->replyError($published)];
        }

        $comment = app(ThreadsReplyWebhookService::class)->saveReply($account, [
            'id' => (string) $published->json('id'),
            'text' => $text,
            'username' => $account->username,
            'is_reply_owned_by_me' => true,
            'profile_picture_url' => $account->avatar_url,
            'replied_to' => ['id' => $replyTo],
            'root_post' => ['id' => $post->post_id],
        ], $post);

        return ['success' => true, 'message' => 'Reply published on Threads.', 'data' => $comment];
    }

    /**
     * A text container is usually ready at once; Meta's flat 30-second wait
     * (publishPost) is for media. Poll its status briefly instead, so a
     * reply doesn't hold the request for half a minute.
     */
    private function waitForContainer(string $containerId, SocialAccount $account, int $attempts = 5): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $status = $this->api->request('get', $this->baseUrl . $containerId, [], ['fields' => 'status', 'access_token' => $account->access_token])->json('status');
            if ($status !== 'IN_PROGRESS') {
                return; // FINISHED, or ERROR/EXPIRED - publish reports those
            }
            usleep(1_500_000);
        }
    }

    private function replyError($response): string
    {
        $message = $response->json('error.message') ?? ('HTTP ' . $response->status());

        // 10 / 200: permission errors - most often threads_manage_replies not granted yet.
        return in_array((int) $response->json('error.code'), [10, 200], true)
            ? "Threads refused the reply: {$message} Reconnect Threads in Connections to grant reply permission."
            : "Threads refused the reply: {$message}";
    }

    private function errorResponse($model, $response)
    {
        $data = $response->json() ?? [];
        $message = $data['error']['message'] ?? $data['error_description'] ?? 'Unknown Threads API error';

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
