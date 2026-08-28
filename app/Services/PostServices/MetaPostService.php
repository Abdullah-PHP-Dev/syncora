<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostComment;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use getID3;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\Messaging\Conversation;

class MetaPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.facebook.base_url');
    }

    protected function ensureValidToken($post)
    {
        $account = $post->socialAccount;
        if (
            !empty($account->expires_at)
            && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        $clientId = adminSetting('posts.facebook.client_id');
        $clientSecret = adminSetting('posts.facebook.client_secret');
        $endpoint = 'https://graph.facebook.com/v23.0/oauth/access_token';
       
        $payload = [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => (string) $clientId,
            'client_secret'     => $clientSecret,
            'fb_exchange_token' => $account->access_token,
        ];

        $response = $this->api->request('get', $endpoint, [], $payload);

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token'       => $tokenData['access_token'],
            'refresh_token'      => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_at'    => now()->addSeconds($tokenData['expires_in'] ?? 3600),
        ]);

        $account->refresh();
        return true;
    }



    /**
     * Store post to multiple Facebook pages using queue
     */
    public function store($data, $pages)
    {
        $results = [];
        $errors = [];
        $successCount = 0;
        $uploadResult = [];

        if (isset($data['uploaded_media'])) {
            // Already uploaded once by PostController::quickStore() and
            // shared across every platform in this submission - see
            // uploadQuickPostMedia()'s docblock.
            $uploadResult = ['success' => true, 'media' => $data['uploaded_media']];
        } elseif (isset($data['ai_image_url'])) {
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

        // Create post record and dispatch jobs for each page
        foreach ($pages as $page) {
            try {
                // Create post record with status 'pending'
                $post = $this->post::create([
                    'title' => $data['title'] ?? Auth::user()->name,
                    'post_id' => null,
                    'platform' => 'facebook',
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
                            'platform' => 'facebook',
                            'post_id' => $post->id,
                            'visibility' => 'public',
                            'user_id' => Auth::user()->id,
                            'social_account_id' => $page->id,
                            'post_category_id' => $data['category_id'],
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
     * Store post to multiple Facebook pages using queue
     */

     public function publishPost($post)
     {
        $account = $post->socialAccount;

        try {
            if (!$this->ensureValidToken($post)) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to refresh access token'
                ]);

                return ['success' => false];
            }

            $result = $this->publishPostOnMeta($post, $account);

            if ($result['success']) {
                $post->update([
                    'post_id' => $result['post_id'],
                    'status' => 'completed'
                ]);
                return ['success' => true];
            } else {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? $result['error'] ?? 'Unknown error'
                ]);
                return ['success' => false];
            }
        } catch (DecryptException $e) {
            // Catches decrypt failures from BOTH ensureValidToken() (reads
            // access_token to refresh it) and publishPostOnMeta() (reads it
            // to actually post) - one handler instead of duplicating this
            // in both places. Always means APP_KEY changed since this
            // account's token was encrypted, or the row is corrupted -
            // there is no way to recover the token, only reconnecting the
            // account fixes it, so mark it invalid rather than let every
            // future run silently retry a token that can never decrypt.
            return $this->handleTokenDecryptFailure($post, $account, $e);
        }
     }

    private function handleTokenDecryptFailure($post, $account, DecryptException $e): array
    {
        Log::warning('Facebook access token failed to decrypt - marking account invalid.', [
            'account_id' => $account?->id,
            'post_id'    => $post->id,
            'error'      => $e->getMessage(),
        ]);

        $account?->update(['is_token_valid' => false]);

        $message = "This account's access token could not be decrypted (the encryption key may have changed since it was connected). Please reconnect this account.";

        $post->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return ['success' => false, 'error' => $message];
    }

    /**
     * Publish to a single Facebook page
     */
protected function publishPostOnMeta($post, $account)
{
    try {
        // 1. Safely retrieve the decrypted access token
        $accessToken = $account->access_token;
    
        $accountId = $account->platform_account_id;

        if (empty($accessToken)) {
            throw new \Exception("Access token is missing or empty for account ID {$account->id}.");
        }

        $endpoint = $this->baseUrl . $accountId . "/feed?access_token={$accessToken}";
        $payload = ['message' => $post->content];

        // 2. Handle Media Uploads if present
        if (!empty($post->media)) {
            $media = $this->uploadMediaToMeta($post, $accessToken);
           
            if (!($media['success'] ?? false)) {
                $post->status = 'failed';
                $post->error_message = $media['message'] ?? $media['error'] ?? 'Facebook media publish faced an error.';  
                $post->save();

                return $media;
            }

            // If video published directly during upload step
            if (isset($media['direct_published']) && $media['direct_published']) {
                $post->update([
                    'post_id' => $media['id'],
                    'error_message' => '',  
                    'status' => 'completed'
                ]);

                return ['success' => true];
            }
           
            $payload['attached_media'] = $media['media'];
        }
        
        // 3. Send API Request
        $response = $this->api->request('post', $endpoint, ['Content-Type' => 'application/json'], $payload, 'json');
       
        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }
        
        $post->update([
            'post_id' => $response['id'],
            'error_message' => '',
            'status' => 'completed'
        ]);

        return ['success' => true];

    } catch (DecryptException $e) {
            dd($e->getMessage(), $account);
        // Not caught here - re-thrown to publishPost()'s single
        // handleTokenDecryptFailure() handler, which also covers the
        // ensureValidToken() decrypt site, so this failure mode only has
        // one place that marks the account invalid.
        throw $e;
    } catch (\Throwable $e) {
              dd($e->getMessage(), $account);
        // Catches all other runtime errors / network exceptions
        $post->update([
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

    private function uploadMediaToMeta($post, $accessToken) {
        $attachedMedia = [];
        $payload = [];
     
        foreach ($post->media as $key => $each) {
            // If it's an image, we upload it as unpublished to attach later
            if ($each->media_type == 'image') {
                $payload['published'] = false;
                $endpoint = $this->baseUrl . $post->socialAccount->platform_account_id . "/photos";
                $payload['url'] = $each->media_url;
                
                $response = $this->api->request('post', $endpoint . "?access_token={$accessToken}", ['Content-Type' => 'application/json'], $payload, 'json');
    
                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }
    
                $attachedMedia[] = [
                    'media_fbid' => $response->json()['id']
                ];
            } else {
                // CRITICAL: For videos, you typically must publish them directly to the /videos endpoint
                // rather than attaching them via `attached_media` to the /feed endpoint.
                $endpoint = $this->baseUrl . $post->socialAccount->platform_account_id . "/videos";
                $payload['file_url'] = $each->media_url;
                $payload['description'] = $post->content; // The video description becomes the post body
                
                $response = $this->api->request('post', $endpoint . "?access_token={$accessToken}", ['Content-Type' => 'application/json'], $payload, 'json');
    
                if (!$response->successful()) {
                    return $this->errorResponse($post, $response);
                }
                
                // Because /videos publishes immediately, we don't need to hit the /feed endpoint later
                return [
                    'success' => true,
                    'direct_published' => true,
                    'id' => $response->json()['id']
                ];
            }  
        }
    
        return [
            'success' => true,
            'direct_published' => false,
            'media' => $attachedMedia,
        ];
    }

    /**
     * Fetches current page-level stats (Page Likes/followers) plus a
     * richer raw insights snapshot, called once right after a Page is
     * connected. The two Graph API calls are independent - a Page too
     * new/small to have Insights data (Meta gates several Insights
     * metrics behind a minimum-audience threshold) must not prevent the
     * plain fan_count/followers_count fields, which have no such gating,
     * from saving.
     */
    public function syncAccountStats(SocialAccount $account): void
    {
        $fieldsResponse = $this->api->request(
            'get',
            $this->baseUrl . $account->platform_account_id,
            [],
            ['fields' => 'fan_count,followers_count,talking_about_count', 'access_token' => $account->access_token]
        );

        if ($fieldsResponse->successful()) {
            $data = $fieldsResponse->json();
            $account->update([
                'likes_count'     => $data['fan_count'] ?? $account->likes_count,
                'followers_count' => $data['followers_count'] ?? $account->followers_count,
            ]);
        } else {
            Log::warning('Failed to fetch Facebook Page fields.', [
                'account_id' => $account->id,
                'error'      => $fieldsResponse->json()['error']['message'] ?? $fieldsResponse->body(),
            ]);
        }

        try {
            $insightsResponse = $this->api->request(
                'get',
                $this->baseUrl . $account->platform_account_id . '/insights',
                [],
                ['metric' => 'page_impressions,page_engaged_users', 'period' => 'day', 'access_token' => $account->access_token]
            );

            if ($insightsResponse->successful()) {
                $account->update(['metadata' => array_merge($account->metadata ?? [], [
                    'insights' => array_merge($account->metadata['insights'] ?? [], ['page' => $insightsResponse->json()['data'] ?? []]),
                ])]);
            } else {
                // Expected for small/new Pages - not a bug.
                Log::warning('Facebook Page insights fetch failed (likely too new/small for this metric).', [
                    'account_id' => $account->id,
                    'error'      => $insightsResponse->json()['error']['message'] ?? $insightsResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook Page insights fetch threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Registers this Page with the app's webhook subscription so Meta
     * actually starts delivering feed/comment events for it - the
     * App-Dashboard-level webhook config alone is not sufficient, Meta
     * requires this per-Page opt-in too (POST .../subscribed_apps).
     * 'feed' matches exactly what processCommentChange() expects for
     * Facebook comment events.
     */
    public function subscribeToWebhooks(SocialAccount $account): void
    {
        try {
            $response = $this->api->request(
                'post',
                $this->baseUrl . $account->platform_account_id . '/subscribed_apps',
                [],
                ['subscribed_fields' => 'feed', 'access_token' => $account->access_token]
            );

            if ($response->successful() && ($response->json()['success'] ?? false)) {
                $account->update(['metadata' => array_merge($account->metadata ?? [], ['webhook_subscriptions' => true])]);
            } else {
                Log::warning('Failed to subscribe Facebook Page to webhooks.', [
                    'account_id' => $account->id,
                    'error'      => $response->json()['error']['message'] ?? $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook Page webhook subscription threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * On connect, backfills the Page's most recent posts (default 4)
     * along with each post's insights and comments, so a newly connected
     * account isn't empty until the customer publishes something new
     * through this app. Each post/insights/comments call is
     * independently failure-tolerant - one bad post must not abort the
     * rest of the batch.
     */
    public function backfillRecentPosts(SocialAccount $account, int $limit = 4): void
    {
        $postsResponse = $this->api->request(
            'get',
            $this->baseUrl . $account->platform_account_id . '/posts',
            [],
            ['fields' => 'id,message,created_time,full_picture,shares,likes.summary(true)', 'limit' => $limit, 'access_token' => $account->access_token]
        );

        if (!$postsResponse->successful()) {
            Log::warning('Failed to fetch Facebook Page posts for backfill.', [
                'account_id' => $account->id,
                'error'      => $postsResponse->json()['error']['message'] ?? $postsResponse->body(),
            ]);
            return;
        }

        foreach ($postsResponse->json()['data'] ?? [] as $item) {
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
                        'platform' => 'facebook',
                        'user_id'  => $account->user_id,
                        'content'  => $item['message'] ?? '',
                        'likes'    => $item['likes']['summary']['total_count'] ?? 0,
                        'shares'   => $item['shares']['count'] ?? 0,
                        'status'   => 'completed',
                    ]
                );

                if (!empty($item['full_picture'])) {
                    PostMedia::updateOrCreate(
                        ['post_id' => $post->id],
                        [
                            'platform'         => 'facebook',
                            'user_id'          => $account->user_id,
                            'social_account_id'  => $account->id,
                            'media_url'        => $item['full_picture'],
                            'media_type'       => 'image',
                        ]
                    );
                }

                $this->backfillPostInsights($post, $account);
                $this->backfillPostComments($post, $account);
            } catch (\Throwable $e) {
                Log::warning('Failed to backfill a Facebook post.', ['account_id' => $account->id, 'post_id' => $item['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }
    }

    private function backfillPostInsights(Post $post, SocialAccount $account): void
    {
        try {
            $response = $this->api->request(
                'get',
                $this->baseUrl . $post->post_id . '/insights',
                [],
                ['metric' => 'post_impressions,post_engaged_users', 'period' => 'lifetime', 'access_token' => $account->access_token]
            );

            if (!$response->successful()) {
                Log::warning('Facebook post insights fetch failed.', ['post_id' => $post->id, 'error' => $response->json()['error']['message'] ?? $response->body()]);
                return;
            }

            $metrics = collect($response->json()['data'] ?? [])->keyBy('name');
            $impressions = $metrics->get('post_impressions')['values'][0]['value'] ?? null;

            $post->update([
                'impressions'    => $impressions ?? $post->impressions,
                'analytics_data' => $response->json()['data'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Facebook post insights fetch threw.', ['post_id' => $post->id, 'error' => $e->getMessage()]);
        }
    }

    private function backfillPostComments(Post $post, SocialAccount $account): void
    {
        try {
            $response = $this->api->request(
                'get',
                $this->baseUrl . $post->post_id . '/comments',
                [],
                ['fields' => 'id,message,from,created_time,like_count', 'limit' => 25, 'access_token' => $account->access_token]
            );

            if (!$response->successful()) {
                Log::warning('Facebook post comments backfill failed.', ['post_id' => $post->id, 'error' => $response->json()['error']['message'] ?? $response->body()]);
                return;
            }

            $comments = $response->json()['data'] ?? [];

            foreach ($comments as $comment) {
                PostComment::updateOrCreate(
                    ['comment_id' => $comment['id'], 'post_id' => $post->id],
                    [
                        'platform'        => 'facebook',
                        'content'         => $comment['message'] ?? '',
                        'user_name'       => $comment['from']['name'] ?? 'Facebook user',
                        'likes'           => $comment['like_count'] ?? 0,
                        'posted_at'       => $comment['created_time'] ?? now(),
                        'sender_type'     => 'customer',
                        'is_reply'        => false,
                        'social_account_id' => $account->id,
                    ]
                );
            }

            $post->update(['comments' => count($comments)]);
        } catch (\Throwable $e) {
            Log::warning('Facebook post comments backfill threw.', ['post_id' => $post->id, 'error' => $e->getMessage()]);
        }
    }

    public function getPosts($pageId, $pageToken)
    {
        $account = MediaAccount::wherePageIdAndPageToken($pageId, $pageToken)->first();
        $this->ensureValidToken($account);
        $endpoint = "https://graph.facebook.com/v25.0/{$pageId}/feed";

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

    public function destroy($post)
    {
        $this->ensureValidToken($post);
        $endpoint = $this->baseUrl . $post->post_id;

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

    public function updatePost($postId, $data, $token)
    {
        $account = SocialPost::with('mediaAccount')->wherePagePostId($postId)->first();
        $this->ensureValidToken($account);
        $url = '';
        $endpoint = '';
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
            $s3Path = "uploads/meta/media/{$fileName}";
            Storage::disk('r2')->put(
                $s3Path,
                file_get_contents($data['media']->getRealPath()),
                ['visibility' => 'public']
            );

            $url = Storage::disk('r2')->url($s3Path);

            if (isset($url)) {
                $endpoint = "https://graph.facebook.com/v25.0/{$postId}";
                $payload['url'] = $url;
            }
        } else if (isset($data['url'])) {
            $endpoint = "https://graph.facebook.com/v25.0/{$postId}";
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

    public function publishComment($data, $comment)
    {
        $this->ensureValidToken($comment->post);
        $endpoint =  $this->baseUrl . $comment->comment_id . "/comments";

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

    private function storeComment($comment, $data, $commentId)
    {
        $comment = PostComment::create([
            'content'            => $data['body'] ?? '',
            'sender_type'     => 'support',
            'platform'        => 'facebook',
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
     * GET verification handshake Meta performs when the comments webhook
     * subscription is configured (and periodically re-verifies).
     */
    public function verifyWebhook(Request $request): ?string
    {
        $expectedToken = adminSetting('posts.facebook.webhook_verify_token', 'socialeaz-98897');

        if (
            $request->query('hub_mode') === 'subscribe'
            && hash_equals($expectedToken, (string) $request->query('hub_verify_token'))
        ) {
            return (string) $request->query('hub_challenge');
        }

        return null;
    }

    /**
     * Confirms an inbound webhook POST body genuinely came from Meta, via
     * the X-Hub-Signature-256 header (HMAC-SHA256 over the raw body using
     * the App Secret) - without this, anyone who finds the webhook URL
     * could inject fake comments into the inbox.
     */
    public function verifySignature(Request $request): bool
    {
        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), adminSetting('posts.facebook.client_secret'));

        return hash_equals($expected, substr($signatureHeader, 7));
    }

    /**
     * Post-comment webhook delivery for both Facebook Pages (object: "page")
     * and Instagram professional accounts (object: "instagram") - separate
     * from the Messaging module's DM webhook, which only ever sees
     * entry[].messaging[] and never entry[].changes[].
     */
    public function handleCommentWebhook(array $payload, string $platform): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            $externalAccountId = $entry['id'] ?? null;

            if (!$externalAccountId) {
                continue;
            }

            $socialAccount = SocialAccount::where('platform', $platform)
                ->where('platform_account_id', $externalAccountId)
                ->first();

            if (!$socialAccount) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $this->processCommentChange($change, $platform, $socialAccount);
            }
        }
    }

    /**
     * Facebook Page comments arrive on the 'feed' field alongside likes and
     * post edits - only item=comment + verb=add is an actual new comment.
     * Instagram has no such wrapper: every 'comments' field delivery IS a
     * new comment, so item/verb simply don't apply there.
     */
    private function processCommentChange(array $change, string $platform, SocialAccount $socialAccount): void
    {
        $value = $change['value'] ?? [];
        $isInstagram = $platform === 'instagram';

        if ($isInstagram) {
            if (($change['field'] ?? null) !== 'comments') {
                return;
            }
        } elseif (($change['field'] ?? null) !== 'feed' || ($value['item'] ?? null) !== 'comment' || ($value['verb'] ?? null) !== 'add') {
            return;
        }

        $commentId = $value['comment_id'] ?? $value['id'] ?? null;
        $commentText = $value['message'] ?? $value['text'] ?? '';

        if (!$commentId || $commentText === '') {
            return;
        }

        // Comments this app itself posted via publishComment() echo back
        // through this same webhook - the ' --. ' marker it appends lets us
        // recognize and skip our own replies instead of re-importing them
        // as new customer comments.
        if (str_contains($commentText, '--.')) {
            return;
        }

        $nativePostId = $isInstagram ? ($value['media']['id'] ?? null) : ($value['post_id'] ?? null);

        $post = $nativePostId
            ? Post::where('social_account_id', $socialAccount->id)->where('post_id', $nativePostId)->first()
            : null;

        $parentId = $value['parent_id'] ?? null;
        $parentComment = $parentId
            ? PostComment::where('platform', $platform)->where('comment_id', $parentId)->first()
            : null;

        // Only columns that actually exist on post_comments - the model's
        // $fillable lists several (user_platform_id, is_read, ...) that
        // were never added as real columns, so writing them throws a
        // "Column not found" SQL error rather than being silently dropped.
        PostComment::updateOrCreate(
            ['platform' => $platform, 'comment_id' => $commentId],
            [
                'content'           => $commentText,
                'sender_type'       => 'customer',
                'user_id'           => $post?->user_id,
                'user_name'         => $value['from']['username'] ?? $value['from']['name'] ?? 'Anonymous',
                'post_id'           => $post?->id,
                'social_account_id' => $socialAccount->id,
                'parent_comment_id' => $parentComment?->id,
                'is_reply'          => (bool) $parentComment,
            ]
        );
    }

    public function destroyComment($chat)
    {
        $this->ensureValidToken($chat->mediaAccount);
        $endpoint = $this->baseUrl . $chat->comment_id;

        $response = $this->api->request(
            'delete',
            $endpoint . "?access_token={$chat->mediaAccount->access_token}",
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
