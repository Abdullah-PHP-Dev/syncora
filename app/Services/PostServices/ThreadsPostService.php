<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
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
 * (and posts.threads.client_id/client_secret filled in) to actually post.
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
        $account = $post->socialAccount;

        if (!empty($account->expires_at) && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))) {
            return true;
        }

        $endpoint = 'https://graph.threads.net/refresh_access_token';

        $response = $this->api->request('get', $endpoint, [], [
            'grant_type'   => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token' => $tokenData['access_token'],
            'expires_at'   => now()->addSeconds($tokenData['expires_in'] ?? 5184000), // 60 days
        ]);

        $account->refresh();

        return true;
    }

    public function store($data, $pages)
    {
        $results = [];
        $errors = [];
        $successCount = 0;

        if (empty($data['content']) && empty($data['media'])) {
            return ['success' => false, 'message' => 'Post content or media is required'];
        }

        $uploadResult = ['media' => []];

        if (!empty($data['media'])) {
            $uploadResult = $this->uploadMediaToS3($data['media'] ?? []);

            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['message']];
            }
        }

        foreach ($pages as $page) {
            try {
                $post = $this->post::create([
                    'title'            => $data['title'] ?? Auth::user()->name,
                    'post_id'          => null,
                    'platform'         => 'threads',
                    'visibility'       => 'public',
                    'user_id'          => Auth::id(),
                    'social_account_id'  => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'group_id'         => $data['group_id'] ?? null,
                    'content'          => $data['content'] ?? null,
                    'schedule_mode'    => $data['schedule_mode'] ?? 0,
                    'schedule_at'      => $data['schedule_at'] ?? null,
                    'expiry_mode'      => $data['expiry_mode'] ?? 0,
                    'expiry_at'        => $data['expiry_at'] ?? null,
                    'status'           => 'pending',
                ]);

                foreach ($uploadResult['media'] as $media) {
                    $this->media::create([
                        'platform'         => 'threads',
                        'post_id'          => $post->id,
                        'visibility'       => 'public',
                        'user_id'          => Auth::id(),
                        'social_account_id'  => $page->id,
                        'post_category_id' => $data['category_id'] ?? 1,
                        'media_url'        => $media['url'],
                        'media_type'       => $media['media_type'],
                        'file_name'        => $media['file_name'],
                        'file_size'        => $media['file_size'],
                        'width'            => $media['width'],
                        'height'           => $media['height'],
                        'duration_seconds' => $media['duration_seconds'] ?? null,
                        'alt_text'         => $media['alt_text'],
                    ]);
                }

                $successCount++;
                $results[] = $post;
            } catch (\Exception $e) {
                $errors[] = [
                    'page_id'   => $page->platform_account_id,
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
                ? "Post created for {$successCount} page(s) and will be processed in background."
                : 'Failed to create posts.',
        ];
    }

    public function publishPost($post)
    {
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
