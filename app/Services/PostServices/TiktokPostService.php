<?php

namespace App\Services\PostServices;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Models\PostMedia;
use getID3;
use App\Models\PostComment;

class TiktokPostService
{
    protected $api, $post, $media, $baseUrl;

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
    protected function ensureValidToken($post)
    {
        $account = $post->postAccount;
        // Token still valid
        if (
            !empty($account->expires_in)
            && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        $clientId = adminSetting('posts.tiktok.client_id');
        $clientSecret = adminSetting('posts.tiktok.client_secret');

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token'      => $tokenData['access_token'],
            'refresh_token'     => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_in'   => now()->addSeconds($tokenData['expires_in']),
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

        if (!empty($data['ai_image_url'])) {
            $mediaExtension = strtolower(pathinfo(
                parse_url($data['ai_image_url'], PHP_URL_PATH),
                PATHINFO_EXTENSION
            ));
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

    public function publishPost($post)
    {
        try {
            $account = $post->postAccount;
            if (!$this->ensureValidToken($post)) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to refresh access token'
                ]);

                return ['success' => false];
            }

            // Fetch current creator configuration limits
            $creatorResponse = Http::withToken($account->access_token)
                ->asJson()
                ->withBody('{}', 'application/json')
                ->post("{$this->baseUrl}/post/publish/creator_info/query/");

            if (!$creatorResponse->successful()) {
                return $this->errorResponse($creatorResponse, $account->platform);
            }

            $creatorResponseData = $creatorResponse->json()['data'] ?? [];

            // Trigger the media router
            $result = $this->pushMediaToTiktok($post, $creatorResponseData, $account);

            if (!$result['success']) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'TikTok publishing failed.'
                ]);
                return $result;
            }

            $post->update([
                'status' => 'completed',
                'post_id' => $result['publish_id'],
                'error_message' => null
            ]);

            return ['success' => true, 'id' => $result['publish_id']];
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

            $payload = [
                'post_info' => [
                    'title' => $title ?: 'Post Image',
                    'description' => $post->content ?? '',
                    'privacy_level' => $creatorResponseData['privacy_level_options'][0] ?? 'PUBLIC',
                    'disable_comment' => false,
                    'auto_add_music' => false,
                ],
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

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['error']['message'] ?? 'Failed initialization for photo upload.'];
            }

            return [
                'success' => true,
                'publish_id' => data_get($response->json(), 'data.publish_id'),
            ];
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
            $payload = [
                'post_info' => [
                    'title' => mb_substr($post->content ?? '', 0, 150), // Title string field setup
                    'privacy_level' => $creatorResponseData['privacy_level_options'][0] ?? 'PUBLIC',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("{$this->baseUrl}/post/publish/video/init/", $payload);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['error']['message'] ?? 'Failed initialization for video upload.'];
            }

            return [
                'success' => true,
                'publish_id' => data_get($response->json(), 'data.publish_id'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
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

        $this->ensureValidToken($comment->post);

        // TikTok Business API endpoint for creating a reply to an existing comment
        $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/business/comment/reply/create/';

        $payload = [
            "business_id" => $account->account_id,
            "video_id"    => $comment->post?->post_id ?? $data['video_id'], // TikTok item_id / video_id
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

    public function getComments($videoId, $account)
    {

        if (!$this->ensureValidToken($account)) {
            $errors = [
                'success' => false,
                'message' => 'Failed to refresh access token'
            ];
        }
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $account->access_token,
            'Content-Type'  => 'application/json',
        ])->post(
            'https://open.tiktokapis.com/v2/research/video/comment/list/',
            [
                'video_id' => $videoId,
                'max_count' => 100,
                'cursor' => 0,
            ]
        )->json();

        $response = Http::withToken($account->access_token)
            ->get(
                'https://business-api.tiktok.com/open_api/v1.3/business/comment/list/',
                [
                    "business_id" => $account->account_id,
                    "video_id" => $videoId,
                    "status" => "PUBLIC"
                ]
            );
        $data = $response->json();

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $data['error']['message']
            ];
        }

        return [
            'success' => true,
            'data' => $data['items']
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
