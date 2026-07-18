<?php

namespace App\Services\PostServices;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Models\PostMedia;
use Webkul\Core\Models\Chat;
use getID3;

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
    protected function ensureValidToken($account)
    {
        // Token still valid
        if (
            !empty($account->expires_in)
            && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        $clientId = core()->superConfig("admin.twsaa.tiktok.posts.app_id");
        $clientSecret = core()->superConfig("admin.twsaa.tiktok.posts.app_secret");

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse($response);
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
                    'page_id' => $page->external_id,
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
                    'page_id' => $page->external_id,
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

                Storage::disk('s3')->put(
                    $s3Path,
                    file_get_contents($file->getRealPath()),
                    ['visibility' => 'public']
                );

                $url = Storage::disk('s3')->url($s3Path);

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
     * Get creator info for a page
     */
    protected function getCreatorInfo($page): array
    {
        try {
            $response = Http::withToken($page->access_token)
                ->asJson()
                ->withBody('{}', 'application/json')
                ->post("{$this->baseUrl}/post/publish/creator_info/query/");

            if (!$response->successful()) {
                return $this->errorResponse($response);
            }

            return [
                'success' => true,
                'data' => $response->json()['data']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload Media to S3
     */
    protected function uploadMedia($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = uniqid() . '.' . $extension;
        $path = "uploads/tiktok/{$fileName}";

        Storage::disk('s3')->put(
            $path,
            file_get_contents($file->getRealPath()),
            [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType(),
            ]
        );

        return Storage::disk('s3')->url($path);
    }

    /**
     * Publish Photo Post
     */
    protected function publishPhoto($page, $data, $photoUrls, $creatorResponseData): array
    {
        try {
            $content = trim($data['content'] ?? '');
            $dotPosition = mb_strpos($content, '.');
            $contentWithoutHashtags = preg_replace('/#\S+/u', '', $content);
            $contentWithoutHashtags = preg_replace('/\s+/', ' ', trim($contentWithoutHashtags));

            $dotPosition = mb_strpos($contentWithoutHashtags, '.');

            $title = ($dotPosition !== false && $dotPosition < 85)
                ? mb_substr($contentWithoutHashtags, 0, $dotPosition + 1)
                : mb_substr($contentWithoutHashtags, 0, 85);

            $payload = [
                'post_info' => [
                    'title' => $title,
                    'description' => $data['content'] ?? '',
                    'privacy_level' => $creatorResponseData['privacy_level_options'][0] ?? 'PUBLIC',
                    'disable_comment' => false,
                    'auto_add_music' => true,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => is_array($photoUrls) ? $photoUrls : [$photoUrls],
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ];

            $response = Http::withToken($page->access_token)
                ->acceptJson()
                ->post("{$this->baseUrl}/post/publish/content/init/", $payload);

            if (!$response->successful()) {
                return $this->errorResponse($response);
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
     * Publish Video Post
     */
    protected function publishVideo($page, $data, $videoUrls, $creatorResponseData): array
    {
        try {
            // Use first video URL if multiple provided
            $videoUrl = is_array($videoUrls) ? $videoUrls[0] : $videoUrls;

            $payload = [
                'post_info' => [
                    'title' => $data['content'] ?? '',
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

            $response = Http::withToken($page->access_token)
                ->acceptJson()
                ->post("{$this->baseUrl}/post/publish/video/init/", $payload);

            if (!$response->successful()) {
                return $this->errorResponse($response);
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
     * Get Publish Status
     */
    public function getPublishStatus(string $publishId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->post("{$this->baseUrl}/post/publish/status/fetch/", [
                    'publish_id' => $publishId
                ]);

            if (!$response->successful()) {
                return $this->errorResponse($response);
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Save Social Post to Database
     */
    protected function saveSocialPost($publishId, $mediaUrls, array $data, $page): SocialPost
    {
        // Store media URLs as JSON
        $mediaJson = is_array($mediaUrls) ? json_encode($mediaUrls) : $mediaUrls;

        return SocialPost::create([
            'company_id' => company()->id(),
            'social_publish_account_id' => $page->id,
            'social_category_id' => $data['category_id'] ?? 1,
            'page_post_id' => $publishId,
            'page_id' => $page->external_id,
            'content' => $data['content'] ?? null,
            'media' => $mediaJson,
            'url' => $mediaJson,
            'status' => 'processing',
            'schedule_mode' => $data['schedule_mode'] ?? 0,
            'schedule_at' => isset($data['schedule_at']) ? Carbon::parse($data['schedule_at']) : null,
            'expiry_at' => isset($data['expiry_at']) ? Carbon::parse($data['expiry_at']) : null,
            'expiry_mode' => $data['expiry_mode'] ?? 0,
        ]);
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
     * Publish a comment/reply
     */
    public function publishComment($chat, $data)
    {
        $this->ensureValidToken($chat->socialPublishAccount);
        $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/business/comment/list/';
        $payload = [
            "business_id" => $chat->socialPublishAccount->external_id,
            "video_id" => $chat->socialPublishAccount->page_post_id,
            "status" => "PUBLIC"
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $chat->socialPublishAccount->access_token,
                'Content-Type' => 'application/json'
            ],
            $payload
        );

        if (!$response->successful()) {
            return $this->errorResponse($response);
        }

        return $this->storeComment($chat, $data, $response->json()['id']);
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
                    "business_id" => $account->external_id,
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
    protected function errorResponse($response): array
    {
        $data = $response->json() ?? $response;

        return [
            'success' => false,
            'message' => $data['error']['message']
                ?? $data['error']
                ?? $data['message']
                ?? 'TikTok API Error',
            'response' => $data,
        ];
    }

    private function storeComment($chat, $data, $commentId)
    {
        $comment = Chat::create([
            'body'            => $data['body'] ?? '',
            'sender_type'     => 'support',
            'sender_name'     => $chat->youtubeChat?->profile_name ?? '',
            'applicable_id'   => $chat->youtubeChat?->id,
            'is_read'         => 0,
            'type'            => 'comment',
            'applicable_type' => 'youtube',
            'file'            => '',
            'comment_id'      => $commentId,
            'social_post_id'  => $chat->socialPost?->id,
            'social_publish_account_id' => $chat->socialPublishAccount?->id,
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
    }
}
