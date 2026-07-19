<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Jobs\UploadYoutubeVideoJob;
use App\Models\Post;
use App\Models\PostMedia;
use Webkul\Core\Models\Chat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Webkul\Admin\Models\MediaAccount;
use Illuminate\Support\Facades\Storage;
use getID3;


class YoutubePostService
{
    protected $api, $baseUrl, $accountBaseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = 'https://www.googleapis.com/youtube/v3';
        $this->accountBaseUrl = config('services.google.account_base_url');
    }

    /**
     * Ensure valid access token by refreshing if needed
     */
    protected function ensureValidToken($post)
    {

        $account = $post->postAccount;
        if (
            $account->expires_in &&
            now()->lt(Carbon::parse($account->expires_in)->subMinutes(5))
        ) {
            return true;
        }

        if (empty($account->refresh_token)) {
            return false;
        }

        $clientId = adminSetting('posts.google.client_id');
        $clientSecret = adminSetting('posts.google.client_secret');

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $account->refresh_token,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token'       => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_in'    => now()->addSeconds($tokenData['expires_in']),
        ]);

        $account->refresh();
        return true;
    }

    /**
     * Publish post to multiple Facebook pages using queue
     */
    public function store($data, $pages)
    {
        $results = [];
        $errors = [];
        $successCount = 0;

        // Upload media to S3 once
        $mediaUrl = null;
        $mediaExtension = null;
        $mediaType = null;
        $uploadResult = [];
        if (isset($data['ai_image_url'])) {
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
                    'platform' => 'youtube',
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
                            'platform' => 'youtube',
                            'post_id' => $post->id,
                            'platform' => 'youtube',
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

            if ($post->media->isEmpty()) {
                throw new \Exception('No media found for this post.');
            }

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $uploadedVideoIds = [];
            $content = trim($post->content ?? '');

            // ==========================================
            // LOOP THROUGH EACH ATTACHED VIDEO
            // ==========================================
            foreach ($post->media as $index => $mediaItem) {
                $mediaUrl = $mediaItem->media_url;

                $fileExtension = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                $tempPath = $tempDir . '/' . uniqid() . '.' . $fileExtension;

                // Download media from S3/CDN to temp directory
                $downloadSuccess = file_put_contents($tempPath, file_get_contents($mediaUrl));

                if (!$downloadSuccess || !file_exists($tempPath)) {
                    throw new \Exception('Unable to download media from: ' . $mediaUrl);
                }

                $mime = mime_content_type($tempPath);
                $videoSize = filesize($tempPath);

                if ($videoSize === 0) {
                    if (file_exists($tempPath)) { unlink($tempPath); }
                    throw new \Exception('Downloaded video file is empty.');
                }

                /*
                |--------------------------------------------------------------------------
                | Title Generation (Unique per video index if multiple)
                |--------------------------------------------------------------------------
                */
                $dotPosition = mb_strpos($content, '.');
                $baseTitle = ($dotPosition !== false && $dotPosition < 90)
                    ? mb_substr($content, 0, $dotPosition + 1)
                    : mb_substr($content, 0, 90);

                if (empty($baseTitle)) {
                    $baseTitle = 'Video ' . date('Y-m-d H:i:s');
                }

                // If uploading multiple videos, append a part number so they don't look identical
                $title = count($post->media) > 1 ? "{$baseTitle} (Part " . ($index + 1) . ")" : $baseTitle;

                $categoryId = '22'; // Default: People & Blogs
                try {
                    if (isset($post->socialCategory) && $post->socialCategory) {
                        $categoryId = '22';
                    }
                } catch (\Exception $e) {}

                $payload = [
                    'snippet' => [
                        'title' => mb_substr($title, 0, 100),
                        'description' => $post->content ?? '',
                        'categoryId' => $categoryId
                    ],
                    'status' => [
                        'privacyStatus' => 'public'
                    ]
                ];

                /*
                |--------------------------------------------------------------------------
                | Create Resumable Upload Session
                |--------------------------------------------------------------------------
                */
                $sessionResponse = Http::timeout(300)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $account->access_token,
                        'Content-Type' => 'application/json',
                        'X-Upload-Content-Type' => $mime,
                        'X-Upload-Content-Length' => $videoSize,
                    ])
                    ->post(
                        'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
                        $payload
                    );

                if (!$sessionResponse->successful()) {
                    if (file_exists($tempPath)) { unlink($tempPath); }
                    $errorBody = $sessionResponse->body();
                    throw new \Exception('YouTube session creation failed: ' . $errorBody);
                }

                $uploadUrl = $sessionResponse->header('Location');
                if (empty($uploadUrl)) {
                    if (file_exists($tempPath)) { unlink($tempPath); }
                    throw new \Exception('No upload URL received from YouTube.');
                }

                /*
                |--------------------------------------------------------------------------
                | Upload Video Data Stream
                |--------------------------------------------------------------------------
                */
                $stream = fopen($tempPath, 'r');
                $uploadResponse = Http::timeout(3600)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $account->access_token,
                        'Content-Type' => $mime,
                        'Content-Length' => $videoSize,
                    ])
                    ->withBody($stream, $mime)
                    ->send('PUT', $uploadUrl);

                fclose($stream);
                if (file_exists($tempPath)) {
                    unlink($tempPath); // Always clean up temporary files immediately
                }

                if (!$uploadResponse->successful()) {
                    throw new \Exception('Video chunk upload failed: ' . $uploadResponse->body());
                }

                $youtubeData = $uploadResponse->json();
                $videoId = $youtubeData['id'] ?? null;

                if (!$videoId) {
                    throw new \Exception('YouTube video ID missing from response payload.');
                }

                $uploadedVideoIds[] = $videoId;
                
                // Optional: Save individual ID to its specific media record
                $mediaItem->update(['media_id' => $videoId]);
            }

            /*
            |--------------------------------------------------------------------------
            | Finalize Post State
            |--------------------------------------------------------------------------
            */
            // Store all IDs as a comma-separated string inside your main post layout record
            $post->update([
                'post_id' => $uploadedVideoIds[0],
                'status' => 'completed',
                'error_message' => null
            ]);

            return ['success' => true, 'video_ids' => $uploadedVideoIds];

        } catch (\Exception $e) {


            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    /**
     * Get posts for a channel
     */
    public function getPosts($channelId, $accessToken)
    {
        try {
            $endpoint = "{$this->baseUrl}/search?channelId={$channelId}&part=snippet,id&order=date&maxResults=50";

            $response = Http::withToken($accessToken)
                ->get($endpoint);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $response->json()['error']['message'] ?? 'Unknown error'
                ];
            }

            return [
                'success' => true,
                'data' => $response->json()['items'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a video
     */
    public function destroy($post)
    {
        try {
            if (empty($post->post_id)) {
                return [
                    'success' => false,
                    'message' => 'Post ID is required to delete'
                ];
            }

            $this->ensureValidToken($post->postAccount);

            $endpoint = "{$this->baseUrl}/videos?id={$post->post_id}";

            $response = Http::withToken($post->postAccount->access_token)
                ->delete($endpoint);

            $responseData = $response->json();

            if (!$response->successful()) {
                $errorCode = $responseData['error']['code'] ?? null;
                if ($errorCode !== 404) {
                    return [
                        'success' => false,
                        'message' => $responseData['error']['message'] ?? 'Unknown error'
                    ];
                }
            }

            // Delete media from S3 if exists
            if ($post->media) {
                $mediaUrls = json_decode($post->media, true) ?? [$post->media];
                foreach ((array)$mediaUrls as $mediaUrl) {
                    if ($mediaUrl) {
                        $path = parse_url($mediaUrl, PHP_URL_PATH);
                        if ($path) {
                            // S3 usually prefers paths without the leading slash
                            $path = ltrim($path, '/');
                            Storage::disk('s3')->delete($path);
                        }
                    }
                }
            }

            // Delete table data (the SocialPost model)
            //$post->delete();

            return [
                'success' => true,
                // Return actual data if successful, or a custom message if it was a 404
                'data' => $response->successful() ? $responseData : ['message' => 'Post was already deleted directly on Google. Local data cleaned up.']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update video details (title, description, etc.)
     */
    public function updatePost($postId, $data, $token)
    {
        try {
            $account = SocialPost::with('mediaAccount')->wherePagePostId($postId)->first();
            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'Post not found'
                ];
            }

            $this->ensureValidToken($account->mediaAccount);

            // Build update payload
            $payload = [
                'id' => $postId,
                'snippet' => [
                    'title' => $data['content'] ?? '',
                    'description' => $data['description'] ?? '',
                ]
            ];

            // Add schedule if needed
            if (!empty($data['schedule_mode']) && $data['schedule_mode'] === 'on') {
                $payload['status'] = [
                    'privacyStatus' => 'private',
                    'publishAt' => Carbon::parse($data['schedule_at'])->toIso8601String()
                ];
            }

            $endpoint = "{$this->baseUrl}/videos?part=snippet,status";

            $response = Http::withToken($token)
                ->put($endpoint, $payload);

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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get video upload status
     */
    public function getVideoStatus($videoId, $accessToken)
    {
        try {
            $endpoint = "{$this->baseUrl}/videos?id={$videoId}&part=status,snippet";

            $response = Http::withToken($accessToken)
                ->get($endpoint);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $response->json()['error']['message'] ?? 'Unknown error'
                ];
            }

            $items = $response->json()['items'] ?? [];
            if (empty($items)) {
                return [
                    'success' => false,
                    'message' => 'Video not found'
                ];
            }

            return [
                'success' => true,
                'data' => $items[0]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Error response handler
     */
    private function errorResponse($model, $response)
    {
        $data = $response->json() ?? $response;

        $message = $data['error']['details'][0]['errorDetails'][0]['message']
            ?? $data['error']['message']
            ?? $data['error']
            ?? $data['message']
            ?? $data['error_description']
            ?? 'Unknown error';

            $model->status = 'failed';
            $model->error_message = $message ?? 'Unknown error';
            $model->save();
        return [
            'success' => false,
            'message' => $message
        ];
    }

    /**
     * Publish a comment/reply
     */
    public function publishComment($chat, $data)
    {
        $this->ensureValidToken($chat->postAccount);
        $endpoint = 'https://www.googleapis.com/youtube/v3/comments?part=snippet';
        $payload = [
            'snippet' => [
                'parentId' => $chat->comment_id,
                'textOriginal' => $data['body'],
            ]
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $chat->postAccount->access_token,
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

        $response = Http::withToken($account->access_token)
            ->get(
                'https://www.googleapis.com/youtube/v3/commentThreads',
                [
                    'part' => 'snippet,replies',
                    'videoId' => $videoId,
                    'maxResults' => 100,
                    'order' => 'time',
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
            'social_publish_account_id' => $chat->postAccount?->id,
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
    }

    /**
     * Delete a comment
     */
    public function destroyComment($chat)
    {
        $this->ensureValidToken($chat->postAccount);

        $endpoint = 'https://www.googleapis.com/youtube/v3/comments?id=' . $chat->comment_id;



        $response = $this->api->request(
            'delete',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $chat->postAccount->access_token,
            ],
            [
                'id' => $chat->comment_id
            ]
        );

        if (!$response->successful()) {
            return $this->errorResponse($response);
        }

        return [
            'success' => true,
            'data' => $response->json() ?? []
        ];
    }
}
