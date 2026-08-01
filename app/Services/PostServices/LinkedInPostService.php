<?php

namespace App\Services\PostServices;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use getID3;
use App\Models\PostComment;

class LinkedInPostService
{
    protected string $linkedinVersion = '202606';

    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.linkedin.base_url');
    }

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

        $clientId = adminSetting('posts.linkedin.client_id');
        $clientSecret = adminSetting('posts.linkedin.client_secret');
        $endpoint = 'https://www.linkedin.com/oauth/v2/accessToken';
        $payload = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ];

        $response = $this->api->request('post', $endpoint, [], $payload, 'form');
    
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
                    'platform' => 'linkedin',
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
                            'platform' => 'linkedin',
                            'post_id' => $post->id,
                            'platform' => 'linkedin',
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
        $this->ensureValidToken($post);
        $account = $post->postAccount;
        // Build post payload handles multiple media processing
        $payload = $this->buildLinkedinPostPayload($account, $post);

        if (empty($payload)) {
            return ['success' => false, 'message' => 'Failed to generate post payload or media upload failed'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $account->access_token,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->linkedinVersion,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . "posts", $payload);
       
        if (!$response->successful()) {
            return $this->errorResponse($post, $response, $account->platform);
        }

        $publishId = $response->header('x-restli-id');

        $post->post_id = $publishId;
        $post->error_message = '';
        $post->status = 'completed';
        $post->save();

        return ['success' => true];
    }

    protected function buildLinkedinPostPayload($account, $post): array
    {
        $payload = [
            'author' => 'urn:li:organization:' . $account->account_id,
            'commentary' => $post->content,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        $content = trim($post->content);
        $dotPosition = mb_strpos($content, '.');
        $title = ($dotPosition !== false && $dotPosition < 100)
            ? mb_substr($content, 0, $dotPosition + 1)
            : mb_substr($content, 0, 100);

        // 1. Ensure media collection is not empty
        if (!empty($post->media) && count($post->media) > 0) {
            $uploadedUrns = [];

            // Loop through each media entry in the array
            foreach ($post->media as $eachMedia) {
                // Using the properties you specified: media_url and media_type
                $mediaUrl = $eachMedia->media_url; 
                
                $extension = strtolower(
                    pathinfo(parse_url($mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION)
                );
                $fileName = basename(parse_url($mediaUrl, PHP_URL_PATH));
                $s3Path = "uploads/linkedin/media/{$fileName}";

                $mediaResult = [
                    'file' => $mediaUrl,
                    'extension' => $extension,
                    's3_path' => $s3Path,
                    's3_url' => $mediaUrl
                ];

                // Trigger upload method per sequential file
                $uploadResult = $this->uploadMediaToLinkedIn($account, $mediaResult, $post);

                if ($uploadResult['success'] && !empty($uploadResult['urns'])) {
                    // Collect the returned asset URN (e.g. urn:li:image:123 or urn:li:video:123)
                    $uploadedUrns[] = $uploadResult['urns'][0]; 
                } else {
                    // Fail early if an asset fails uploading
                    return []; 
                }
            }

            // 2. Format the payload structural body depending on item count
            if (count($uploadedUrns) === 1) {
                // Standard Single Media Upload (Image or Video)
                $payload['content'] = [
                    'media' => [
                        'id' => $uploadedUrns[0],
                        'title' => $title,
                    ]
                ];
            } elseif (count($uploadedUrns) > 1) {
                // LinkedIn Multi-Image Upload Setup (2 to 20 images)
                $imagesArray = [];
                foreach ($uploadedUrns as $urn) {
                    $imagesArray[] = [
                        'id' => $urn
                    ];
                }

                $payload['content'] = [
                    'multiImage' => [
                        'images' => $imagesArray
                    ]
                ];
            }
        }

        return $payload;
    }

    /**
     * Upload image/video to LinkedIn
     */
    protected function uploadMediaToLinkedIn($account, $mediaResult, $post): array
    {
        try {
            $s3Url = $mediaResult['s3_url']; 
            $extension = $mediaResult['extension'];
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            
            // 1. FIX: Download the current media file, NOT the global $post->media array
            $downloadResponse = Http::timeout(300)->get($s3Url);
            if (!$downloadResponse->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to download asset from source URL: ' . $s3Url
                ];
            }

            $fileContent = $downloadResponse->body();
            
            // 2. FIX: Dynamically read mime-type and content-length for this specific file
            $mimeType = $downloadResponse->header('Content-Type') ?? ($isImage ? 'image/jpeg' : 'video/mp4');
            $fileSize = strlen($fileContent);
          
            $urn = $isImage
                ? $this->uploadLinkedinImage($account, $fileContent, $mimeType, $fileSize, $account->access_token, $mediaResult)
                : $this->uploadLinkedinVideo($account, $fileContent, $mimeType, $fileSize, $account->access_token, $mediaResult);

            if (!$urn) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload media item to LinkedIn'
                ];
            }

            return [
                'success' => true,
                'urns' => [$urn]
            ];
        } catch (\Exception $e) {
            Log::error('Upload media to LinkedIn failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

   /**
    * Upload image to LinkedIn
    */
   protected function uploadLinkedinImage($account, $fileContent, string $mimeType, int $fileSize, string $accessToken, $mediaResult)
   {
       try {
           $initResponse = Http::withHeaders([
               'Authorization' => 'Bearer ' . $accessToken,
               'X-Restli-Protocol-Version' => '2.0.0',
               'Linkedin-Version' => $this->linkedinVersion,
           ])->post($this->baseUrl . "images?action=initializeUpload", [
               'initializeUploadRequest' => [
                   'owner' => 'urn:li:organization:' . $account->account_id,
               ]
           ]);

           if (!$initResponse->successful()) {
               Log::error('LinkedIn image init failed', [
                   'response' => $initResponse->body()
               ]);
               return null;
           }

           $uploadData = $initResponse->json('value');
           $uploadUrl = $uploadData['uploadUrl'];
           $imageUrn = $uploadData['image'];

           $uploadFileResponse = Http::withHeaders([
               'Authorization' => 'Bearer ' . $accessToken,
               'Content-Type' => $mimeType,
           ])->withBody($fileContent, $mimeType)->put($uploadUrl);

           if (!$uploadFileResponse->successful()) {
               Log::error('LinkedIn image upload failed', [
                   'response' => $uploadFileResponse->body()
               ]);
               return null;
           }

           return $imageUrn;
       } catch (\Exception $e) {
           Log::error('LinkedIn image upload exception', [
               'error' => $e->getMessage()
           ]);
           return null;
       }
   }

   /**
    * Upload video to LinkedIn
    */
   protected function uploadLinkedinVideo($account, $fileContent, string $mimeType, int $fileSize, string $accessToken, $mediaResult)
   {
       try {
           $initResponse = Http::withHeaders([
               'Authorization' => 'Bearer ' . $accessToken,
               'X-Restli-Protocol-Version' => '2.0.0',
               'Linkedin-Version' => $this->linkedinVersion,
           ])->post($this->baseUrl . "videos?action=initializeUpload", [
               'initializeUploadRequest' => [
                   'owner' => 'urn:li:organization:' . $account->account_id,
                   'fileSizeBytes' => $fileSize,
                   'uploadCaptions' => false,
                   'uploadThumbnail' => false,
               ]
           ]);
          
           if (!$initResponse->successful()) {
               Log::error('LinkedIn video init failed', [
                   'response' => $initResponse->body()
               ]);
               return null;
           }

           $uploadData = $initResponse->json('value');
           $uploadInstructions = $uploadData['uploadInstructions'];
           $videoUrn = $uploadData['video'];
           $uploadToken = $uploadData['uploadToken'] ?? '';

           $uploadedPartIds = [];
           foreach ($uploadInstructions as $instruction) {
               $partUrl = $instruction['uploadUrl'];
               $firstByte = $instruction['firstByte'];
               $lastByte = $instruction['lastByte'];

               $chunk = substr($fileContent, $firstByte, $lastByte - $firstByte + 1);

               $uploadPartResponse = Http::withHeaders([
                   'Authorization' => 'Bearer ' . $accessToken,
                   'Content-Type' => 'application/octet-stream',
               ])->withBody($chunk, 'application/octet-stream')->put($partUrl);

               if (!$uploadPartResponse->successful()) {
                   Log::error('LinkedIn video part upload failed', [
                       'response' => $uploadPartResponse->body(),
                       'part' => $firstByte . '-' . $lastByte
                   ]);
                   return null;
               }

               // LinkedIn API expects the ETag value enclosed in quotes or clean string
               $uploadedPartIds[] = $uploadPartResponse->header('ETag');
           }

           $finalizeResponse = Http::withHeaders([
               'Authorization' => 'Bearer ' . $accessToken,
               'X-Restli-Protocol-Version' => '2.0.0',
               'Linkedin-Version' => $this->linkedinVersion,
           ])->post("https://api.linkedin.com/rest/videos?action=finalizeUpload", [
               'finalizeUploadRequest' => [
                   'video' => $videoUrn,
                   'uploadToken' => $uploadToken,
                   'uploadedPartIds' => $uploadedPartIds,
               ]
           ]);

           if (!$finalizeResponse->successful()) {
               Log::error('LinkedIn video finalize failed', [
                   'response' => $finalizeResponse->body()
               ]);
               return null;
           }

           return $videoUrn;
       } catch (\Exception $e) {
           Log::error('LinkedIn video upload exception', [
               'error' => $e->getMessage()
           ]);
           return null;
       }
   }


   /**
     * Publish comment reply on LinkedIn
     */
    public function publishComment($data, $comment)
    {
        $this->ensureValidToken($comment->post);
        
        // LinkedIn nested comments endpoint format: /rest/socialActions/{parentCommentUrn}/comments
        $parentCommentUrn = urlencode($comment->comment_id);
        $endpoint = $this->baseUrl . "/socialActions/{$parentCommentUrn}/comments";

        // LinkedIn expects actor URN and message text in JSON format
        $payload = [
            'actor' => $comment->postAccount->account_id, // e.g., 'urn:li:person:XXXX' or 'urn:li:organization:XXXX'
            'message' => [
                'text' => $data['body']
            ]
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Authorization' => "Bearer {$comment->postAccount->access_token}",
                'Content-Type'  => 'application/json',
                'LinkedIn-Version' => '202401', // Update to your target API version
                'X-Restli-Protocol-Version' => '2.0.0'
            ],
            $payload,
            'json'
        );

        if (!$response->successful()) {
            return $this->errorResponse($comment, $response);
        }

        // LinkedIn returns the new comment URN in the response header 'x-restli-id' or body
        $newCommentId = $response->header('x-restli-id') ?? $response->json()['id'] ?? null;

        return $this->storeComment($comment, $data, $newCommentId);
    }

    /**
     * Store comment
     */
    private function storeComment($comment, $data, $commentId)
    {
        $comment = PostComment::create([
            'content'           => $data['body'] ?? '',
            'sender_type'       => 'support',
            'platform'          => 'linkedin',
            'parent_comment_id' => $comment->id,
            'user_id'           => Auth::user()->id,
            'sender_name'       => Auth::user()->name,
            'post_id'           => $comment->post?->id,
            'is_read'           => 0,
            'is_reply'          => true,
            'user_name'         => 'support',
            'comment_id'        => $commentId,
            'post_account_id'   => $comment->postAccount?->id
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
    }

    /**
     * Extract error message from response
     */
    protected function extractErrorMessage($response): string
    {
        $data = $response->json();

        if (isset($data['errorDetails']['inputErrors'][0]['description'])) {
            return $data['errorDetails']['inputErrors'][0]['description'];
        }

        if (isset($data['errorDetails']['inputErrors'][0]['message'])) {
            return $data['errorDetails']['inputErrors'][0]['message'];
        }

        if (isset($data['message'])) {
            return $data['message'];
        }

        return 'LinkedIn API Error';
    }

    /**
     * Delete post
     */
    public function destroy($post): array
    {
        try {
            $account = $post->postAccount;

            $this->ensureValidToken($post);
   
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'LinkedIn-Version' => $this->linkedinVersion,
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'X-RestLi-Method' => 'DELETE'
                ])
                ->delete("{$this->baseUrl}posts/" . urlencode($post->account_id));
                   
            if (!$response->successful()) {
                return $this->errorResponse($post, $response);
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'message' => 'Post deleted successfully'
            ];
        } catch (\Exception $e) {
          
            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update post (Not supported by LinkedIn API)
     */
    public function updatePost(): array
    {
        return [
            'success' => false,
            'message' => 'LinkedIn API does not support editing posts',
        ];
    }

    public function getComments($postId, $account)
    {

        if (!$this->ensureValidToken($account)) {
            $errors = [
                'success' => false,
                'message' => 'Failed to refresh access token'
            ];
        }
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $account->access_token,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->linkedinVersion,
        ])->get("{$this->baseUrl}socialActions/" . urlencode($postId));


        $data = $response->json();
        dd($data, "{$this->baseUrl}socialActions/" . urlencode($postId));
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
     * Error response handler
     */
    protected function errorResponse($model, $response): array
    {
        $message = $this->extractErrorMessage($response);
        $model->status = 'failed';
        $model->error_message = $message ?? 'Unknown error';
        $model->save();
        
        return [
            'success' => false,
            'status' => $response->status(),
            'message' => $message,
            'response' => $response->json() ?? [],
        ];
    }
}
