<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessingGooglePostJob;
use getID3;
use App\Models\PostComment;

class GooglePostService
{
    protected $api, $baseUrl, $accountBaseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.google.base_url');
        $this->accountBaseUrl = adminSetting('posts.google.account_base_url');
    }

    /**
     * Ensure valid access token by refreshing if needed
     */
    protected function ensureValidToken($post)
    {
        $account = $post->socialAccount;
        // Token still valid
        if (
            !empty($account->expires_at)
            && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))
        ) {
            return true;
        }

        $clientId = adminSetting('posts.google.client_id');
        $clientSecret = adminSetting('posts.google.client_secret');
        $endpoint = 'https://oauth2.googleapis.com/token';
        $payload = [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $account->refresh_token,
        ];

        $response = $this->api->request('post', $endpoint, [], $payload, 'form');

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token'     => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_at'  => now()->addSeconds($tokenData['expires_in']),
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
            $uploadResult = $this->uploadMediaToS3($data['media'] ?? []);
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
                    'platform' => 'google',
                    'visibility' => 'public',
                    'user_id' => Auth::user()->id,
                    'social_account_id' => $page->id,
                    'post_category_id' => $data['category_id'] ?? 1,
                    'group_id' => $data['group_id'] ?? null,
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
                            'platform' => 'google',
                            'post_id' => $post->id,
                            'platform' => 'google',
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
     * Process a single post (called by job)
     */
    public function publishPost($post)
    {
        try {
            $account = $post->socialAccount;
            // Ensure valid token
            if (!$this->ensureValidToken($post)) {
                $post->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to refresh access token'
                ]);
                return false;
            }

            // Publish to location
            $result = $this->publishToLocation($post);
           
            if ($result['success']) {
                $data = $result['response'];

                $postId = basename($data['name']);
                $post->update([
                    'post_id' => $postId,
                    'status' => 'completed',
                    'error_message' => ''
                ]);
                return ['success' => true];
            } else {
                $post->update([
                    'status' => 'failed',
                    'error_message' => $result['message']
                ]);
                return ['success' => false];
            }
        } catch (\Exception $e) {
            $post->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            return ['success' => false];
        }
    }

    /**
     * Publish to a specific location
     */
    protected function publishToLocation($post)
    {
        try {
  
            $account = $post->socialAccount;
            $payload = [
                'languageCode' => 'en-US',
                "topic_type" => "STANDARD",
                'summary' => $post->content ?? '', // or $post->content depending on your field name
                // Add other fields like callToAction or topicType if needed
            ];

            // If post has media relations/array
            if ($post && $post->media && count($post->media) > 0) {
                $mediaArray = [];

                foreach ($post->media as $item) {
                    // Extract URL dynamically whether it's an object, array, or direct string URL
                    $mediaUrl = $item->media_url;

                    if (empty($mediaUrl)) {
                        continue;
                    }

                    // Get extension from URL (ignores query string strings safely)
                    $extension = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'];
                    $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', '3gp', 'mpeg', 'mpg'];
                    $mediaFormat = 'PHOTO'; // Default fallback

                    if (in_array($extension, $videoExtensions)) {
                        $mediaFormat = 'VIDEO';
                    } elseif (in_array($extension, $imageExtensions)) {
                        $mediaFormat = 'PHOTO';
                    }

                    $mediaArray[] = [
                        "mediaFormat" => $mediaFormat,
                        "sourceUrl" => $mediaUrl,
                    ];
                }

                if (!empty($mediaArray)) {
                    $payload['media'] = $mediaArray[0];
                }
            }

            $gbpAccountId = $account->metadata['parent_account_id'] ?? '';

            $response = $this->api->request(
                'post',
                "{$this->baseUrl}accounts/{$gbpAccountId}/locations/{$account->platform_account_id}/localPosts",
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $account->access_token
                ],
                $payload,
                'json'
            );
            if (!$response->successful()) {
                $error = $response->json();
                // Skip INVALID_ARGUMENT errors (e.g., duplicate posts)
                if (isset($error['error']['status']) && $error['error']['status'] === 'INVALID_ARGUMENT') {
                    return $this->errorResponse($post, $response);
                }

                return $this->errorResponse($post, $response);
            }

            $responseData = $response->json();
            $postName = $responseData['name'] ?? null;
            $postId = $postName ? basename($postName) : null;

            return [
                'success' => true,
                'post_id' => $postId,
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get locations for a Google Business Profile account
     */
    protected function getLocations($account)
    {
        try {
            $gbpAccountId = $account->metadata['parent_account_id'] ?? '';

            $response = $this->api->request(
                'get',
                "{$this->accountBaseUrl}accounts/{$gbpAccountId}/locations?read_mask=name,title,metadata,state",
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $account->access_token
                ],
                [],
                'form'
            );

            if (!$response->successful()) {
                return $this->errorResponse($response->json());
            }

            $locations = $response->json()['locations'] ?? [];

            return [
                'success' => true,
                'data' => collect($locations)->pluck('name')->toArray()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get posts for a location
     */
    public function getPosts($locationId, $page)
    {
        $this->ensureValidToken($page);
        $endpoint = "{$this->baseUrl}accounts/{$page->platform_account_id}/locations/{$locationId}/localPosts";

        $response = $this->api->request(
            'get',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $page->access_token
            ],
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
            'data' => $response->json()['localPosts'] ?? []
        ];
    }


    /**
     * Get posts for a location
     */
    public function getPost($post, $page)
    {
        $this->ensureValidToken($page);
        $gbpAccountId = $page->metadata['parent_account_id'] ?? '';
        $endpoint = "{$this->baseUrl}accounts/{$gbpAccountId}/locations/{$page->platform_account_id}/localPosts/{$post->post_id}";

        $response = $this->api->request(
            'get',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $page->access_token
            ],
            []
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Unknown error'
            ];
        }

        if ($response['state'] === 'LIVE') {
            $post->update([
                'status' => 'completed'
            ]);
        }

        return [
            'success' => true,
            'message' => $response
        ];
    }


    /**
     * Send a reply to a Google Business Profile Review
     *
     * @param string $data   The incoming request data
     * @param string $comment  The comment to which the reply will send
     * @return array
     */
    public function publishComment($data, $comment): array
    {
        try {
            $this->ensureValidToken($comment->post);
            $account = $comment->socialAccount;
            $gbpAccountId = $account->metadata['parent_account_id'] ?? '';
            $endpoint = "https://mybusiness.googleapis.com/v4/accounts/{$gbpAccountId}/locations/{$account->platform_account_id}/reviews/{$comment->comment_id}/reply";

            $payload = [
                'comment' => $data['body'],
            ];

            $response = $this->api->request(
                'put',
                $endpoint,
                [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $account->access_token,
                ],
                $payload,
                'json'
            );

            if (!$response->successful()) {
                return $this->errorResponse($comment, $response);
            }
    
            return $this->storeComment($comment, $data, $response->json()['id']);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function storeComment($comment, $data, $commentId)
    {
        $comment = PostComment::create([
            'content'            => $data['body'] ?? '',
            'sender_type'     => 'support',
            'platform'        => 'google',
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
     * Delete a post
     */
    public function destroy($post)
    {
        $this->ensureValidToken($post);
        $gbpAccountId = $post->socialAccount->metadata['parent_account_id'] ?? '';
        $endpoint = "{$this->baseUrl}accounts/{$gbpAccountId}/locations/{$post->socialAccount->platform_account_id}/localPosts/{$post->post_id}";

        $response = $this->api->request(
            'delete',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $post->socialAccount->access_token
            ],
            []
        );

        $responseData = $response->json();
        $errorCode = $responseData['error']['code'] ?? null;
        if (!$response->successful()) {
            if ($errorCode !== 404) {
                return [
                    'success' => false,
                    'status' => $errorCode,
                    'message' => $responseData['error']['message'] ?? 'Unknown error'
                ];
            }
        }

        return [
            'success' => true,
            'status' => $errorCode,
            'data' => $response->successful() ? $responseData : ['message' => 'Post was already deleted directly on Google. Local data cleaned up.']
        ];
    }

    /**
     * Error response handler
     */
    private function errorResponse($model, $response)
    {
        $message = $error['details'][0]['errorDetails'][0]['message']
        ?? $error['error_description']
        ?? $error['message']
        ?? 'Unknown error';
        $error = $response['error'] ?? $response;
        $model->status = 'failed';
        $model->error_message = $message;
        $model->save();
        
        return [
            'success' => false,
            'message' => $message
        ];
    }
}
