<?php

namespace App\Services\PostServices;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SocialAccount;
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
        $this->baseUrl = adminSetting('posts.linkedin.base_url') ?: 'https://api.linkedin.com/rest/';
    }

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
            'expires_at'    => now()->addSeconds($tokenData['expires_in']),
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

        if (isset($data['uploaded_media'])) {
            // Already uploaded once by PostController::quickStore() and
            // shared across every platform in this submission - see
            // uploadQuickPostMedia()'s docblock.
            $uploadResult = ['success' => true, 'media' => $data['uploaded_media']];
        } elseif (!empty($data['ai_image_url'])) {
            $mediaExtension = strtolower(pathinfo(
                parse_url($data['ai_image_url'], PHP_URL_PATH),
                PATHINFO_EXTENSION
            ));
        } else {
            $uploadResult = $this->uploadMediaToS3($data['media'] ?? []);
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
                            'platform' => 'linkedin',
                            'post_id' => $post->id,
                            'platform' => 'linkedin',
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

                // Dispatch job to process this post (without the file)
                //  ProcessFacebookPostJob::dispatch($post, $page)->onQueue('high');

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


    public function publishPost($post)
    {
        $owner = \App\Models\User::findOrFail($post->user_id);
        app(\App\Services\PackageAccessService::class)->authorize($owner, 'publishing');
        // Recheck pending legacy records too; the ledger prevents retry double charging.
        app(\App\Services\PackageUsageService::class)->reserve($owner, 'scheduled_published_posts', 1, 'post:'.$post->id);

        $this->ensureValidToken($post);
        $account = $post->socialAccount;
        $this->lastUploadError = null;
        // Build post payload handles multiple media processing
        $payload = $this->buildLinkedinPostPayload($account, $post);

        if (empty($payload)) {
            // Mark it failed (left pending it was retried every run) with
            // LinkedIn's own reason rather than a generic one.
            $message = 'LinkedIn media upload failed: ' . ($this->lastUploadError ?: 'no reason returned');
            $post->update(['status' => 'failed', 'error_message' => $message]);

            return ['success' => false, 'message' => $message];
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
        $post->post_url = $publishId ? self::permalink($publishId) : $post->post_url;
        $post->error_message = '';
        $post->status = 'completed';
        $post->save();

        return ['success' => true];
    }

    protected function buildLinkedinPostPayload($account, $post): array
    {
        $payload = [
            'author' => 'urn:li:organization:' . $account->platform_account_id,
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
                    $this->lastUploadError ??= $uploadResult['message'] ?? null;
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
    /** LinkedIn's reason for the last failed media upload (see publishPost()). */
    protected ?string $lastUploadError = null;

    /** "403 - Not enough permissions to access ..." from a LinkedIn error response. */
    private function linkedinError($response, string $what): string
    {
        $detail = $response->json('message') ?? $response->json('error_description') ?? \Illuminate\Support\Str::limit(trim($response->body()), 200);

        return "{$what} (HTTP {$response->status()}" . ($detail ? ": {$detail}" : '') . ')';
    }

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
                   'owner' => 'urn:li:organization:' . $account->platform_account_id,
               ]
           ]);

           if (!$initResponse->successful()) {
               Log::error('LinkedIn image init failed', [
                   'response' => $initResponse->body()
               ]);
               $this->lastUploadError = $this->linkedinError($initResponse, 'image upload could not start');
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
               $this->lastUploadError = $this->linkedinError($uploadFileResponse, 'image upload was rejected');
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
                   'owner' => 'urn:li:organization:' . $account->platform_account_id,
                   'fileSizeBytes' => $fileSize,
                   'uploadCaptions' => false,
                   'uploadThumbnail' => false,
               ]
           ]);
          
           if (!$initResponse->successful()) {
               Log::error('LinkedIn video init failed', [
                   'response' => $initResponse->body()
               ]);
               $this->lastUploadError = $this->linkedinError($initResponse, 'video upload could not start');
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
            'actor' => $comment->socialAccount->platform_account_id, // e.g., 'urn:li:person:XXXX' or 'urn:li:organization:XXXX'
            'message' => [
                'text' => $data['body']
            ]
        ];

        $response = $this->api->request(
            'post',
            $endpoint,
            [
                'Authorization' => "Bearer {$comment->socialAccount->access_token}",
                'Content-Type'  => 'application/json',
                'LinkedIn-Version' => $this->linkedinVersion,
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
            'social_account_id'   => $comment->socialAccount?->id
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
            $account = $post->socialAccount;

            $this->ensureValidToken($post);
   
            $response = Http::withToken($account->access_token)
                ->withHeaders([
                    'LinkedIn-Version' => $this->linkedinVersion,
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'X-RestLi-Method' => 'DELETE'
                ])
                ->delete("{$this->baseUrl}posts/" . urlencode($post->post_id));

            if (!$response->successful()) {
                return ['success' => false, 'status' => $response->status(), 'message' => $this->extractErrorMessage($response)];
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
     * Edit a published post's text (Posts API PARTIAL_UPDATE of commentary;
     * learn.microsoft.com/linkedin/marketing/community-management/shares/posts-api).
     * Media and the article link can't be changed.
     */
    public function editPublished(Post $post, array $data): array
    {
        $this->ensureValidToken($post);
        $response = Http::withToken($post->socialAccount->fresh()->access_token)
            ->withHeaders([
                'LinkedIn-Version' => $this->linkedinVersion,
                'X-Restli-Protocol-Version' => '2.0.0',
                'X-RestLi-Method' => 'PARTIAL_UPDATE',
            ])
            ->post("{$this->baseUrl}posts/" . urlencode($post->post_id), ['patch' => ['$set' => ['commentary' => (string) ($data['content'] ?? '')]]]);

        if (!$response->successful()) {
            return ['success' => false, 'status' => $response->status(), 'message' => $this->extractErrorMessage($response)];
        }

        return ['success' => true, 'status' => $response->status()];
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
     * Pulls the Organization's total data report (follower count, Page
     * views, follower gain/loss over time) and stores it on the
     * SocialAccount - same "connect-time report snapshot" shape as Meta/
     * Instagram's syncAccountStats(). Each call requires the
     * rw_organization_admin scope granted at redirectLinkedin(); LinkedIn
     * returns 403 on these endpoints for orgs without Page-admin-level
     * access even though posting/reading content still works, so each
     * fetch below is independently soft-failed rather than aborting the
     * whole sync.
     */
    public function syncAccountStats(SocialAccount $account): void
    {
        $orgUrn = 'urn:li:organization:' . $account->platform_account_id;
        $headers = [
            'Authorization' => 'Bearer ' . $account->access_token,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->linkedinVersion,
        ];

        $updates = [];
        $metadata = $account->metadata ?? [];
        $insights = $metadata['insights'] ?? [];

        // Total follower count - organizations/{id}'s old inline follower
        // count field was retired; networkSizes is the documented replacement.
        try {
            $followersResponse = Http::withHeaders($headers)
                ->get($this->baseUrl . 'networkSizes/' . urlencode($orgUrn), ['edgeType' => 'CompanyFollowedByMember']);

            if ($followersResponse->successful()) {
                $updates['followers_count'] = $followersResponse->json()['firstDegreeSize'] ?? $account->followers_count;
                $insights['followers'] = $followersResponse->json();
            } else {
                Log::warning('LinkedIn follower count fetch failed.', [
                    'account_id' => $account->id,
                    'error'      => $followersResponse->json()['message'] ?? $followersResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('LinkedIn follower count fetch threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }

        // Page views / clicks / unique visitors report.
        try {
            $pageStatsResponse = Http::withHeaders($headers)
                ->get($this->baseUrl . 'organizationPageStatistics', ['q' => 'organization', 'organization' => $orgUrn]);

            if ($pageStatsResponse->successful()) {
                $elements = $pageStatsResponse->json()['elements'] ?? [];
                $updates['views_count'] = $elements[0]['totalPageStatistics']['views']['allPageViews']['pageViews'] ?? $account->views_count;
                $insights['page_statistics'] = $elements;
            } else {
                // Expected unless the org has been approved for this
                // analytics surface - not a bug, same soft-fail as
                // Instagram's Insights fetch.
                Log::warning('LinkedIn page statistics fetch failed.', [
                    'account_id' => $account->id,
                    'error'      => $pageStatsResponse->json()['message'] ?? $pageStatsResponse->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('LinkedIn page statistics fetch threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }

        // Follower gain/loss over time (organic + paid) - kept in insights
        // only, there's no single-number column for it on post_accounts.
        try {
            $followerStatsResponse = Http::withHeaders($headers)
                ->get($this->baseUrl . 'organizationalEntityFollowerStatistics', ['q' => 'organizationalEntity', 'organizationalEntity' => $orgUrn]);

            if ($followerStatsResponse->successful()) {
                $insights['follower_statistics'] = $followerStatsResponse->json()['elements'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('LinkedIn follower statistics fetch threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
        }

        $metadata['insights'] = $insights;
        $updates['metadata'] = $metadata;
        $account->update($updates);
    }

    /**
     * Subscribe the Organization to LinkedIn's Organization Social Action
     * Notifications (LIKE, COMMENT, SHARE, COMMENT_DELETE ... on its posts):
     * PUT /rest/eventSubscriptions/(developerApplication:..,user:..,entity:..,
     * eventType:ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS) {"webhook": url}.
     * learn.microsoft.com/linkedin/marketing/community-management/organizations/organization-social-action-notifications
     *
     * LinkedIn requires: the app's webhook URL registered (and validated) in
     * the developer portal's Webhooks tab, the member to be an ADMINISTRATOR
     * of the Page and the rw_organization_admin permission. Until then this
     * records why it isn't subscribed; engagement is kept current by polling
     * (syncEngagement, posts:sync-engagement) either way.
     */
    public function subscribeToWebhooks(SocialAccount $account): void
    {
        $callback = route('comments.webhook.linkedin.receive');
        $appId = adminSetting('posts.linkedin.client_id');
        $result = ['subscribed' => false, 'reason' => 'not_configured'];

        if ($appId) {
            try {
                $result = $this->subscribeOrganization($account, (string) $appId, $callback);
            } catch (\Throwable $e) {
                Log::warning('LinkedIn webhook subscription threw.', ['account_id' => $account->id, 'error' => $e->getMessage()]);
                $result = ['subscribed' => false, 'reason' => 'error', 'error' => $e->getMessage()];
            }
        }

        $metadata = $account->metadata ?? [];
        $metadata['settings'] = array_merge($metadata['settings'] ?? [], [
            'webhook_callback_url' => $callback,
            'webhook_subscriptions' => $result['subscribed'],
            'webhook_status' => $result + ['checked_at' => now()->toIso8601String()],
        ]);
        $account->update(['metadata' => $metadata]);
    }

    /** @return array{subscribed: bool, reason?: string, error?: string, member?: string} */
    private function subscribeOrganization(SocialAccount $account, string $appId, string $callback): array
    {
        if (! $this->refreshAccountToken($account)) {
            return ['subscribed' => false, 'reason' => 'token_expired'];
        }
        $headers = $this->restHeaders($account);
        $orgUrn = 'urn:li:organization:' . $account->platform_account_id;

        // The connected member's person URN: their ADMINISTRATOR role on this Page.
        $acls = Http::withHeaders($headers)->get($this->baseUrl . 'organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED']);
        $member = collect($acls->json('elements') ?? [])->first(fn ($row) => ($row['organization'] ?? null) === $orgUrn)['roleAssignee'] ?? null;
        if (! $acls->successful() || ! $member) {
            return ['subscribed' => false, 'reason' => 'not_admin', 'error' => $acls->json('message')];
        }

        $key = '(developerApplication:' . rawurlencode('urn:li:developerApplication:' . $appId)
            . ',user:' . rawurlencode($member)
            . ',entity:' . rawurlencode($orgUrn)
            . ',eventType:ORGANIZATION_SOCIAL_ACTION_NOTIFICATIONS)';
        $response = Http::withHeaders($headers + ['Content-Type' => 'application/json'])
            ->put($this->baseUrl . 'eventSubscriptions/' . $key, ['webhook' => $callback]);

        if ($response->successful()) {
            return ['subscribed' => true, 'member' => $member];
        }
        Log::warning('LinkedIn webhook subscription refused.', ['account_id' => $account->id, 'status' => $response->status(), 'error' => $response->json('message') ?? $response->body()]);

        return ['subscribed' => false, 'reason' => in_array($response->status(), [401, 403], true) ? 'missing_permission' : 'refused', 'error' => $response->json('message')];
    }

    /**
     * Re-read one published post's engagement. Likes and comments come from
     * socialActions (r_organization_social - matches the LinkedIn feed);
     * impressions, unique impressions, clicks and shares from
     * organizationalEntityShareStatistics (rw_organization_admin, organic
     * only). Each part is optional: a 403 on one keeps what the other gave
     * and records why in platform_metadata.engagement_errors. LinkedIn
     * reports no "views" for organic posts in these APIs - impressions are
     * its reach metric - so views is left as it is rather than guessed.
     * Never changes the post's status (a live post isn't "failed" because
     * a read failed).
     */
    public function syncEngagement(Post $post): bool
    {
        $account = $post->socialAccount;
        if (! $post->post_id || ! $account?->access_token) {
            return false;
        }

        $errors = [];
        $fill = [];
        if (! $this->refreshAccountToken($account)) {
            $this->recordSync($post, [], ['token' => 'LinkedIn connection expired - reconnect LinkedIn in Connections.']);

            return false;
        }
        $headers = $this->restHeaders($account);

        try {
            $actions = Http::withHeaders($headers)->get($this->baseUrl . 'socialActions/' . rawurlencode($post->post_id));
            if ($actions->successful()) {
                $fill['likes'] = (int) ($actions->json('likesSummary.totalLikes') ?? 0);
                $fill['comments'] = (int) ($actions->json('commentsSummary.aggregatedTotalComments') ?? $actions->json('commentsSummary.totalFirstLevelComments') ?? 0);
            } else {
                $errors['social_actions'] = $this->readError($actions, 'r_organization_social / r_organization_social_feed');
            }
        } catch (\Throwable $e) {
            $errors['social_actions'] = $e->getMessage();
        }

        try {
            $stats = $this->shareStatistics($account, $post->post_id, $headers);
            if (isset($stats['error'])) {
                $errors['statistics'] = $stats['error'];
            } elseif ($stats !== []) {
                $fill += array_filter([
                    'likes' => $stats['likeCount'] ?? null,
                    'comments' => $stats['commentCount'] ?? null,
                ], fn ($v) => $v !== null);
                $fill['impressions'] = (int) ($stats['impressionCount'] ?? 0);
                $fill['reach'] = (int) ($stats['uniqueImpressionsCount'] ?? 0);
                $fill['clicks'] = (int) ($stats['clickCount'] ?? 0);
                $fill['shares'] = (int) ($stats['shareCount'] ?? 0);
            } else {
                // LinkedIn omits posts with no activity yet: zero, per its docs.
                $fill += ['impressions' => 0, 'reach' => 0, 'clicks' => 0, 'shares' => 0];
            }
        } catch (\Throwable $e) {
            $errors['statistics'] = $e->getMessage();
        }

        // Comment text is its own permission (r_organization_social_feed) -
        // read it even when the socialActions summary was refused.
        $commentsError = $this->fetchAndStoreComments($account, $post);
        if ($commentsError) {
            $errors['comments'] = $commentsError;
        }
        // The counts came from statistics: a refused summary isn't a problem then.
        if (isset($errors['social_actions']) && ! isset($errors['statistics']) && isset($fill['comments'])) {
            unset($errors['social_actions']);
        }
        $this->recordSync($post, $fill, $errors);

        return $fill !== [] || ! $commentsError;
    }

    /** totalShareStatistics of one post: [] when LinkedIn has none yet, ['error' => ..] on refusal. */
    private function shareStatistics(SocialAccount $account, string $postUrn, array $headers): array
    {
        $param = str_starts_with($postUrn, 'urn:li:ugcPost:') ? 'ugcPosts' : 'shares';
        // Restli 2.0: List() stays literal, the URNs inside are encoded.
        $url = $this->baseUrl . 'organizationalEntityShareStatistics?q=organizationalEntity'
            . '&organizationalEntity=' . rawurlencode('urn:li:organization:' . $account->platform_account_id)
            . '&' . $param . '=List(' . rawurlencode($postUrn) . ')';

        $response = Http::withHeaders($headers)->get($url);
        if (! $response->successful()) {
            return ['error' => $this->readError($response, 'rw_organization_admin')];
        }

        return $response->json('elements.0.totalShareStatistics') ?? [];
    }

    private function readError($response, string $scope): string
    {
        $message = $response->json('message') ?? $response->body();

        return in_array($response->status(), [401, 403], true)
            ? "LinkedIn refused ({$response->status()}): the connection lacks {$scope} or the member isn't a Page admin. {$message}"
            : "LinkedIn error {$response->status()}: {$message}";
    }

    private function recordSync(Post $post, array $fill, array $errors): void
    {
        if ($errors) {
            Log::info('LinkedIn engagement sync incomplete.', ['post_id' => $post->id, 'errors' => $errors]);
        }
        $post->forceFill($fill + [
            'platform_metadata' => array_merge($post->platform_metadata ?? [], [
                'engagement_synced_at' => now()->toIso8601String(),
                'engagement_errors' => $errors ?: null,
            ]),
        ])->save();
    }

    private function restHeaders(SocialAccount $account): array
    {
        return [
            'Authorization' => 'Bearer ' . $account->access_token,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->linkedinVersion,
        ];
    }

    /**
     * Refresh an expiring token without touching any post (ensureValidToken()
     * marks the post failed - right while publishing, wrong for a live post).
     */
    private function refreshAccountToken(SocialAccount $account): bool
    {
        if (! empty($account->expires_at) && Carbon::parse($account->expires_at)->gt(now()->addMinutes(5))) {
            return true;
        }
        if (empty($account->refresh_token)) {
            return false;
        }

        $response = $this->api->request('post', 'https://www.linkedin.com/oauth/v2/accessToken', [], [
            'client_id' => adminSetting('posts.linkedin.client_id'),
            'client_secret' => adminSetting('posts.linkedin.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ], 'form');
        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $account->update([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?? $account->refresh_token,
            'expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?? 3600)),
        ]);
        $account->refresh();

        return true;
    }

    public static function permalink(string $postUrn): string
    {
        return 'https://www.linkedin.com/feed/update/' . $postUrn;
    }

    /**
     * On connect, backfills the Organization's most recent posts (default
     * 5) along with each post's engagement summary and latest 5 comments,
     * so a newly connected account isn't empty until the customer
     * publishes something new through this app. Uses updateOrCreate
     * (rather than the "skip if a row already exists" pattern the
     * Instagram/Facebook equivalents use) so that reconnecting the same
     * Organization - eg. after a token expired and the user re-runs the
     * redirect/callback flow - refreshes existing posts' engagement
     * counts and comments instead of leaving stale data on file. Each
     * post/summary/comments call is independently failure-tolerant - one
     * bad post must not abort the rest of the batch.
     */
    public function backfillRecentPosts(SocialAccount $account, int $limit = 5): void
    {
        $orgUrn = 'urn:li:organization:' . $account->platform_account_id;
        $headers = [
            'Authorization' => 'Bearer ' . $account->access_token,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Linkedin-Version' => $this->linkedinVersion,
        ];

        $postsResponse = Http::withHeaders($headers)->get($this->baseUrl . 'posts', [
            'author' => $orgUrn,
            'q'      => 'author',
            'count'  => $limit,
            'sortBy' => 'LAST_MODIFIED',
        ]);

        if (!$postsResponse->successful()) {
            Log::warning('Failed to fetch LinkedIn posts for backfill.', [
                'account_id' => $account->id,
                'error'      => $postsResponse->json()['message'] ?? $postsResponse->body(),
            ]);
            return;
        }

        foreach ($postsResponse->json()['elements'] ?? [] as $item) {
            try {
                $postUrn = $item['id'];
                $commentary = $item['commentary'] ?? '';

                $summary = [];
                try {
                    $summaryResponse = Http::withHeaders($headers)->get($this->baseUrl . 'socialActions/' . urlencode($postUrn));
                    if ($summaryResponse->successful()) {
                        $summary = $summaryResponse->json();
                    }
                } catch (\Throwable $e) {
                    Log::warning('LinkedIn post engagement summary fetch threw.', ['post_urn' => $postUrn, 'error' => $e->getMessage()]);
                }

                $content = trim(strip_tags($commentary));
                $dotPosition = mb_strpos($content, '.');
                $title = ($dotPosition !== false && $dotPosition < 100)
                    ? mb_substr($content, 0, $dotPosition + 1)
                    : mb_substr($content, 0, 100);

                $post = Post::updateOrCreate(
                    ['social_account_id' => $account->id, 'post_id' => $postUrn],
                    [
                        'platform'          => 'linkedin',
                        'user_id'           => $account->user_id,
                        'title'             => $title,
                        'post_url'          => 'https://www.linkedin.com/feed/update/' . $postUrn,
                        'content'           => $commentary,
                        'likes'             => $summary['likesSummary']['totalLikes'] ?? 0,
                        'comments'          => $summary['commentsSummary']['totalFirstLevelComments'] ?? 0,
                        'status'            => 'completed',
                        'platform_metadata' => $item,
                    ]
                );

                $this->fetchAndStoreComments($account, $post, 5);
            } catch (\Throwable $e) {
                Log::warning('Failed to backfill a LinkedIn post.', ['account_id' => $account->id, 'post_id' => $item['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Import a post's first-level comments: GET /rest/socialActions/{urn}/comments
     * (learn.microsoft.com/linkedin/marketing/community-management/shares/comments-api).
     * The API documents no sort parameter - and Rest.li refuses parameters it
     * doesn't know, which is why comments never arrived while the counts did -
     * so the comments are paged with the standard start/count and kept newest
     * first here. updateOrCreate keeps re-runs idempotent; a comment SocialEaz
     * posted itself (stored under its comment URN) isn't duplicated.
     *
     * LinkedIn doesn't give third-party apps other members' names under the
     * Page permissions, so a member shows as "LinkedIn member"; the Page's own
     * comments show as the Page.
     *
     * @return string|null why the comments couldn't be read, null on success
     */
    private function fetchAndStoreComments(SocialAccount $account, Post $post, int $limit = 50): ?string
    {
        $url = $this->baseUrl . 'socialActions/' . rawurlencode((string) $post->post_id) . '/comments';
        $elements = [];

        try {
            for ($start = 0; $start < $limit; $start += 50) {
                $response = Http::withHeaders($this->restHeaders($account))->get($url, ['start' => $start, 'count' => min(50, $limit - $start)]);
                // Some API versions refuse paging parameters too: the first page without them.
                if ($response->status() === 400 && $start === 0) {
                    $response = Http::withHeaders($this->restHeaders($account))->get($url);
                }
                if (! $response->successful()) {
                    $error = $this->readError($response, 'r_organization_social_feed');
                    Log::warning('LinkedIn comments fetch failed.', ['post_id' => $post->id, 'status' => $response->status(), 'error' => $response->json('message') ?? $response->body()]);

                    return $error;
                }

                $page = $response->json('elements') ?? [];
                $elements = array_merge($elements, $page);
                if (count($page) < 50 || ! $response->json('paging.links')) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('LinkedIn comments fetch threw.', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return $e->getMessage();
        }

        $orgUrn = 'urn:li:organization:' . $account->platform_account_id;
        usort($elements, fn ($a, $b) => ($b['created']['time'] ?? 0) <=> ($a['created']['time'] ?? 0));

        foreach (array_slice($elements, 0, $limit) as $comment) {
            $id = (string) ($comment['id'] ?? '');
            if ($id === '' || ! empty($comment['parentComment'])) {
                continue;
            }
            $urn = $comment['commentUrn'] ?? $comment['$URN'] ?? null;
            $actor = (string) ($comment['actor'] ?? '');
            $own = $actor === $orgUrn;
            $existing = PostComment::where('post_id', $post->id)->whereIn('comment_id', array_filter([$id, $urn]))->first();

            $values = [
                'platform' => 'linkedin',
                'user_id' => $account->user_id,
                'social_account_id' => $account->id,
                'user_name' => $own ? ($account->name ?: 'Your Page') : ($this->actorName($comment) ?? 'LinkedIn member'),
                'content' => (string) ($comment['message']['text'] ?? ''),
                'likes' => (int) ($comment['likesSummary']['totalLikes'] ?? 0),
                'sender_type' => $own ? 'support' : 'customer',
                'is_reply' => false,
                'status' => 'approved',
                'posted_at' => isset($comment['created']['time']) ? Carbon::createFromTimestampMs($comment['created']['time']) : now(),
                'imported_at' => now(),
            ];

            if ($existing) {
                // A reply sent from SocialEaz keeps its author; only LinkedIn's figures change.
                $existing->update($existing->sender_type === 'support' && ! $own ? array_intersect_key($values, array_flip(['content', 'likes', 'imported_at'])) : $values);
            } else {
                PostComment::create(['comment_id' => $id, 'post_id' => $post->id] + $values);
            }
        }

        return null;
    }

    /** "First Last" when LinkedIn decorated the actor (actor~), else null. */
    private function actorName(array $comment): ?string
    {
        $person = $comment['actor~'] ?? null;
        if (! is_array($person)) {
            return null;
        }
        $first = $person['localizedFirstName'] ?? null;
        $last = $person['localizedLastName'] ?? (is_string($person['lastName'] ?? null) ? $person['lastName'] : null);
        $name = trim(implode(' ', array_filter([$first, $last], 'is_string')));

        return $name !== '' ? $name : ($person['localizedName'] ?? null);
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
