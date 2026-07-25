<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use App\Models\PostMedia;
use getID3;
use App\Models\PostComment;

class XPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.x.base_url');
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

        $endpoint = $this->baseUrl . "oauth2/token";

        $payload = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id'     => adminSetting('posts.x.client_id'),
            'client_secret' => adminSetting('posts.x.client_secret')
        ];
     
        $response = $this->api->request('post', $endpoint, [], $payload, 'form');


        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        $token = $response->json();

        $account->update([
            'access_token'   => $token['access_token'],
            'refresh_token'  => $token['refresh_token'] ?? $account->refresh_token,
            'expires_in'     => now()->addSeconds($token['expires_in']),
        ]);

        $account->refresh();
        return true;
    }

    /**
     * Publish post to multiple Instagram pages using queue
     */
    public function store($data, $pages) {
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
                    'platform' => 'x',
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
                            'platform' => 'x',
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
        $account = $post->postAccount;
        if (!$this->ensureValidToken($post)) {
            $post->update([
                'status' => 'failed',
                'error_message' => 'Failed to refresh access token'
            ]);

            return false;
        }
        $endpoint = $this->baseUrl . 'tweets';
        $account = $post->postAccount;
        $payload = [
            'text' => $post->content ?? '',
            "share_with_followers" => true,
        ];
    
        // 1. Process all media items attached to the post
        $uploadedMediaIds = [];
        
        foreach ($post->media as $mediaItem) {
            // Assuming $mediaItem holds the direct file URL in a property like 'url' or 'path'
            // Replace $mediaItem->url with the exact field name your DB uses
            $mediaResult = $this->uploadXMedia($mediaItem, $post, $account);
    
            if (!$mediaResult['success']) {
                return $mediaResult; // Handle individual upload failures
            }
    
            $uploadedMediaIds[] = $mediaResult['media_id'];
        }
   
        // 2. Attach the collected array of media IDs if they exist
        if (!empty($uploadedMediaIds)) {
            $payload['media'] = [
                'media_ids' => $uploadedMediaIds
            ];
        }
    
        // API Request to post the Tweet
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $account->access_token,
            'Content-Type' => 'application/json'
        ])->post($endpoint, $payload);
    
        if (!$response->successful()) {
            return $this->errorResponse($post, $response, $account->platform);
        }
    

    
        $postId = $response->json()['data']['id'];

        $post->status = 'completed';
        $post->post_id = $postId;
        $post->error_message = null;
        $post->save();

        return [
            'success' => true,
            'id' => $postId,
        ];
    }
    
    protected function uploadXMedia($media, $post, $account): array
    {
        $url = $media->media_url;
        // Extracted logic remains the same, but acts sequentially per media item
        $extension = strtolower(
            pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
        );
    
        $fileName = $media->file_name;
        
        // Note: X supports standard document formats as images if they are PDFs, 
        // otherwise you must rely on standard image/video extensions.
        $isVideo = in_array($extension, ['mp4', 'mov', 'avi']);
        $mimeType = null;
        $tempFile = null;
    
        $authHeaders = [
            'Authorization' => "Bearer {$account->access_token}",
            'Content-Type'  => 'application/json',
        ];
    
        if (isset($post->post_id) && $post->post_id != null) {
            $response = $this->api->request(
                'get',
                $this->baseUrl . 'media/upload',
                $authHeaders,
                [
                    'command' => 'STATUS',
                    'media_id' => $post->post_id,
                ]
            );
           
            if ($response->successful()) {
                $data = $response->json()['data'];
                $processingInfo = data_get($data, 'processing_info');
                $status = $processingInfo['state'];
                
                if (in_array($status, ['pending', 'in_progress', 'succeeded'])) {
                    $post->post_id = $data['id'];
                    $post->status = $status;
                    $post->save();
                }
    
                return [
                    'success'   => true,
                    'media_id'  => $data['id'],
                    'media_key' => $data['media_key'],
                    'url'       => $media,
                    'state'     => $status,
                ];
            }
        }
    
        /**
         * ====================================
         * IMAGE / DOCUMENT UPLOAD
         * ====================================
         */
        if (!$isVideo) {
            $response = Http::timeout(300)->get($url);
            
            if (!$response->successful()) {
                return $this->errorResponse($post, $response);
            }
    
            $fileContents = $response->body();

            $upload = Http::withToken($account->access_token)
                ->attach('media', $fileContents, $fileName)
                ->post($this->baseUrl . 'media/upload', [
                    'media_category' => 'tweet_image',
                ]);
    
            if (!$upload->successful()) {
                return $this->errorResponse($post, $upload);
            }
    
            return [
                'success'   => true,
                'url'       => $url,
                'media_id'  => data_get($upload->json(), 'data.id'),
                'media_key' => data_get($upload->json(), 'data.media_key'),
            ];
        }
    
        /**
         * ====================================
         * VIDEO UPLOAD (CHUNKING)
         * ====================================
         */
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'x_video_');
    
            $response = Http::timeout(600)
                ->withOptions(['sink' => $tempFile])
                ->get($url);
                
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                return [
                    'success' => false,
                    'message' => 'Unable to download video from S3',
                ];
            }
    
            $fileSize = $media->file_size;
    
            $mimeType = match ($extension) {
                'mov' => 'video/quicktime',
                'avi' => 'video/x-msvideo',
                default => 'video/mp4',
            };
    
            $initializeResponse = Http::withHeaders($authHeaders)
                ->post( $this->baseUrl . 'media/upload/initialize', [
                    'media_category' => 'tweet_video',
                    'media_type'     => $mimeType,
                    'total_bytes'    => $fileSize,
                ]);
    
            if (!$initializeResponse->successful()) {
                return $this->errorResponse($post, $initializeResponse);
            }
            
            $mediaId = data_get($initializeResponse->json(), 'data.id');
            $mediaKey = data_get($initializeResponse->json(), 'data.media_key');
    
            $handle = fopen($tempFile, 'rb');
            $segmentIndex = 0;
    
            while (!feof($handle)) {
                $chunk = fread($handle, 4 * 1024 * 1024);
    
                $appendResponse = Http::withToken($account->access_token)
                    ->attach('media', $chunk, $fileName)
                    ->post($this->baseUrl . "/media/upload/{$mediaId}/append", [
                        'segment_index' => $segmentIndex,
                    ]);
    
                if (!$appendResponse->successful()) {
                    fclose($handle);
                    return $this->errorResponse($post, $appendResponse);
                }
    
                $segmentIndex++;
            }
            fclose($handle);
           
            $finalize = Http::withToken($account->access_token)
            ->withBody('{}', 'application/json')
            ->post("https://api.x.com/2/media/upload/{$mediaId}/finalize");

            if (!$finalize->successful()) {
                return $this->errorResponse($post, $finalize);
            }
            
            $finalResponse = $finalize->json()['data'] ?? [];
  
            if (!empty($finalResponse)) {
                $status = $finalResponse['processing_info']['state'];
                if (in_array($status, ['pending', 'in_progress'])) {
                    $post->post_id = $finalResponse['id'];
                    $post->status = $status;
                    $post->save();
    
                    return [
                        'success'   => true,
                        'media_id'  => $mediaId,
                        'media_key' => $mediaKey,
                        'url'       => $url,
                        'state'     => $status,
                    ];
                }
            }
    
            $state = 'pending';
            $attempts = 0;
    
            do {
                sleep(2);
                $status = Http::withHeaders($authHeaders)->get($this->baseUrl . "/media/upload/{$mediaId}");
    
                if (!$status->successful()) {
                    return $this->errorResponse($post, $status);
                }
    
                $processingInfo = data_get($status->json(), 'processing_info');
                $state = data_get($processingInfo, 'state', 'succeeded');
                $attempts++;
            } while (in_array($state, ['pending', 'in_progress']) && $attempts < 15);
    
            return [
                'success'   => true,
                'media_id'  => $mediaId,
                'media_key' => $mediaKey,
                'url'       => $url,
                'state'     => $state,
            ];
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Get posts for a page
     */
    public function getPosts($pageId, $pageToken)
    {
        $endpoint = "https://api.x.com/2/users/{$pageId}/tweets";

        $response = $this->api->request(
            'get',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $pageToken
            ],
            []
        );

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $response->json()['detail'] ?? 'Unknown error'
            ];
        }

        return [
            'success' => true,
            'data' => $response->json()['data'] ?? []
        ];
    }

    /**
     * Delete a post
     */
    public function destroy($post)
    {

        $this->ensureValidToken($post);
        $endpoint = 'https://api.x.com/2/tweets/' . $post->post_id;

        $response = $this->api->request(
            'delete',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $post->postAccount->access_token,
                'Content-Type' => 'application/json'
            ],
            []
        );

        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $response->json()['data'] ?? []
        ];
    }

