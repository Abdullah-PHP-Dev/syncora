<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use getID3;
use App\Models\PostComment;

class InstagramPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        // Default/fallback only - publishing/refresh always resolve the
        // per-account URL via resolveBaseUrl() since the two Instagram
        // connect flows (Facebook Login for Business vs standalone
        // Instagram Login) issue tokens that only work against their own
        // Graph API domain - see resolveBaseUrl()'s docblock.
        $this->baseUrl = 'https://graph.facebook.com/v25.0/';
    }

    /**
     * PostAccountController has two independent, non-interchangeable
     * Instagram connect flows:
     *  - callbackMeta() (Facebook Login for Business): issues a Facebook
     *    Page access token, only valid against graph.facebook.com.
     *  - callbackInstagram() (standalone Instagram Login): issues an
     *    Instagram-scoped token (via api.instagram.com/graph.instagram.com),
     *    only valid against graph.instagram.com - using it with
     *    graph.facebook.com produces an "invalid token" error even though
     *    the token itself is fine.
     * callbackInstagram() tags accounts it creates with
     * settings.auth_type = 'instagram_login' so publishing/refresh here can
     * route each account to the domain its token actually belongs to.
     */
    protected function isInstagramLoginAccount($account): bool
    {
        return ($account->settings['auth_type'] ?? null) === 'instagram_login';
    }

    protected function resolveBaseUrl($account): string
    {
        return $this->isInstagramLoginAccount($account)
            ? 'https://graph.instagram.com/v20.0/'
            : 'https://graph.facebook.com/v25.0/';
    }

    protected function ensureValidToken($post)
    {
        // Resolve postAccount correctly whether $post is Post model or PostAccount model
        $account = $post instanceof \App\Models\Post ? $post->postAccount : $post;

        if (!$account) {
            return false;
        }

        // Return true if token is valid for more than 5 minutes
        if (!empty($account->expires_in) && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))) {
            return true;
        }

        if ($this->isInstagramLoginAccount($account)) {
            // Instagram Login tokens are refreshed on graph.instagram.com
            // itself with the current token - no app client_id/secret
            // involved (unlike the Facebook Page token exchange below).
            $response = $this->api->request('get', 'https://graph.instagram.com/refresh_access_token', [], [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $account->access_token,
            ]);
        } else {
            // Facebook Page access token, issued by callbackMeta() using
            // posts.facebook.client_id/secret - must be refreshed with the
            // same app credentials that issued it.
            $response = $this->api->request('get', 'https://graph.facebook.com/v20.0/oauth/access_token', [], [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => adminSetting('posts.facebook.client_id'),
                'client_secret'     => adminSetting('posts.facebook.client_secret'),
                'fb_exchange_token' => $account->access_token,
            ]);
        }

        if (!$response->successful()) {
            return false;
        }

        $tokenData = $response->json();

        $account->update([
            'access_token' => $tokenData['access_token'],
            'expires_in'   => now()->addSeconds($tokenData['expires_in'] ?? 5184000),
        ]);

        $account->refresh();
        return true;
    }

    /**
     * Publish post to multiple Instagram pages using queue
     */
    public function store($data, $pages)
    {
        $results = [];
        $errors = [];
        $successCount = 0;

        // Validate inputs
        if (empty($data['content'])) {
            return [
                'success' => false,
                'message' => 'Post content is required'
            ];
        }

        // Upload media to S3 once
        $mediaUrl = null;
        $mediaType = null;
        $mediaExtension = null;

        if (!empty($data['ai_image_url'])) {
            $mediaExtension = strtolower(pathinfo(
                parse_url($data['ai_image_url'], PHP_URL_PATH),
                PATHINFO_EXTENSION
            ));
            $isImage = in_array($mediaExtension, ['jpg', 'jpeg', 'png', 'webp']);
            $mediaType = $isImage ? 'image' : 'video';
            $mediaUrl = $data['ai_image_url'];
        } else {
            $uploadResult = $this->uploadMediaToS3($data['media']);
            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'message' => $uploadResult['message']
                ];
            }
        }

        // Loop through each page and create container
        foreach ($pages as $page) {
            try {
                // Create post record with status 'pending'
                $post = $this->post::create([
                    'title' => $data['title'] ?? Auth::user()->name,
                    'post_id' => null,
                    'platform' => 'instagram',
                    'visibility' => 'public',
                    'user_id' => Auth::user()->id,
                    'post_account_id' => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'page_id' => $page->account_id,
                    'content' => $data['content'] ?? null,
                    'schedule_mode' => $data['schedule_mode'] ?? 0,
                    'schedule_at' => $data['schedule_at'] ?? null,
                    'expiry_mode' => $data['expiry_mode'] ?? 0,
                    'expiry_at' => $data['expiry_at'] ?? null,
                    'status' => 'pending'
                ]);

                if ($post) {
                    foreach ($uploadResult['media'] as $media) {
                        $saveMedia = $this->media::create([
                            'platform' => 'instagram',
                            'post_id' => $post->id,
                            'visibility' => 'public',
                            'user_id' => Auth::user()->id,
                            'post_category_id' => $data['category_id'],
                            'post_account_id' => $page->id,
                            'media_url' => $media['url'],
                            'media_type' => $media['media_type'],
                            'file_name' => $media['file_name'],
                            'file_size' => $media['file_size'],
                            'width' => $media['width'],
                            'height' => $media['height'],
                            'duration_seconds' => $media['duration_seconds'] ?? null,
                            'thumbnail_url' => $media['thumbnail_url'] ?? null,
                            'alt_text' => $media['alt_text'],
                            'width' => $media['width'],
                        ]);
                    }
                }

                // Dispatch job to process this post (without the file)
                //  ProcessFacebookPostJob::dispatch($post, $page)->onQueue('high');

                $successCount++;
                $results[] = $post;
            } catch (\Exception $e) {
                $errors[] = [
                    'page_id' => $page->account_id,
                    'page_name' => $page->page_name ?? $page->name,
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $successCount > 0,
            'total_pages' => count($pages),
            'success_count' => $successCount,
            'error_count' => count($errors),
            'data' => $results,
            'errors' => $errors,
            'message' => $successCount > 0
                ? "Post created for {$successCount} page(s) and will be processed in background."
                : "Failed to create posts."
        ];
    }

    /**
     * Upload media to S3
     */
    protected function uploadMediaToS3($files)
    {
        try {
            $media = [];

            foreach ($files as $file) {

                $extension = strtolower($file->getClientOriginalExtension());
                $mimeType  = $file->getMimeType();
                $fileSize  = $file->getSize();
                $fileName  = time() . '_' . uniqid() . '.' . $extension;

                $s3Path = "uploads/meta/media/{$fileName}";

                Storage::disk('r2')->put(
                    $s3Path,
                    file_get_contents($file->getRealPath()),
                    ['visibility' => 'public']
                );

                $url = Storage::disk('r2')->url($s3Path);

                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif'];
                $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', 'm4v'];

                $mediaType = 'file';

                if (in_array($extension, $imageExtensions)) {
                    $mediaType = $extension == 'gif' ? 'gif' : 'image';
                }

                if (in_array($extension, $videoExtensions)) {
                    $mediaType = 'video';
                }

                $width = null;
                $height = null;
                $duration = null;
                $thumbnail = null;

                /**
                 * IMAGE
                 */
                if ($mediaType == 'image' || $mediaType == 'gif') {

                    $imageInfo = @getimagesize($file->getRealPath());

                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }

                /**
                 * VIDEO
                 */
                if ($mediaType == 'video') {

                    if (class_exists(\getID3::class)) {
                        $getID3 = new getID3();
                        $info = $getID3->analyze($file->getRealPath());

                        $duration = isset($info['playtime_seconds'])
                            ? round($info['playtime_seconds'], 2)
                            : null;

                        if (isset($info['video']['resolution_x'])) {
                            $width = $info['video']['resolution_x'];
                        }

                        if (isset($info['video']['resolution_y'])) {
                            $height = $info['video']['resolution_y'];
                        }
                    }

                    // Generate thumbnail yourself if required
                    // Example:
                    // uploads/meta/thumbnails/xxxxx.jpg
                    $thumbnail = null;
                }

                $media[] = [

                    'success' => true,

                    'media_type' => $mediaType,

                    'file_name' => $fileName,

                    'original_name' => $file->getClientOriginalName(),

                    'extension' => $extension,

                    'mime_type' => $mimeType,

                    'file_size' => $fileSize,

                    'file_size_mb' => round($fileSize / 1024 / 1024, 2),

                    'width' => $width,

                    'height' => $height,

                    'duration_seconds' => $duration,

                    'thumbnail_url' => $thumbnail,

                    'alt_text' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),

                    'url' => $url,

                    'path' => $s3Path,
                ];
            }

            return [
                'success' => true,
                'media' => $media
            ];
        } catch (\Exception $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    /**
     * Publish Post
     */

    public function publishPost($post)
    {
        $account = $post->postAccount;
   
        if (!$this->ensureValidToken($post)) {
            $post->update([
                'status' => 'failed',
                'error_message' => 'Failed to refresh access token'
            ]);

            return ['success' => false];
        }

        $media = $this->createMediaContainer($post);

        if (!$media['success']) {
            $post->status = 'failed';
            $post->error_message = $media['message'] ?? $media['error'] ?? 'Instagram media publish faced an error.';
            $post->save();

            return $media;
        }

        $isMainReady = false;
        $mainAttempts = 0;

        while (!$isMainReady && $mainAttempts < 10) {
            $checkContainer = $this->checkContainerStatus($media['container_id'], $post);

            if ($checkContainer['success'] && $checkContainer['status'] == 'FINISHED') {
                $isMainReady = true;
            } else {
                $mainAttempts++;
                sleep(2);
            }
        }

        if (!$isMainReady) {
            $post->update([
                'status' => 'failed',
                'error_message' => 'Main carousel container processing timed out.'
            ]);
            return ['success' => false];
        }

        $endpoint = "{$this->resolveBaseUrl($account)}{$account->account_id}/media_publish?access_token={$account->access_token}";

        $response = $this->api->request(
            'post',
            $endpoint,
            [],
            [
                'creation_id' => $media['container_id']
            ],
            'form'
        );

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $publishId = $response->json()['id'];

        $post->post_id = $publishId;
        $post->status = 'completed';
        $post->error_message = '';
        $post->save();

        return ['success' => true];
    }

    /**
     * Create media container for Instagram (Handles Single & Multiple Media)
     */
    public function createMediaContainer($post)
    {
        try {
            $account = $post->postAccount;

            $endpoint = "{$this->resolveBaseUrl($account)}{$account->account_id}/media?access_token={$account->access_token}";

            $mediaCount = count($post->media);

            if ($mediaCount === 0) {
                return [
                    'success' => false,
                    'message' => 'No media items found for this post.'
                ];
            }

            // ==========================================
            // CASE 1: SINGLE MEDIA (IMAGE OR VIDEO)
            // ==========================================
            if ($mediaCount === 1) {
                $mediaItem = $post->media->first(); // Adjust to $post->media[0] if it's an array instead of a Collection

                $payload = [
                    'caption' => $post->content,
                ];

                if ($mediaItem->media_type == 'image') {
                    $payload['image_url'] = $mediaItem->media_url;
                } else {
                    $payload['video_url'] = $mediaItem->media_url;
                    $payload['media_type'] = 'VIDEO';
                }
                $response = $this->api->request('post', $endpoint, [], $payload, 'form');

                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }

                $containerId = $response->json()['id'];
                $mediaItem->media_id = $containerId;
                $mediaItem->save();

                return [
                    'success' => true,
                    'container_id' => $containerId,
                ];
            }

            // ==========================================
            // CASE 2: MULTIPLE MEDIA (CAROUSEL)
            // ==========================================
            $children = [];

            // Step 1: Create all individual child containers
            foreach ($post->media as $media) {
                $payload = [
                    'is_carousel_item' => true,
                ];

                if ($media->media_type == 'image') {
                    $payload['image_url'] = $media->media_url;
                } else {
                    $payload['video_url'] = $media->media_url;
                    $payload['media_type'] = 'VIDEO';
                }

                $response = $this->api->request('post', $endpoint, [], $payload, 'form');

                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }

                $childId = $response->json()['id'];
                $media->media_id = $childId;
                $media->save();

                $children[] = $childId;
            }

            // Step 2: WAIT for all child containers to finish processing
            foreach ($children as $childId) {
                $isReady = false;
                $attempts = 0;
                $maxAttempts = 15;

                while (!$isReady && $attempts < $maxAttempts) {
                    $statusCheck = $this->checkContainerStatus($childId, $post);

                    if ($statusCheck['success'] && $statusCheck['status'] === 'FINISHED') {
                        $isReady = true;
                    } elseif ($statusCheck['success'] && $statusCheck['status'] === 'ERROR') {
                        return [
                            'success' => false,
                            'message' => "Child media container {$childId} failed processing on Instagram."
                        ];
                    } else {
                        $attempts++;
                        sleep(3);
                    }
                }

                if (!$isReady) {
                    return [
                        'success' => false,
                        'message' => "Media container item processing timed out."
                    ];
                }
            }

            // Step 3: Create the final Carousel container
            $response = $this->api->request(
                'post',
                $endpoint,
                [],
                [
                    'media_type' => 'CAROUSEL',
                    'children' => implode(',', $children),
                    'caption' => $post->content,
                ],
                'form'
            );

            if (!$response->successful()) {
                return $this->errorResponse($post, $response);
            }

            $containerId = $response->json()['id'];

            return [
                'success' => true,
                'container_id' => $containerId,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check container status
     */
    public function checkContainerStatus($containerId, $post)
    {
        try {
            $account = $post->postAccount;
            $response = $this->api->request(
                'get',
                $this->resolveBaseUrl($account) . $containerId,
                [],
                [
                    'fields' => 'status_code',
                    'access_token' => $account->access_token,
                ]
            );

            if (!$response->successful()) {
                return $this->errorResponse($post, $response);
            }

            return [
                'success' => true,
                'status' => $response->json()['status_code'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish comment
     */
    public function publishComment($data, $comment)
    {
        $this->ensureValidToken($comment->post);
        $endpoint = $this->resolveBaseUrl($comment->postAccount) . $comment->comment_id . "/replies";

        $payload = [
            "message" => $data['body'] . ' --. '
        ];

        $response = $this->api->request(
            'post',
            $endpoint . "?access_token={$comment->postAccount->access_token}",
            [],
            $payload,
            'form'
        );

        if (!$response->successful()) {
            return $this->errorResponse($comment, $response);
        }

        return $this->storeComment($comment, $data, $response->json()['id']);
    }

    /**
     * Store comment
     */
    private function storeComment($comment, $data, $commentId)
    {
        $comment = PostComment::create([
            'content'            => $data['body'] ?? '',
            'sender_type'     => 'support',
            'platform'        => 'instagram',
            'parent_comment_id' => $comment->id,
            'user_id'         => Auth::user()->id,
            'sender_name'     => Auth::user()->name,
            'post_id'   => $comment->post?->id,
            'is_read'         => 0,
            'is_reply' => true,
            'user_name'            => 'support',
            'comment_id' => $commentId,
            'post_account_id'  => $comment->postAccount?->id
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
    }


    /**
     * Backfill a post's comments from Instagram's Graph API. Used when a post
     * has an engagement comment count but no imported PostComment rows yet
     * (e.g. the comment webhook hasn't captured them).
     */
    public function fetchComments(Post $post): void
    {
        $this->ensureValidToken($post);

        $endpoint = $this->resolveBaseUrl($post->postAccount) . $post->post_id . '/comments';

        $response = $this->api->request('get', $endpoint, [], [
            'fields' => 'id,text,username,timestamp,like_count',
            'access_token' => $post->postAccount->access_token,
        ]);

        if (!$response->successful()) {
            return;
        }

        foreach ($response->json()['data'] ?? [] as $comment) {
            PostComment::updateOrCreate(
                [
                    'comment_id' => $comment['id'],
                    'post_id' => $post->id,
                ],
                [
                    'platform' => 'instagram',
                    'content' => $comment['text'] ?? '',
                    'user_name' => $comment['username'] ?? 'Instagram user',
                    'likes' => $comment['like_count'] ?? 0,
                    'posted_at' => $comment['timestamp'] ?? now(),
                    'sender_type' => 'customer',
                    'is_reply' => false,
                    'post_account_id' => $post->postAccount?->id,
                ]
            );
        }
    }

    /**
     * Get posts
     */
    public function getPosts($pageId, $pageToken)
    {
        $account = MediaAccount::where('account_id', $pageId)->first();
        $this->ensureValidToken($account);
        $endpoint = "https://graph.facebook.com/v25.0/{$pageId}/media";

        $response = $this->api->request(
            'get',
            $endpoint . "?access_token={$pageToken}",
            []
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error'
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()['data']
        ];
    }

    /**
     * Delete post
     */
    public function destroy($post)
    {
        $this->ensureValidToken($post);
        $endpoint = $this->resolveBaseUrl($post->postAccount) . $post->post_id;

        $response = $this->api->request(
            'delete',
            $endpoint . "?access_token={$post->postAccount->access_token}",
            []
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error',
                'status' => $response->status()
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
            'status' => $response->status()
        ];
    }

    /**
     * Delete comment
     */
    public function destroyComment($chat)
    {
        $this->ensureValidToken($chat->postAccount);
        $endpoint = $this->resolveBaseUrl($chat->postAccount) . $chat->comment_id;

        $response = $this->api->request(
            'delete',
            $endpoint . "?access_token={$chat->socialPublish->access_token}",
            []
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error'
            ];
        }

        if (isset($chat->media)) {
            Storage::disk('r2')->delete($chat->media);
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }

    /**
     * Update post
     */
    public function updatePost($postId, $data, $token)
    {
        $account = SocialPost::with('postAccount')->where('post_id', $postId)->first();
        $this->ensureValidToken($account->postAccount);

        $payload = [
            'message' => $data['content'],
        ];

        if (!empty($data['schedule_mode']) && $data['schedule_mode'] === 'on') {
            $payload['published'] = false;
            $payload['scheduled_publish_time'] = Carbon::parse($data['schedule_at'])->timestamp;
        }

        if (isset($data['media'])) {
            $extension = strtolower($data['media']->getClientOriginalExtension());
            $fileName = time() . '.' . $extension;
            $s3Path = "uploads/instagram/media/{$fileName}";

            Storage::disk('r2')->put(
                $s3Path,
                file_get_contents($data['media']->getRealPath()),
                ['visibility' => 'public']
            );

            $url = Storage::disk('r2')->url($s3Path);
            $endpoint = "https://graph.facebook.com/v22.0/{$postId}";
            $payload['url'] = $url;
        } else if (isset($data['url'])) {
            $endpoint = "https://graph.facebook.com/v22.0/{$postId}";
            $payload['link'] = $data['url'];
        }

        $response = $this->api->request(
            'post',
            $endpoint . "?access_token={$token}",
            ['Content-Type' => 'application/json'],
            $payload,
            'form'
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error'
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()
        ];
    }

    private function errorResponse($model, $response)
    {
        $model->status = 'failed';
        $model->error_message = $response->json()['error']['message'] ?? 'Unknown error';
        $model->save();

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Unknown error'
        ];
    }
}
