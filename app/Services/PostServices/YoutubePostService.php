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
    protected function ensureValidToken($account)
    {

        // Token still valid
        if (
            !empty($account->expires_in)
            && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        $clientId = core()->superConfig("admin.twsaa.google.posts.app_id");
        $clientSecret = core()->superConfig("admin.twsaa.google.posts.app_secret");

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $account->refresh_token,
        ]);

        if (!$response->successful()) {
            return $this->errorResponse($response);
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

        // Remove the uploaded file from data to avoid serialization issues
        $jobData = $data;
        unset($jobData['media']); // Remove the file object

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
     * Create schedule record in database
     */
    protected function createScheduleRecord($data, $page, $mediaResult): SocialPost
    {
        $mediaData = [
            'url' => $mediaResult['url'],
            'ext' => $mediaResult['ext']
        ];

        return SocialPost::create([
            'page_post_id'          => null,
            'company_id'            => company()->id(),
            'social_publish_account_id'      => $page->id,
            'social_category_id'    => $data['category_id'] ?? 1,
            'page_id'               => $page->external_id,
            'content'               => $data['content'] ?? null,
            'media'                 => json_encode($mediaData),
            'url'                   => json_encode($mediaData),
            'status'                => 'scheduled',
            'schedule_mode'         => $data['schedule_mode'] ?? 0,
            'schedule_at'           => isset($data['schedule_at']) ? Carbon::parse($data['schedule_at']) : null,
            'expiry_mode'           => $data['expiry_mode'] ?? 0,
            'expiry_at'             => isset($data['expiry_at']) ? Carbon::parse($data['expiry_at']) : null,
        ]);
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
            if (empty($post->page_post_id)) {
                return [
                    'success' => false,
                    'message' => 'Post ID is required to delete'
                ];
            }

            $this->ensureValidToken($post->socialPublishAccount);

            $endpoint = "{$this->baseUrl}/videos?id={$post->page_post_id}";

            $response = Http::withToken($post->socialPublishAccount->access_token)
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
    private function errorResponse($response)
    {
        $data = $response->json() ?? $response;

        $message = $data['error']['details'][0]['errorDetails'][0]['message']
            ?? $data['error']['message']
            ?? $data['error']
            ?? $data['message']
            ?? $data['error_description']
            ?? 'Unknown error';

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
        $this->ensureValidToken($chat->socialPublishAccount);
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
            'social_publish_account_id' => $chat->socialPublishAccount?->id,
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
        $this->ensureValidToken($chat->socialPublishAccount);

        $endpoint = 'https://www.googleapis.com/youtube/v3/comments?id=' . $chat->comment_id;



        $response = $this->api->request(
            'delete',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $chat->socialPublishAccount->access_token,
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