/**
     * Publish a comment/reply to X (Twitter)
     */
    public function publishComment($data, $comment)
    {
        $this->ensureValidToken($comment->post);
        
        $endpoint = 'https://api.x.com/2/tweets';
        
        $payload = [
            'text' => $data['body'] ?? '',
            'reply' => [
                'in_reply_to_tweet_id' => $comment->comment_id
            ]
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $comment->postAccount->access_token,
                'Content-Type' => 'application/json'
            ],
            $payload,
            'json'
        );

        if (!$response->successful()) {
            return $this->errorResponse($comment, $response);
        }

        $responseData = $response->json();
        $tweetId = $responseData['data']['id'] ?? null;

        return $this->storeComment($comment, $data, $tweetId);
    }

    /**
     * Store comment in database
     */
    private function storeComment($comment, $data, $commentId)
    {
        $createdComment = PostComment::create([
            'content'           => $data['body'] ?? '',
            'sender_type'       => 'support',
            'platform'          => 'x', // Fixed platform name (was tiktok)
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
     * Delete a comment
     */
    public function destroyComment($chat)
    {
        $this->ensureValidToken($chat->postAccount);
        $endpoint = 'https://api.x.com/2/tweets/' . $chat->comment_id;

        $response = $this->api->request(
            'delete',
            $endpoint,
            [
                'Authorization' => 'Bearer ' . $chat->postAccount->access_token,
                'Content-Type' => 'application/json'
            ],
            []
        );

        if (!$response->successful()) {
            return $this->errorResponse($response);
        }

        return [
            'success' => true,
            'data' => $response->json()['data'] ?? []
        ];
    }

    /**
     * Error response handler
     */
    private function errorResponse($model, $response)
    {
        $data = $response->json() ?? $response;
        $message = $data['detail'] ?? $data['error_description'] ?? $data['error'] ?? $data['title'] ?? 'Unknown error';
        $model->status = 'failed';
        $model->error_message = $message ?? 'Unknown error';
        $model->save();

        return [
            'success' => false,
            'status' => $response->status(),
            'message' => $message ?? 'Unknown error'
        ];
    }
}
