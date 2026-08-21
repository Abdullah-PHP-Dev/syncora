<?php

namespace App\Services\PostServices;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Models\PostAccount;
use App\Models\PostMedia;
use getID3;
use App\Models\PostComment;
use App\Jobs\Posts\ResolveTiktokPublishStatus;

class TiktokPostService
{
    protected $api, $post, $media, $baseUrl;

    /**
     * Why a token error is stashed instead of returned: ensureValidToken()
     * has to answer a plain bool (its callers branch on it), but the caller
     * still needs TikTok's actual reason to surface on the post.
     */
    protected ?string $lastTokenError = null;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->post = $post;
        $this->media = $media;
        $this->baseUrl = 'https://open.tiktokapis.com/v2';
    }
    /**
     * Ensure valid access token by refreshing if needed
     */
    protected function ensureValidToken($subject): bool
    {
        $this->lastTokenError = null;

        // Accepts a Post or a PostAccount. getComments() already called
        // this with an account, which then ran `$account->postAccount` -
        // always null on a PostAccount - so every comment fetch died on a
        // null read before reaching TikTok.
        $account = $subject instanceof PostAccount
            ? $subject
            : ($subject->postAccount ?? null);

        if (!$account) {
            $this->lastTokenError = 'No TikTok account is linked to this record.';
            return false;
        }

        // Token still valid
        if (
            !empty($account->expires_in)
            && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        if (empty($account->refresh_token)) {
            $this->lastTokenError = 'The TikTok access token has expired and no refresh token is stored - reconnect the account.';
            return false;
        }

        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token/", [
            'client_key'    => adminSetting('posts.tiktok.client_id'),
            'client_secret' => adminSetting('posts.tiktok.client_secret'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        $tokenData = $response->json() ?? [];

        // A failed refresh comes back as HTTP 200 with an `error` key in
        // the body, so the status alone doesn't tell you anything. The
        // previous version returned errorResponse()'s array here, which is
        // truthy - so `if (!$this->ensureValidToken(...))` never fired and
        // publishing carried straight on with a dead token.
        if (!$response->successful() || empty($tokenData['access_token'])) {
            $this->lastTokenError = $tokenData['error_description']
                ?? $tokenData['error']
                ?? 'Failed to refresh the TikTok access token.';

            return false;
        }

        $account->update([
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_in'    => now()->addSeconds($tokenData['expires_in'] ?? 86400),
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
        $mediaExtension = null;

        if (isset($data['uploaded_media'])) {
            // Already uploaded once by PostController::quickStore() and
            // shared across every platform in this submission - see
            // uploadQuickPostMedia()'s docblock.
            $uploadResult = ['success' => true, 'media' => $data['uploaded_media']];
        } elseif (!empty($data['ai_image_url'])) {
            // The AI branch used to set $mediaExtension and nothing else,
            // so the media loop below hit an undefined $uploadResult and
            // every AI-generated TikTok post died before it was saved.
            // Normalised into the same shape uploadMediaToS3() returns.
            $mediaExtension = strtolower(pathinfo(
                parse_url($data['ai_image_url'], PHP_URL_PATH),
                PATHINFO_EXTENSION
            ));

            $uploadResult = [
                'success' => true,
                'media'   => [[
                    'url'              => $data['ai_image_url'],
                    'media_type'       => in_array($mediaExtension, ['mp4', 'mov', 'webm']) ? 'video' : 'image',
                    'file_name'        => basename(parse_url($data['ai_image_url'], PHP_URL_PATH)),
                    'file_size'        => null,
                    'width'            => null,
                    'height'           => null,
                    'duration_seconds' => null,
                    'thumbnail_url'    => null,
                    'alt_text'         => $data['title'] ?? null,
                ]],
            ];
        } else {
            $uploadResult = $this->uploadMediaToS3($data['media']);

            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'message' => $uploadResult['message']
                ];
            }
        }

        // Remove the uploaded file from data to avoid serialization issues
        $jobData = $data;
        unset($jobData['media']);

        // Loop through each page and create container
        foreach ($pages as $page) {
            try {
                // Create post record with status 'pending'
                $post = $this->post::create([
                    'title' => $data['title'] ?? Auth::user()->name,
                    'post_id' => null,
                    'platform' => 'tiktok',
                    'visibility' => 'public',
                    'user_id' => Auth::user()->id,
                    'group_id' => $data['group_id'] ?? null,
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
                            'platform' => 'tiktok',
                            'post_id' => $post->id,
                            'visibility' => 'public',
                            'user_id' => Auth::user()->id,
                            'post_account_id' => $page->id,
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
                $path = Storage::disk('r2')->putFile(
                            $s3Path,
                            $file,
                            ['visibility' => 'public']
                        );
                // Storage::disk('r2')->put(
                //     $s3Path,
                //     file_get_contents($file->getRealPath()),
                //     ['visibility' => 'public']
                // );

                $url = Storage::disk('r2')->url($path);

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

    public function publishPost($post)
    {
        try {
            $account = $post->postAccount;

            if (!$this->ensureValidToken($post)) {
                $post->update([
                    'status'        => 'failed',
                    'error_message' => $this->lastTokenError ?? 'Failed to refresh access token',
                ]);

                return ['success' => false, 'message' => $this->lastTokenError];
            }

            // ensureValidToken() may have just written a new token.
            $account->refresh();

            // Fetch current creator configuration limits
            $creatorResponse = Http::withToken($account->access_token)
                ->asJson()
                ->withBody('{}', 'application/json')
                ->post("{$this->baseUrl}/post/publish/creator_info/query/");

            $creatorBody = $creatorResponse->json() ?? [];

            // creator_info/query answers 200 with error.code != "ok" for
            // the cases that matter most here (spam_risk_too_many_posts,
            // unaudited client, rate limits), so the HTTP status alone
            // isn't a success signal. The old call also passed
            // ($response, $platformString) into errorResponse($model,
            // $response) - arguments in the wrong order, which fataled on
            // ->save() against an HTTP response object instead of
            // recording the real API error on the post.
            if (!$creatorResponse->successful() || ($creatorBody['error']['code'] ?? 'ok') !== 'ok') {
                return $this->errorResponse($post, $creatorResponse);
            }

            $creatorResponseData = $creatorBody['data'] ?? [];

            // Trigger the media router
            $result = $this->pushMediaToTiktok($post, $creatorResponseData, $account);

            if (!$result['success']) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'TikTok publishing failed.'
                ]);
                return $result;
            }

            // The publish_id from post/publish/{video|content}/init/ is a
            // tracking id for TikTok's async processing job, not the
            // video's own id - it looks like "v_pub_url~v2-1...." and
            // produces a 404 if used directly in a tiktok.com/video/{id}
            // URL. The real numeric video id (and the account's own
            // @username, needed for a working share URL) only exist once
            // TikTok finishes processing. resolvePublishedVideo() only
            // covers the first ~10s (fast path for quick uploads) - if
            // TikTok is still processing after that, ResolveTiktokPublishStatus
            // takes over in the background and updates post_id/post_url
            // once it's actually ready, rather than leaving the unresolved
            // publish_id in place indefinitely.
            $resolved = $this->resolvePublishedVideo($account->access_token, $result['publish_id']);

            $post->update([
                'status'        => 'completed',
                'post_id'       => $resolved['video_id'] ?? $result['publish_id'],
                'post_url'      => $resolved['video_id']
                    ? 'https://www.tiktok.com/@' . $account->username . '/video/' . $resolved['video_id']
                    : null,
                'error_message' => $resolved['video_id']
                    ? null
                    : 'Published - TikTok is still processing the video, its public URL will be filled in automatically once ready.',
            ]);

            if (!$resolved['video_id']) {
                ResolveTiktokPublishStatus::dispatch($post->id, $result['publish_id'])
                    ->delay(now()->addSeconds(15));
            }

            return ['success' => true, 'id' => $resolved['video_id'] ?? $result['publish_id']];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function pushMediaToTiktok($post, $creatorResponseData, $account)
    {
        $mediaCount = count($post->media);
        if ($mediaCount === 0) {
            return ['success' => false, 'message' => 'No media files attached to this post.'];
        }

        // Detect if the post contains any video items
        $hasVideo = $post->media->contains(function ($media) {
            $extension = strtolower(pathinfo(parse_url($media->media_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            return in_array($extension, ['mp4', 'mov', 'webm']);
        });
       
        // Guardrails for TikTok API restrictions
        if ($hasVideo) {
           
            if ($mediaCount > 1) {
                return ['success' => false, 'message' => 'TikTok does not allow multiple videos or mixing photos and videos in a single post.'];
            }

            $videoUrl = $post->media->first()->media_url;
            return $this->publishVideo($account->access_token, $post, $videoUrl, $creatorResponseData);
        }

        // Photo processing track (Handles single or multiple photos seamlessly)
        $photoUrls = $post->media->pluck('media_url')->toArray();

        if (count($photoUrls) > 35) {
            return ['success' => false, 'message' => 'TikTok allows a maximum of 35 photos per post.'];
        }

        return $this->publishPhoto($account->access_token, $post, $photoUrls, $creatorResponseData);
    }

    /**
     * TikTok rejects any privacy_level that isn't in the creator's own
     * privacy_level_options for that account, and an unaudited client is
     * only ever offered SELF_ONLY. The old code hardcoded SELF_ONLY for
     * every video (so even an approved app kept publishing privately) and
     * took options[0] blindly for photos - whatever TikTok happened to
     * list first. This maps the post's stored visibility onto what the
     * account actually allows, and falls back to the safest option.
     */
    protected function resolvePrivacyLevel($post, array $creatorInfo): string
    {
        $options = $creatorInfo['privacy_level_options'] ?? [];

        $preferred = match (strtolower((string) ($post->visibility ?? 'public'))) {
            'private', 'self', 'only_me' => 'SELF_ONLY',
            'friends', 'mutual'          => 'MUTUAL_FOLLOW_FRIENDS',
            'followers'                  => 'FOLLOWER_OF_CREATOR',
            default                      => 'PUBLIC_TO_EVERYONE',
        };

        if (in_array($preferred, $options, true)) {
            return $preferred;
        }

        // SELF_ONLY is always on offer, including for unaudited clients.
        return in_array('SELF_ONLY', $options, true)
            ? 'SELF_ONLY'
            : ($options[0] ?? 'SELF_ONLY');
    }

    /**
     * creator_info/query reports per-account interaction locks
     * (comment_disabled / duet_disabled / stitch_disabled). Sending
     * disable_comment=false when the creator has comments switched off is
     * both an API error and a breach of TikTok's UX guidelines - one of
     * the more common app-review rejections. All three were previously
     * hardcoded to false. $includeVideoOnly is false for photo posts,
     * where duet/stitch don't exist as concepts.
     */
    protected function interactionSettings(array $creatorInfo, bool $includeVideoOnly = true): array
    {
        $settings = [
            'disable_comment' => (bool) ($creatorInfo['comment_disabled'] ?? false),
        ];

        if ($includeVideoOnly) {
            $settings['disable_duet']   = (bool) ($creatorInfo['duet_disabled'] ?? false);
            $settings['disable_stitch'] = (bool) ($creatorInfo['stitch_disabled'] ?? false);
        }

        return $settings;
    }

    /**
     * Commercial-content disclosure, which TikTok's Direct Post review
     * checks for explicitly. Read defensively: these two flags only carry
     * a real value once the composer exposes the matching "Your brand" /
     * "Branded content" checkboxes. Until then both default to false,
     * which is the correct "not commercial content" declaration.
     */
    protected function brandSettings($post, string $privacyLevel): array
    {
        $brandOrganic = (bool) ($post->brand_organic_toggle ?? false); // "Your brand"
        $brandContent = (bool) ($post->brand_content_toggle ?? false); // paid partnership

        // TikTok refuses branded content on a private post.
        if ($privacyLevel === 'SELF_ONLY') {
            $brandContent = false;
        }

        return [
            'brand_organic_toggle' => $brandOrganic,
            'brand_content_toggle' => $brandContent,
        ];
    }

    /**
     * post/publish/{video,content}/init/ answers 200 with an error.code
     * other than "ok" for url_ownership_unverified,
     * spam_risk_too_many_posts, privacy_level_option_mismatch and
     * friends. The old check only read the HTTP status, so all of those
     * came back as success with a null publish_id and the post was
     * marked completed while nothing had been published.
     */
    protected function readInitResponse($response, string $fallbackMessage): array
    {
        $body      = $response->json() ?? [];
        $errorCode = $body['error']['code'] ?? 'ok';
        $publishId = data_get($body, 'data.publish_id');

        if (!$response->successful() || $errorCode !== 'ok' || !$publishId) {
            $message = $body['error']['message'] ?? $fallbackMessage;

            // By far the most common PULL_FROM_URL failure, and TikTok's
            // own message doesn't say which domain needs verifying.
            if ($errorCode === 'url_ownership_unverified') {
                $message = 'TikTok rejected the media URL: the domain serving it is not verified for this app. '
                    . 'Verify the CDN domain (CDN_URL) under Content Posting API - Verify domains in the TikTok developer portal.';
            }

            return ['success' => false, 'message' => $message, 'error_code' => $errorCode];
        }

        return ['success' => true, 'publish_id' => $publishId];
    }

    /**
     * Publish Photo Post (Single or Multiple)
     */
    protected function publishPhoto($token, $post, array $photoUrls, $creatorResponseData): array
    {
        try {
            $content = trim($post->content ?? '');
            $contentWithoutHashtags = preg_replace('/#\S+/u', '', $content);
            $contentWithoutHashtags = preg_replace('/\s+/', ' ', trim($contentWithoutHashtags));
            $dotPosition = mb_strpos($contentWithoutHashtags, '.');

            $title = ($dotPosition !== false && $dotPosition < 85)
                ? mb_substr($contentWithoutHashtags, 0, $dotPosition + 1)
                : mb_substr($contentWithoutHashtags, 0, 85);

            $privacyLevel = $this->resolvePrivacyLevel($post, $creatorResponseData);

            $payload = [
                'post_info' => array_merge([
                    // Photo posts cap title at 90 and description at 4000.
                    'title'          => mb_substr($title ?: 'Post Image', 0, 90),
                    'description'    => mb_substr($post->content ?? '', 0, 4000),
                    'privacy_level'  => $privacyLevel,
                    'auto_add_music' => false,
                ], $this->interactionSettings($creatorResponseData, false), $this->brandSettings($post, $privacyLevel)),
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => $photoUrls,
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("{$this->baseUrl}/post/publish/content/init/", $payload);

            return $this->readInitResponse($response, 'Failed initialization for photo upload.');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish Video Post (Exactly 1 Video)
     */
    protected function publishVideo($token, $post, string $videoUrl, $creatorResponseData): array
    {
        try {
            $privacyLevel = $this->resolvePrivacyLevel($post, $creatorResponseData);

            // Reject an over-length video here rather than spending a
            // publish attempt on a guaranteed rejection - the cap is
            // per-account and arrives with creator_info/query.
            $maxDuration = (int) ($creatorResponseData['max_video_post_duration_sec'] ?? 0);
            $duration    = (int) ($post->media->first()->duration_seconds ?? 0);

            if ($maxDuration > 0 && $duration > $maxDuration) {
                return [
                    'success' => false,
                    'message' => "This video is {$duration}s long - the connected TikTok account can only post videos up to {$maxDuration}s.",
                ];
            }

            $payload = [
                'post_info' => array_merge([
                    // TikTok's video caption field: 2200 characters. The
                    // old 150 silently truncated most captions, hashtags
                    // included.
                    'title'         => mb_substr($post->content ?? '', 0, 2200),
                    'privacy_level' => $privacyLevel,
                ], $this->interactionSettings($creatorResponseData), $this->brandSettings($post, $privacyLevel)),
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("{$this->baseUrl}/post/publish/video/init/", $payload);

            return $this->readInitResponse($response, 'Failed initialization for video upload.');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * A single post/publish/status/fetch/ check - PUBLISH_COMPLETE's
     * response carries the real numeric video id in
     * `publicaly_available_post_id` (TikTok's own field name, misspelling
     * included - not a typo introduced here). Returns the raw status too
     * so callers (the quick synchronous loop below, and the queued
     * fallback job for slower uploads) can each decide how to react to
     * "still processing" differently.
     */
    public function checkPublishStatus(string $accessToken, string $publishId): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/post/publish/status/fetch/", ['publish_id' => $publishId]);

        if (!$response->successful()) {
            return ['status' => null, 'video_id' => null];
        }

        $data = $response->json()['data'] ?? [];
        $status = $data['status'] ?? null;

        return [
            'status'   => $status,
            'video_id' => $status === 'PUBLISH_COMPLETE' ? ($data['publicaly_available_post_id'][0] ?? null) : null,
        ];
    }

    /**
     * Quick synchronous fast-path only - covers the common case where
     * TikTok finishes processing within a few seconds, so the post's real
     * URL is available immediately without the admin needing to wait for
     * a background job. Deliberately short (~10s worst case): a request
     * blocking for TikTok's full processing time (which can run well past
     * a minute for longer videos) would be a much worse experience than
     * just falling back to ResolveTiktokPublishStatus below, which keeps
     * checking in the background for as long as it actually takes.
     */
    protected function resolvePublishedVideo(string $accessToken, ?string $publishId): array
    {
        if (!$publishId) {
            return ['video_id' => null];
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $result = $this->checkPublishStatus($accessToken, $publishId);

            if ($result['video_id']) {
                return $result;
            }

            if ($result['status'] === 'FAILED') {
                return ['video_id' => null];
            }

            sleep(2);
        }

        return ['video_id' => null];
    }

    /**
     * Delete Post - TikTok API does not support deletion
     */
    public function destroy($post): array
    {
        return [
            'success' => false,
            'message' => 'TikTok API does not support deleting posts',
        ];
    }

    /**
     * Update Post - TikTok API does not support editing
     */
    public function updatePost(): array
    {
        return [
            'success' => false,
            'message' => 'TikTok API does not support editing posts',
        ];
    }

    /**
     * Publish a comment reply on TikTok
     */
    public function publishComment($data, $comment)
    {
        $account = $comment->postAccount;

        // Was ensureValidToken($comment->post) - null for any comment whose
        // post row has gone, and the refresh result was never checked.
        if (!$this->ensureValidToken($account)) {
            return [
                'success' => false,
                'message' => $this->lastTokenError ?? 'Failed to refresh the TikTok access token.',
            ];
        }

        $account->refresh();

        $videoId = $comment->post?->post_id ?? ($data['video_id'] ?? null);

        if (!$videoId || !$comment->comment_id) {
            return [
                'success' => false,
                'message' => 'Cannot reply: the TikTok video id or parent comment id is missing on this record.',
            ];
        }

        // NOTE ON WHICH API THIS IS: comment reply lives on the TikTok
        // *Business* API (business-api.tiktok.com), which is a different
        // product from the Login Kit / Content Posting App configured in
        // the developer portal. It needs its own app under TikTok for
        // Business, a TikTok Business Account, and a business_id - the
        // open_id stored in account_id here is NOT a business_id, so this
        // call only works once a business_id has actually been resolved
        // and stored. Guarded rather than left to fail silently.
        if (!$account->business_id) {
            return [
                'success' => false,
                'message' => 'TikTok comment replies require the TikTok Business API: no business_id is stored for this account. '
                    . 'The Login Kit app configured in the developer portal does not grant comment access on its own.',
            ];
        }

        // TikTok Business API endpoint for creating a reply to an existing comment
        $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/business/comment/reply/create/';

        $payload = [
            "business_id" => $account->business_id,
            "video_id"    => $videoId,                                      // TikTok item_id / video_id
            "comment_id"  => $comment->comment_id,                          // Parent comment ID to reply to
            "text"        => $data['body'] ?? ''                             // Reply content
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Access-Token'  => $account->access_token, // TikTok uses 'Access-Token' header or Bearer token
                'Authorization' => 'Bearer ' . $account->access_token,
                'Content-Type'  => 'application/json'
            ],
            $payload,
            'json'
        );

        if (!$response->successful() || ($response->json()['code'] ?? 0) !== 0) {
            return $this->errorResponse($comment, $response);
        }

        // TikTok returns the new created reply ID inside 'data.comment_id'
        $newCommentId = $response->json()['data']['comment_id'] ?? null;

        return $this->storeComment($comment, $data, $newCommentId);
    }

    /**
     * Store the reply comment locally in the database
     */
    private function storeComment($comment, $data, $commentId)
    {
        $createdComment = PostComment::create([
            'content'           => $data['body'] ?? '',
            'sender_type'       => 'support',
            'platform'          => 'tiktok', // Fixed typo ("titkok" -> "tiktok")
            'parent_comment_id' => $comment->id,
            'user_id'           => Auth::id(),
            'sender_name'       => Auth::user()?->name ?? 'Support',
            'post_id'           => $comment->post?->id,
            'is_read'           => 1,
            'is_reply'          => true,
            'user_name'         => Auth::user()?->name ?? 'Support',
            'comment_id'        => $commentId,
            'post_account_id'   => $comment->postAccount?->id
        ]);

        return [
            'message' => 'Reply posted successfully',
            'success' => true,
            'data'    => $createdComment
        ];
    }

    /**
     * Same product caveat as publishComment(): this is the TikTok Business
     * API, not the Login Kit app in the developer portal. There is no
     * comment-reading scope on that app at all - the previous first call
     * here went to the Research API (research/video/comment/list/), which
     * is approved only for academic research and was in any case dead
     * code, its result overwritten by the very next assignment.
     */
    public function getComments($videoId, $account)
    {
        if (!$this->ensureValidToken($account)) {
            return [
                'success' => false,
                'message' => $this->lastTokenError ?? 'Failed to refresh access token',
            ];
        }

        $account->refresh();

        if (!$account->business_id) {
            return [
                'success' => false,
                'message' => 'Reading TikTok comments requires the TikTok Business API: no business_id is stored for this account.',
                'data'    => [],
            ];
        }

        $response = Http::withToken($account->access_token)
            ->get(
                'https://business-api.tiktok.com/open_api/v1.3/business/comment/list/',
                [
                    "business_id" => $account->business_id,
                    "video_id"    => $videoId,
                    "status"      => "PUBLIC",
                ]
            );

        $data = $response->json() ?? [];

        // The Business API keeps its real status in the body's `code`
        // (0 = ok) and returns 200 even for auth failures.
        if (!$response->successful() || ($data['code'] ?? 0) !== 0) {
            return [
                'success' => false,
                'message' => $data['message'] ?? $data['error']['message'] ?? 'Failed to fetch TikTok comments.',
                'data'    => [],
            ];
        }

        return [
            'success' => true,
            'data'    => $data['data']['comments'] ?? $data['items'] ?? [],
        ];
    }

    /**
     * Error Response Handler
     */
    protected function errorResponse($model, $response): array
    {
        $data = $response->json() ?? $response;
        $message = $data['error']['message']
                ?? $data['error']
                ?? $data['message']
                ?? 'TikTok API Error';

        $model->status = 'failed';
        $model->error_message = $message ?? 'TikTok API Error';
        $model->save();
        return [
            'success' => false,
            'message' => $message?? 'TikTok API Error',
            'response' => $data,
        ];
    }


}
