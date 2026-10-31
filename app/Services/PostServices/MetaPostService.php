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
    /**
     * Everything this app handles from a Facebook Page, in ONE list: the
     * Page's POST /subscribed_apps call replaces its field list rather
     * than adding to it, so the posts side (feed) and the Messenger side
     * (messages, postbacks, receipts) must always subscribe together -
     * otherwise whichever runs last silently turns the other's events off.
     * All of them arrive on the app's one Page callback URL
     * (/api/comments/facebook -> FacebookCommentWebhookController).
     */
    public const PAGE_WEBHOOK_FIELDS = 'feed,messages,messaging_postbacks,message_deliveries,message_reads';

    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.facebook.base_url');
    }

    protected function ensureValidToken($post, bool $markFailed = true)
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
            return $this->errorResponse($post, $response, $markFailed);
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
            // 'media' is a nullable field (PostRequest: 'media' =>
            // ['nullable', 'array']) - a text-only post with no attached
            // file omits the key entirely rather than sending an empty
            // array, and PHP's "Undefined array key" warning gets
            // promoted to a thrown ErrorException in a real HTTP request
            // (confirmed live: this crashed every text-only Facebook post
            // submission, silently, since PostController::store()'s
            // catch block didn't log anything either - see its own fix).
            // uploadMediaToS3() already handles an empty array cleanly
            // (foreach over [] is a no-op).
            $uploadResult = $this->uploadMediaToS3($data['media'] ?? []);
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
        $owner = \App\Models\User::findOrFail($post->user_id);
        app(\App\Services\PackageAccessService::class)->authorize($owner, 'publishing');
        // Recheck pending legacy records too; the ledger prevents retry double charging.
        app(\App\Services\PackageUsageService::class)->reserve($owner, 'scheduled_published_posts', 1, 'post:'.$post->id);

        $account = $post->socialAccount;

        try {
            if (!$this->ensureValidToken($post)) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to refresh access token'
                ]);

                return ['success' => false, 'message' => 'Failed to refresh access token - reconnect Facebook in Connections.'];
            }

            // publishPostOnMeta() already persists the post's final state
            // itself on every single exit path (success at its own
            // $post->update(post_id/status='completed'), every failure
            // branch via errorResponse()/its own inline updates) - it
            // returns just ['success' => true] on success, with no
            // 'post_id' key. This redundant second update used to re-read
            // $result['post_id'], which never existed, crashing with
            // "Undefined array key" on EVERY successful publish - the
            // post had already been correctly saved as 'completed' with
            // its real post_id by publishPostOnMeta() one line earlier,
            // but the crash here made every successful publish look like
            // a failure in this command's own log/console output.
            // Confirmed live: a real Facebook post published successfully
            // (real post_id saved, status='completed') while this exact
            // line threw and made the command report "Post N failed."
            $result = $this->publishPostOnMeta($post, $account);

            if (! ($result['success'] ?? false)) {
                // publishPostOnMeta() saved the reason on the post - surface
                // it (the command used to print "unknown error").
                return ['success' => false, 'message' => $post->fresh()->error_message ?: ($result['message'] ?? $result['error'] ?? 'Facebook rejected the post.')];
            }

            return ['success' => true];
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
        // Not caught here - re-thrown to publishPost()'s single
        // handleTokenDecryptFailure() handler, which also covers the
        // ensureValidToken() decrypt site, so this failure mode only has
        // one place that marks the account invalid.
        //
        // (Two dd() calls used to sit in this and the next catch block -
        // either would halt PublishPosts.php's entire chunked loop stone
        // dead the moment one post hit this path, silently blocking every
        // post queued after it until someone noticed. Removed.)
        throw $e;
    } catch (\Throwable $e) {
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
    public function subscribeToWebhooks(SocialAccount $account): bool
    {
        try {
            $response = $this->api->request(
                'post',
                $this->baseUrl . $account->platform_account_id . '/subscribed_apps',
                [],
                ['subscribed_fields' => self::PAGE_WEBHOOK_FIELDS, 'access_token' => $account->access_token]
            );

            if ($response->successful() && ($response->json()['success'] ?? false)) {
                $account->update(['metadata' => array_merge($account->metadata ?? [], ['webhook_subscriptions' => true])]);

                return true;
            }

            Log::warning('Failed to subscribe Facebook Page to webhooks.', [
                'account_id' => $account->id,
                'error'      => $response->json()['error']['message'] ?? $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Facebook Page webhook subscription threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Current likes (all reactions), shares and comments for a published
     * post, straight from Facebook. Shares have to be read this way:
     * Facebook sends no webhook when someone shares a Page's post to
     * their own timeline (only when the Page itself shares). Feed posts
     * are "{page}_{post}" ids; video posts are stored as the bare video
     * id, which has no share count.
     */
    public function syncEngagement(Post $post): bool
    {
        $account = $post->socialAccount;
        if (! $post->post_id || ! $account?->access_token) {
            return false;
        }

        $isFeedPost = str_contains((string) $post->post_id, '_');
        $fields = $isFeedPost
            ? 'shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)'
            : 'likes.summary(total_count).limit(0),comments.summary(total_count).limit(0)';

        try {
            $response = $this->api->request('get', $this->baseUrl . $post->post_id, [], ['fields' => $fields, 'access_token' => $account->access_token]);
        } catch (\Throwable $e) {
            Log::info('Facebook engagement sync threw.', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::info('Facebook engagement sync failed.', ['post_id' => $post->id, 'error' => $response->json('error.message') ?? $response->body()]);

            return false;
        }

        $post->forceFill(array_filter([
            'likes'    => $response->json($isFeedPost ? 'reactions.summary.total_count' : 'likes.summary.total_count'),
            'shares'   => $isFeedPost ? (int) ($response->json('shares.count') ?? 0) : null,
            'comments' => $response->json('comments.summary.total_count'),
        ], fn ($v) => $v !== null) + $this->postInsights($post, $account, $isFeedPost) + [
            'platform_metadata' => array_merge($post->platform_metadata ?? [], ['engagement_synced_at' => now()->toIso8601String()]),
        ])->save();

        return true;
    }

    /**
     * Views, reach and clicks from the Insights API - Facebook sends no
     * webhook for any of them. Meta retired post_impressions* in Nov
     * 2025; post_media_view ("Views") is its replacement, so it fills
     * both views and impressions (dashboards sum impressions). There is
     * no Facebook metric for saves. Only the columns Facebook returned
     * are included, so a Page too small for Insights keeps what it had.
     */
    private function postInsights(Post $post, SocialAccount $account, bool $isFeedPost): array
    {
        if (! $isFeedPost) {
            // Video posts are stored as the bare video id.
            $plays = $this->insightValues($post->post_id . '/video_insights', ['total_video_views'], $account)['total_video_views'] ?? null;

            return $plays === null ? [] : ['views' => $plays, 'impressions' => $plays];
        }

        $values = $this->insightValues($post->post_id . '/insights', ['post_media_view', 'post_total_media_view_unique', 'post_clicks'], $account);
        $isVideo = $post->relationLoaded('media') ? $post->media->contains('media_type', 'video') : $post->media()->where('media_type', 'video')->exists();
        $plays = $isVideo ? ($this->insightValues($post->post_id . '/insights', ['post_video_views'], $account)['post_video_views'] ?? null) : null;

        return array_filter([
            'views'       => $plays ?? $values['post_media_view'] ?? null,
            'impressions' => $values['post_media_view'] ?? null,
            'reach'       => $values['post_total_media_view_unique'] ?? null,
            'clicks'      => $values['post_clicks'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /**
     * metric => lifetime value. Asked together first; if Facebook rejects
     * the batch (one metric unavailable for this post rejects them all),
     * each metric is asked on its own so the others still come through.
     */
    private function insightValues(string $path, array $metrics, SocialAccount $account): array
    {
        $fetch = function (array $names) use ($path, $account) {
            try {
                $response = $this->api->request('get', $this->baseUrl . $path, [], [
                    'metric' => implode(',', $names), 'period' => 'lifetime', 'access_token' => $account->access_token,
                ]);
            } catch (\Throwable $e) {
                return null;
            }
            if (! $response->successful()) {
                Log::info('Facebook insights request failed.', ['path' => $path, 'metrics' => $names, 'error' => $response->json('error.message')]);

                return null;
            }

            return collect($response->json('data') ?? [])->mapWithKeys(fn ($row) => [
                $row['name'] => is_numeric($value = $row['values'][0]['value'] ?? null) ? (int) $value : null,
            ])->filter(fn ($v) => $v !== null)->all();
        };

        $values = $fetch($metrics);
        if ($values === null && count($metrics) > 1) {
            $values = [];
            foreach ($metrics as $metric) {
                $values += $fetch([$metric]) ?? [];
            }
        }

        return $values ?? [];
    }

    /**
     * What Facebook has this Page subscribed to for this app (GET
     * /{page}/subscribed_apps): ['fields' => [...]] when subscribed,
     * ['fields' => []] when the app isn't subscribed at all, or
     * ['error' => ...] when the call fails.
     */
    public function pageSubscription(SocialAccount $account): array
    {
        $response = $this->api->request('get', $this->baseUrl . $account->platform_account_id . '/subscribed_apps', [], ['access_token' => $account->access_token]);

        if (! $response->successful()) {
            return ['error' => $response->json('error.message') ?? $response->body()];
        }

        $appId = (string) adminSetting('posts.facebook.client_id');
        $apps = collect($response->json('data') ?? []);
        $ours = $apps->first(fn ($app) => (string) ($app['id'] ?? '') === $appId) ?? ($apps->count() === 1 ? $apps->first() : null);

        return ['fields' => $ours['subscribed_fields'] ?? [], 'app' => $ours['name'] ?? null];
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
        // post_impressions / post_engaged_users were retired by Meta -
        // syncEngagement() reads the current Views/reach/clicks metrics.
        $post->setRelation('socialAccount', $account);
        $this->syncEngagement($post);
    }

    private function backfillPostComments(Post $post, SocialAccount $account): void
    {
        try {
            $response = $this->api->request(
                'get',
                $this->baseUrl . $post->post_id . '/comments',
                [],
                ['fields' => 'id,message,from{id,name,picture.type(large)},created_time,like_count', 'limit' => 25, 'access_token' => $account->access_token]
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
                        'user_avatar_url' => $comment['from']['picture']['data']['url'] ?? null,
                        'likes'           => $comment['like_count'] ?? 0,
                        'posted_at'       => $comment['created_time'] ?? now(),
                        'sender_type'     => (string) ($comment['from']['id'] ?? '') === (string) $account->platform_account_id ? 'support' : 'customer',
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
        $this->ensureValidToken($post, false);
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

    /**
     * Edit the text of a published Page post (POST /{page-post-id} message,
     * developers.facebook.com/docs/pages-api/posts). Meta only lets an app
     * edit posts it made, and the attached media can't be replaced.
     */
    public function editPublished(Post $post, array $data): array
    {
        $this->ensureValidToken($post, false);
        $response = $this->api->request('post', $this->baseUrl . $post->post_id, [], [
            'message' => (string) ($data['content'] ?? ''),
            'access_token' => $post->socialAccount->fresh()->access_token,
        ], 'form');

        if (!$response->successful()) {
            $error = $response->json('error') ?? [];

            return ['success' => false, 'status' => $response->status(), 'message' => $error['error_user_msg'] ?? $error['message'] ?? 'Facebook didn’t update the post.'];
        }

        return ['success' => true, 'status' => $response->status()];
    }

    /**
     * Publish a comment as the Page: $type 'comment' = a new top-level
     * comment on $toReply (a Post), 'reply' = a reply to $toReply (a
     * PostComment). A failure never touches the post's own status - the
     * post is still published, only the comment didn't go out.
     */
    public function publishComment($data, $toReply, $type)
    {
        $post = $type === 'reply' ? $toReply->post : $toReply;
        $account = $toReply->socialAccount ?? $post?->socialAccount;
        $targetId = $type === 'reply' ? $toReply->comment_id : $post?->post_id;

        if (! $targetId) {
            return ['success' => false, 'message' => $type === 'reply'
                ? 'This comment has no Facebook id, so it can\'t be replied to.'
                : 'This post isn\'t on Facebook yet (it was not published), so it can\'t be commented on.'];
        }

        if (! $account || ! $account->access_token) {
            return ['success' => false, 'message' => 'The Facebook Page connection needs to be reconnected before you can comment.'];
        }

        $token = $this->ensureValidToken($post, false);
        if (is_array($token)) {
            return $token;
        }

        $response = $this->api->request(
            'post',
            $this->baseUrl . $targetId . '/comments?access_token=' . $account->access_token,
            [],
            ['message' => $data['body']],
            'form'
        );

        if (! $response->successful() || empty($response->json('id'))) {
            return $this->errorResponse($toReply, $response, false);
        }

        return $this->storeComment($toReply, $type, $data, $response->json('id'));
    }

    /** Save the comment/reply just published so it shows at once (the webhook may echo it later). */
    private function storeComment($target, string $type, $data, $commentId)
    {
        $isReply = $type === 'reply';
        $post = $isReply ? $target->post : $target;
        $account = $target->socialAccount ?? $post?->socialAccount;

        $comment = PostComment::firstOrCreate(['comment_id' => $commentId, 'platform' => 'facebook'], [
            'content'           => $data['body'] ?? '',
            'sender_type'       => 'support',
            'parent_comment_id' => $isReply ? $target->id : null,
            'user_id'           => Auth::id(),
            'post_id'           => $post?->id,
            'is_read'           => 1,
            'is_reply'          => $isReply,
            'user_name'         => $account?->name ?: (Auth::user()->name ?? 'Page'),
            'user_avatar_url'   => $account?->avatar_url,
            'social_account_id' => $account?->id,
            'status'            => 'approved',
            'posted_at'         => now(),
        ]);

        return [
            'message' => $isReply ? 'Reply published.' : 'Comment published.',
            'success' => true,
            'data'    => $comment,
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
        $secret = (string) adminSetting('posts.facebook.client_secret');

        // No secret configured: never accept a body signed with an empty key.
        if ($secret === '' || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, substr($signatureHeader, 7));
    }

    /**
     * Post-comment webhook delivery for both Facebook Pages (object: "page")
     * and Instagram professional accounts (object: "instagram") - separate
     * from the Messaging module's DM webhook, which only ever sees
     * entry[].messaging[] and never entry[].changes[].
     */
    /** @return int comment changes handled for a connected account */
    public function handleCommentWebhook(array $payload, string $platform): int
    {
        $handled = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            $externalAccountId = $entry['id'] ?? null;

            if (!$externalAccountId) {
                continue;
            }

            $accounts = $platform === 'instagram'
                ? $this->instagramWebhookAccounts((string) $externalAccountId)
                : SocialAccount::where('platform', $platform)->where('platform_account_id', $externalAccountId)->take(1)->get();

            if ($accounts->isEmpty()) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $this->processCommentChange($change, $platform, $this->instagramAccountForChange($accounts, $change));
                $handled++;
            }
        }

        return $handled;
    }

    /**
     * entry[].id is the Instagram professional account ID. A Page-linked
     * connect stores that as platform_account_id; an Instagram Login
     * connect stores the app-scoped id from /me and keeps the
     * professional one in metadata.settings.ig_user_id - so match both,
     * or Instagram Login accounts never receive a comment.
     */
    private function instagramWebhookAccounts(string $igUserId)
    {
        return SocialAccount::where('platform', 'instagram')
            ->where(fn ($q) => $q->where('platform_account_id', $igUserId)->orWhere('metadata->settings->ig_user_id', $igUserId))
            ->orderBy('id')
            ->get();
    }

    /**
     * The same Instagram account can be connected more than once (Page-linked
     * and Instagram Login, or by several users): the comment belongs to the
     * one whose Post has the commented media, else the first.
     */
    private function instagramAccountForChange($accounts, array $change): SocialAccount
    {
        $mediaId = $change['value']['media']['id'] ?? null;
        $owner = $mediaId && $accounts->count() > 1
            ? Post::whereIn('social_account_id', $accounts->pluck('id'))->where('post_id', $mediaId)->latest('id')->value('social_account_id')
            : null;

        return $accounts->firstWhere('id', $owner) ?? $accounts->first();
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

        $verb = $isInstagram ? 'add' : ($value['verb'] ?? null);

        if ($isInstagram) {
            if (($change['field'] ?? null) !== 'comments') {
                return;
            }
        } elseif (($change['field'] ?? null) !== 'feed') {
            return;
        } elseif (in_array($value['item'] ?? null, ['reaction', 'like', 'share'], true)) {
            $this->processEngagementChange($value, $platform, $socialAccount);

            return;
        } elseif (($value['item'] ?? null) !== 'comment' || !in_array($verb, ['add', 'edited', 'remove'], true)) {
            return;
        }

        $commentId = $value['comment_id'] ?? $value['id'] ?? null;
        $commentText = $value['message'] ?? $value['text'] ?? '';

        // Deleted on Facebook: remove it (and replies to it) here too.
        if ($verb === 'remove') {
            if ($commentId && ($existing = PostComment::where('platform', $platform)->where('comment_id', $commentId)->first())) {
                $removed = 1 + PostComment::where('parent_comment_id', $existing->id)->delete();
                $existing->delete();
                $this->bumpPostCounter($existing->post_id ? Post::find($existing->post_id) : null, 'comments', -$removed);
            }

            return;
        }

        // Edited on Facebook: update the text of a comment we already have.
        if ($verb === 'edited') {
            if ($commentId && $commentText !== '') {
                PostComment::where('platform', $platform)->where('comment_id', $commentId)->update(['content' => $commentText]);
            }

            return;
        }

        if (!$commentId || $commentText === '') {
            return;
        }

        $nativePostId = $isInstagram ? ($value['media']['id'] ?? null) : ($value['post_id'] ?? null);

        $post = $nativePostId
            ? ($isInstagram
                ? Post::where('social_account_id', $socialAccount->id)->where('post_id', $nativePostId)->first()
                : $this->findWebhookPost($socialAccount, $nativePostId))
            : null;

        // The Page (or IG account) replying from Facebook/Instagram itself
        // is the business, not a customer.
        $isOwn = (string) ($value['from']['id'] ?? '') === (string) $socialAccount->platform_account_id;

        $parentId = $value['parent_id'] ?? null;
        $parentComment = $parentId
            ? PostComment::where('platform', $platform)->where('comment_id', $parentId)->first()
            : null;

        // Only columns that actually exist on post_comments - the model's
        // $fillable lists several (user_platform_id, is_read, ...) that
        // were never added as real columns, so writing them throws a
        // "Column not found" SQL error rather than being silently dropped.
        // Comments this app posted itself (publishComment()) echo back here
        // with the same comment_id: updateOrCreate refreshes that row
        // rather than adding a duplicate, and from.id marks it as ours.
        $comment = PostComment::updateOrCreate(
            ['platform' => $platform, 'comment_id' => $commentId],
            [
                'content'           => $commentText,
                'sender_type'       => $isOwn ? 'support' : 'customer',
                // The account owner even when the post wasn't made through
                // the app, so notifications and counts include it.
                'user_id'           => $post?->user_id ?? $socialAccount->user_id,
                'post_id'           => $post?->id,
                'social_account_id' => $socialAccount->id,
                'parent_comment_id' => $parentComment?->id,
                'is_reply'          => (bool) $parentComment,
                'status'            => 'approved',
            ]
            // Only when Facebook sent a name - a retry without one must not
            // wipe a name we already have (eg. from commenterProfile()).
            + (($fromName = $value['from']['username'] ?? $value['from']['name'] ?? null) ? ['user_name' => $fromName] : [])
            + (isset($value['created_time']) ? ['posted_at' => \Carbon\Carbon::createFromTimestamp((int) $value['created_time'])] : [])
        );

        if ($comment->wasRecentlyCreated) {
            $comment->update(array_filter([
                'posted_at' => $comment->posted_at ? null : now(),
                'user_name' => $comment->user_name ? null : 'Anonymous',
            ]));
            $this->bumpPostCounter($post, 'comments', 1);
        }

        // The webhook only carries the commenter's id and name - look the
        // picture up once (like Messenger's fetchUserProfile()), not on
        // every edit or Meta retry.
        if (!$isInstagram && !$comment->user_avatar_url) {
            $profile = $isOwn ? ['avatar' => $socialAccount->avatar_url] : $this->commenterProfile($commentId, $socialAccount);
            $comment->update(array_filter([
                'user_avatar_url' => $profile['avatar'] ?? null,
                'user_name'       => $comment->user_name === 'Anonymous' ? ($profile['name'] ?? null) : null,
            ]));
        }
    }

    /**
     * Who wrote a Facebook comment: ['name' => ..., 'avatar' => ...] from
     * GET /{comment-id}?fields=from{name,picture} with the Page token.
     * Best-effort - [] when Facebook doesn't share it (eg. the commenter's
     * privacy settings, or pages_read_user_content not granted), and the
     * preview falls back to initials.
     */
    public function commenterProfile(string $commentId, SocialAccount $account): array
    {
        try {
            $response = $this->api->request('get', $this->baseUrl . $commentId, [], [
                'fields' => 'from{id,name,picture.type(large)}',
                'access_token' => $account->access_token,
            ]);
            if (!$response->successful()) {
                return [];
            }

            return array_filter([
                'name'   => $response->json('from.name'),
                'avatar' => $response->json('from.picture.data.url'),
            ]);
        } catch (\Throwable $e) {
            Log::info('Facebook commenter profile lookup failed.', ['comment_id' => $commentId, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Likes/reactions and shares from the Page's feed: keep the post's
     * (or, for a reaction on a comment, the comment's) counters current
     * between analytics syncs, which overwrite them with Facebook's own
     * totals. A changed reaction type ("edited") doesn't change the count.
     */
    private function processEngagementChange(array $value, string $platform, SocialAccount $socialAccount): void
    {
        $delta = match ($value['verb'] ?? null) {
            'add' => 1,
            'remove' => -1,
            default => 0,
        };
        if ($delta === 0) {
            return;
        }

        // A reaction on a comment carries that comment's id.
        if (($value['item'] ?? null) !== 'share' && !empty($value['comment_id'])) {
            $comment = PostComment::where('platform', $platform)->where('comment_id', $value['comment_id'])->first();
            if ($comment) {
                $comment->update(['likes' => max(0, (int) $comment->likes + $delta)]);
            }

            return;
        }

        $nativePostId = $value['post_id'] ?? null;
        $post = $nativePostId ? $this->findWebhookPost($socialAccount, $nativePostId) : null;

        $this->bumpPostCounter($post, ($value['item'] ?? null) === 'share' ? 'shares' : 'likes', $delta);
    }

    /**
     * The app's Post for a Facebook webhook's post_id ("{page}_{story}").
     *
     * Feed/photo posts are saved with that same id. Video posts are not:
     * /{page}/videos returns the VIDEO id, a different number from the
     * id of the feed post the video appears in - which is what comment
     * and reaction webhooks send. So when nothing matches, Facebook is
     * asked which video the post carries (attachments.target.id), and the
     * feed post id is remembered on the Post (platform_metadata) so later
     * events match without asking again. Searches every connected account
     * for the Page, not just the first one.
     */
    public function findWebhookPost(SocialAccount $account, string $nativePostId): ?Post
    {
        $accountIds = SocialAccount::where('platform', 'facebook')
            ->where('platform_account_id', $account->platform_account_id)
            ->pluck('id');
        $posts = fn () => Post::whereIn('social_account_id', $accountIds)->where('platform', 'facebook');

        $post = $posts()->where(fn ($q) => $q
            ->whereIn('post_id', array_unique([$nativePostId, \Illuminate\Support\Str::afterLast($nativePostId, '_')]))
            ->orWhere('platform_metadata->feed_post_id', $nativePostId))
            ->latest('id')->first();

        if ($post || ! $account->access_token) {
            return $post;
        }

        try {
            $response = $this->api->request('get', $this->baseUrl . $nativePostId, [], [
                'fields' => 'attachments{target{id},media_type}',
                'access_token' => $account->access_token,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        $targetIds = collect($response->successful() ? ($response->json('attachments.data') ?? []) : [])
            ->pluck('target.id')->filter()->map(fn ($id) => (string) $id)->all();
        if (! $targetIds) {
            return null;
        }

        $post = $posts()->whereIn('post_id', $targetIds)->latest('id')->first();
        if ($post) {
            $post->forceFill(['platform_metadata' => array_merge($post->platform_metadata ?? [], ['feed_post_id' => $nativePostId])])->save();
        }

        return $post;
    }

    private function bumpPostCounter(?Post $post, string $column, int $delta): void
    {
        if ($post && $delta !== 0) {
            $post->forceFill([$column => max(0, (int) $post->{$column} + $delta)])->save();
        }
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

    private function errorResponse($model, $response, bool $markFailed = true)
    {
        if ($markFailed) {
            $model->status = 'failed';
            $model->error_message = $response->json()['error']['message'] ?? 'Unknown error';
            $model->save();
        }

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Unknown error'
        ];
    }
}
