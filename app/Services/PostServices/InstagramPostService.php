<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use getID3;
use App\Models\SocialAccount;
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
        return ($account->metadata['settings']['auth_type'] ?? null) === 'instagram_login';
    }

    protected function resolveBaseUrl($account): string
    {
        return $this->isInstagramLoginAccount($account)
            ? 'https://graph.instagram.com/v20.0/'
            : 'https://graph.facebook.com/v25.0/';
    }

    protected function ensureValidToken($post)
    {
        // Resolve socialAccount correctly whether $post is Post model or SocialAccount model
        $account = $post instanceof \App\Models\Post ? $post->socialAccount : $post;

        if (!$account) {
            return false;
        }

        // Return true if token is valid for more than 5 minutes
        if (!empty($account->expires_at) && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))) {
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
            'expires_at'   => now()->addSeconds($tokenData['expires_in'] ?? 5184000),
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

        if (isset($data['uploaded_media'])) {
            // Already uploaded once by PostController::quickStore() and
            // shared across every platform in this submission - see
            // uploadQuickPostMedia()'s docblock.
            $uploadResult = ['success' => true, 'media' => $data['uploaded_media']];
        } elseif (!empty($data['ai_image_url'])) {
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
                    'group_id' => $data['group_id'] ?? null,
                    'social_account_id' => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'page_id' => $page->platform_account_id,
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
                            'social_account_id' => $page->id,
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
                    'page_id' => $page->platform_account_id,
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
        $account = $post->socialAccount;
   
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

        $endpoint = "{$this->resolveBaseUrl($account)}{$account->platform_account_id}/media_publish?access_token={$account->access_token}";

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
            $account = $post->socialAccount;

            $endpoint = "{$this->resolveBaseUrl($account)}{$account->platform_account_id}/media?access_token={$account->access_token}";

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
                    // media_type=VIDEO for a single (non-carousel) post is
                    // deprecated - Instagram now rejects it outright with
                    // "Invalid parameter: Unsupported media type VIDEO:
                    // The VIDEO value for media_type is deprecated. Use
                    // the REELS media type to publish a video to your
                    // Instagram feed." share_to_feed defaults to true, so
                    // this still appears in the main feed, not just Reels.
                    // VIDEO remains correct for carousel children below -
                    // this only affects the single-media case.
                    $payload['media_type'] = 'REELS';
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
            $account = $post->socialAccount;
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
        $endpoint = $this->resolveBaseUrl($comment->socialAccount) . $comment->comment_id . "/replies";

        $payload = [
            "message" => $data['body'] . ' --. '
        ];

        $response = $this->api->request(
            'post',
            $endpoint . "?access_token={$comment->socialAccount->access_token}",
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
            'social_account_id'  => $comment->socialAccount?->id
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
    }


    /**
     * Fetches current account-level stats (followers/following/media
     * count) plus a richer raw insights snapshot, called once right
     * after an Instagram account is connected. Works for both the
     * Page-linked (callbackMeta) and standalone Instagram Login
     * (callbackInstagram) flows via resolveBaseUrl(). The two Graph API
     * calls are independent - an account too new/small for Insights data
     * must not prevent the plain followers_count/media_count fields,
     * which have no such gating, from saving.
     */
    public function syncAccountStats(SocialAccount $account): void
    {
        $baseUrl = $this->resolveBaseUrl($account);

        $fieldsResponse = $this->api->request(
            'get',
            $baseUrl . $account->platform_account_id,
            [],
            ['fields' => 'followers_count,follows_count,media_count', 'access_token' => $account->access_token]
        );

        if ($fieldsResponse->successful()) {
            $data = $fieldsResponse->json();
            $account->update([
                'followers_count' => $data['followers_count'] ?? $account->followers_count,
                'following_count' => $data['follows_count'] ?? $account->following_count,
                'media_count'     => $data['media_count'] ?? $account->media_count,
            ]);
        } else {
            Log::warning('Failed to fetch Instagram account fields.', [
                'account_id' => $account->id,
                'error'      => $fieldsResponse->json()['error']['message'] ?? $fieldsResponse->body(),
            ]);
        }

        try {
            $insightsResponse = $this->api->request(
                'get',
                $baseUrl . $account->platform_account_id . '/insights',
                [],
                ['metric' => 'reach,profile_views', 'period' => 'day', 'access_token' => $account->access_token]
            );

            if ($insightsResponse->successful()) {
                $account->update(['metadata' => array_merge($account->metadata ?? [], [
                    'insights' => array_merge($account->metadata['insights'] ?? [], ['account' => $insightsResponse->json()['data'] ?? []]),
                ])]);
            } else {
                // Expected for accounts too new/small for Insights data - not a bug.
                Log::warning('Instagram account insights fetch failed (likely too new/small for this metric).', [
                    'account_id' => $account->id,
                    'error'      => $insightsResponse->json()['error']['message'] ?? $insightsResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Instagram account insights fetch threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Registers this Instagram account with the app's webhook
     * subscription so Meta actually starts delivering comment/mention
     * events for it - the App-Dashboard-level webhook config alone is
     * not sufficient, Meta requires this per-account opt-in too (POST
     * .../subscribed_apps). 'comments' matches exactly what
     * processCommentChange() expects for Instagram.
     */
    public function subscribeToWebhooks(SocialAccount $account): void
    {
        try {
            $response = $this->api->request(
                'post',
                $this->resolveBaseUrl($account) . $account->platform_account_id . '/subscribed_apps',
                [],
                ['subscribed_fields' => 'comments,mentions', 'access_token' => $account->access_token]
            );

            if ($response->successful() && ($response->json()['success'] ?? false)) {
                $account->update(['metadata' => array_merge($account->metadata ?? [], ['webhook_subscriptions' => true])]);
            } else {
                Log::warning('Failed to subscribe Instagram account to webhooks.', [
                    'account_id' => $account->id,
                    'error'      => $response->json()['error']['message'] ?? $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Instagram account webhook subscription threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * On connect, backfills the account's most recent media (default 4)
     * along with each item's insights and comments, so a newly connected
     * account isn't empty until the customer publishes something new
     * through this app. Each media/insights/comments call is
     * independently failure-tolerant - one bad item must not abort the
     * rest of the batch.
     */
    public function backfillRecentPosts(SocialAccount $account, int $limit = 4): void
    {
        $baseUrl = $this->resolveBaseUrl($account);

        $mediaResponse = $this->api->request(
            'get',
            $baseUrl . $account->platform_account_id . '/media',
            [],
            ['fields' => 'id,caption,media_type,media_url,timestamp,like_count,comments_count', 'limit' => $limit, 'access_token' => $account->access_token]
        );

        if (!$mediaResponse->successful()) {
            Log::warning('Failed to fetch Instagram media for backfill.', [
                'account_id' => $account->id,
                'error'      => $mediaResponse->json()['error']['message'] ?? $mediaResponse->body(),
            ]);
            return;
        }

        foreach ($mediaResponse->json()['data'] ?? [] as $item) {
            try {
                // Backfill is a one-time "don't leave a freshly connected
                // account empty" seed, not a sync - a post already on file
                // (eg. from a previous connect of this same account) is
                // left untouched rather than re-fetching its media/
                // insights/comments on every reconnect.
                if (Post::where('social_account_id', $account->id)->where('post_id', $item['id'])->exists()) {
                    continue;
                }

                $post = Post::create(
                    [
                        'social_account_id' => $account->id,
                        'post_id'  => $item['id'],
                        'platform' => 'instagram',
                        'user_id'  => $account->user_id,
                        'content'  => $item['caption'] ?? '',
                        'likes'    => $item['like_count'] ?? 0,
                        'comments' => $item['comments_count'] ?? 0,
                        'status'   => 'completed',
                    ]
                );

                if (!empty($item['media_url'])) {
                    PostMedia::updateOrCreate(
                        ['post_id' => $post->id],
                        [
                            'platform'         => 'instagram',
                            'user_id'          => $account->user_id,
                            'social_account_id'  => $account->id,
                            'media_id'         => $item['id'],
                            'media_url'        => $item['media_url'],
                            'media_type'       => strtolower($item['media_type'] ?? 'image'),
                        ]
                    );
                }

                $this->backfillMediaInsights($post, $account, $baseUrl);
                // Reuses the existing comment-backfill method verbatim
                // rather than duplicating its logic here.
                $this->fetchComments($post);
            } catch (\Throwable $e) {
                Log::warning('Failed to backfill an Instagram media item.', ['account_id' => $account->id, 'media_id' => $item['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 'impressions' was deprecated in favor of 'views' for Instagram
     * media insights starting with Graph API v22.0, and this codebase
     * runs a mix of API versions across different call sites - request
     * the metrics that remain valid across versions unconditionally, and
     * attempt 'impressions' separately so a deprecation-shaped 400
     * doesn't take down the whole insights fetch.
     */
    private function backfillMediaInsights(Post $post, SocialAccount $account, string $baseUrl): void
    {
        try {
            $response = $this->api->request(
                'get',
                $baseUrl . $post->post_id . '/insights',
                [],
                ['metric' => 'reach,saved', 'access_token' => $account->access_token]
            );

            $payload = [];

            if ($response->successful()) {
                $payload = $response->json()['data'] ?? [];
            } else {
                Log::warning('Instagram media insights (reach,saved) fetch failed.', ['post_id' => $post->id, 'error' => $response->json()['error']['message'] ?? $response->body()]);
            }

            try {
                $impressionsResponse = $this->api->request(
                    'get',
                    $baseUrl . $post->post_id . '/insights',
                    [],
                    ['metric' => 'impressions', 'access_token' => $account->access_token]
                );

                if ($impressionsResponse->successful()) {
                    $payload = array_merge($payload, $impressionsResponse->json()['data'] ?? []);
                }
                // Silently dropped on failure (eg. deprecated on this API
                // version) - reach/saved above already carried the fetch.
            } catch (\Throwable $e) {
                // Same - not fatal, impressions is a bonus metric here.
            }

            if (!empty($payload)) {
                $metrics = collect($payload)->keyBy('name');
                $reach = $metrics->get('reach')['values'][0]['value'] ?? null;
                $impressions = $metrics->get('impressions')['values'][0]['value'] ?? $metrics->get('views')['values'][0]['value'] ?? null;

                $post->update([
                    'reach'          => $reach ?? $post->reach,
                    'impressions'    => $impressions ?? $post->impressions,
                    'analytics_data' => $payload,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Instagram media insights fetch threw.', ['post_id' => $post->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Backfill a post's comments from Instagram's Graph API. Used when a post
     * has an engagement comment count but no imported PostComment rows yet
     * (e.g. the comment webhook hasn't captured them).
     */
    public function fetchComments(Post $post): void
    {
        $this->ensureValidToken($post);

        $endpoint = $this->resolveBaseUrl($post->socialAccount) . $post->post_id . '/comments';

        $response = $this->api->request('get', $endpoint, [], [
            'fields' => 'id,text,username,timestamp,like_count',
            'access_token' => $post->socialAccount->access_token,
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
                    'social_account_id' => $post->socialAccount?->id,
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
        $endpoint = $this->resolveBaseUrl($post->socialAccount) . $post->post_id;

        $response = $this->api->request(
            'delete',
            $endpoint . "?access_token={$post->socialAccount->access_token}",
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
        $this->ensureValidToken($chat->socialAccount);
        $endpoint = $this->resolveBaseUrl($chat->socialAccount) . $chat->comment_id;

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
        $account = Post::with('socialAccount')->where('post_id', $postId)->first();
        $this->ensureValidToken($account->socialAccount);

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

    /**
     * error.message alone is often a generic top-level string ("Invalid
     * parameter") - the actual reason (eg. a deprecated media_type value)
     * lives in error.error_user_msg / error_user_title, which Graph API
     * includes for exactly this kind of user-actionable rejection. Logging
     * the full body means the next "Invalid parameter"-class failure is
     * diagnosable from the log instead of needing to reproduce it.
     */
    private function errorResponse($model, $response)
    {
        $error = $response->json()['error'] ?? [];

        $message = collect([
            $error['error_user_title'] ?? null,
            $error['error_user_msg'] ?? null,
        ])->filter()->implode(' - ') ?: ($error['message'] ?? 'Unknown error');

        Log::warning('Instagram API error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        $model->status = 'failed';
        $model->error_message = $message;
        $model->save();

        return [
            'success' => false,
            'message' => $message,
        ];
    }
}
