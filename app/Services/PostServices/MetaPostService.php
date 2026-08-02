<?php

namespace App\Services\PostServices;

use App\Services\PostServices\ApiPostService;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use getID3;

class MetaPostService
{
    protected $api, $baseUrl, $post, $media;

    public function __construct(ApiPostService $api, Post $post, PostMedia $media)
    {
        $this->api = $api;
        $this->media = $media;
        $this->post = $post;
        $this->baseUrl = adminSetting('posts.facebook.base_url');
    }

    protected function ensureValidToken($post)
    {
        $account = $post->postAccount;
        if (
            !empty($account->expires_in)
            && Carbon::parse($account->expires_in)->gt(now()->addMinutes(5))
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
            return $this->errorResponse($post, $response);
        }

        $tokenData = $response->json();

        $account->update([
            'access_token'       => $tokenData['access_token'],
            'refresh_token'      => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_in'    => now()->addSeconds($tokenData['expires_in'] ?? 3600),
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
                dd([
                    'title' => $data['title'] ?? Auth::user()->name,
                    'post_id' => null,
                    'platform' => 'facebook',
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
                // Create post record with status 'pending'
                $post = $this->post::create([
                    'title' => $data['title'] ?? Auth::user()->name,
                    'post_id' => null,
                    'platform' => 'facebook',
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
                            'platform' => 'facebook',
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
                dd($url);
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
        $account = $post->postAccount;
        if (!$this->ensureValidToken($post)) {
            $post->update([
                'status' => 'failed',
                'error_message' => 'Failed to refresh access token'
            ]);

            return ['success' => false];
        }

        $result = $this->publishPostOnMeta($post, $account);

        if ($result['success']) {
            $post->update([
                'post_id' => $result['post_id'],
                'status' => 'completed'
            ]);
            return ['success' => true];
        } else {
            $post->update([
                'status' => 'failed',
                'error_message' => $result['message']
            ]);
            return ['success' => false];
        }
     }

    /**
     * Publish to a single Facebook page
     */
    protected function publishPostOnMeta($post, $account)
    {
        $accountId = $account->account_id;
        $accessToken = $account->access_token;
        $endpoint = $this->baseUrl . $account->account_id . "/feed?access_token={$accessToken}";
        $payload = ['message' => $post->content];

        if (!empty($post->media)) {
            $media = $this->uploadMediaToMeta($post, $accessToken);
           
            if (!$media['success']) {
                $post->status = 'failed';
                $post->error_message = $media['message'] ?? $media['error'] ?? 'Facebook media publish faced an error.';  
                $post->save();

                return $media;
            }

            // If it was a video that published directly, complete the process here
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
        
        $response = $this->api->request('post', $endpoint, ['Content-Type' => 'application/json'], $payload, 'json');
       
        if (!$response->successful()) {
            return $this->errorResponse($post, $response);
        }
        
        $post->update([
            'post_id' => $response['id'],
            'status' => 'completed'
        ]);

        return ['success' => true];

    }

    private function uploadMediaToMeta($post, $accessToken) {
        $attachedMedia = [];
        $payload = [];
     
        foreach ($post->media as $key => $each) {
            // If it's an image, we upload it as unpublished to attach later
            if ($each->media_type == 'image') {
                $payload['published'] = false;
                $endpoint = $this->baseUrl . $post->postAccount->account_id . "/photos";
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
                $endpoint = $this->baseUrl . $post->postAccount->account_id . "/videos";
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
        $this->ensureValidToken($post);
        $endpoint = $this->baseUrl . $post->post_id;

        $response = $this->api->request(
            'delete',
            $endpoint . "?access_token={$post->postAccount->access_token}",
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

    public function updatePost($postId, $data, $token)
    {
        $account = SocialPost::with('mediaAccount')->wherePagePostId($postId)->first();
        $this->ensureValidToken($account);
        $url = '';
        $endpoint = '';
        $payload = [
            'message' => $data['content'],
        ];

        if (!empty($data['schedule_mode']) && $data['schedule_mode'] === 'on') {
            $payload['published'] = false;
            $payload['scheduled_publish_time'] = Carbon::parse($data['schedule_at'])->timestamp;
        }

        if (isset($data['media'])) {
            $extension = strtolower($data['media']->getClientOriginalExtension());
            $fileName = time() . '.' . $extension;
            $s3Path = "uploads/meta/media/{$fileName}";
            Storage::disk('s3')->put(
                $s3Path,
                file_get_contents($data['media']->getRealPath()),
                ['visibility' => 'public']
            );

            $url = Storage::disk('s3')->url($s3Path);

            if (isset($url)) {
                $endpoint = "https://graph.facebook.com/v25.0/{$postId}";
                $payload['url'] = $url;
            }
        } else if (isset($data['url'])) {
            $endpoint = "https://graph.facebook.com/v25.0/{$postId}";
            $payload['link'] = $data['url'];
        }

        $response = $this->api->request(
            'post',
            $endpoint . "?access_token={$token}",
            ['Content-Type' => 'application/json'],
            $payload,
            'form'
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

    public function publishComment($data, $comment)
    {
        $this->ensureValidToken($comment->post);
        $endpoint =  $this->baseUrl . $comment->comment_id . "/comments";

        $payload = [
            "message" => $data['body'] . ' --. '
        ];

        $response = $this->api->request(
            'post',
            $endpoint . "?access_token={$comment->postAccount->access_token}",
            [],
            $payload,
            'form'
        );

        if (!$response->successful()) {
            return $this->errorResponse($comment, $response);
        }

        return $this->storeComment($comment, $data, $response->json()['id']);
    }

    private function storeComment($comment, $data, $commentId)
    {
        $comment = PostComment::create([
            'content'            => $data['body'] ?? '',
            'sender_type'     => 'support',
            'platform'        => 'facebook',
            'parent_comment_id' => $data['comment_id'],
            'user_id'         => Auth::user()->id,
            'sender_name'     => Auth::user()->name,
            'post_id'   => $comment->post?->id,
            'is_read'         => 0,
            'is_reply' => true,
            'user_name'            => 'support',
            'comment_id' => $commentId,
            'post_account_id'  => $comment->postAccount?->id
        ]);

        return [
            'message' => '',
            'success' => true,
            'data'    => $comment
        ];
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

    private function errorResponse($model, $response)
    {
        $model->status = 'failed';
        $model->error_message = $response->json()['error']['message'] ?? 'Unknown error';
        $model->save();

        return [
            'success' => false,
            'message' => $response->json()['error']['message'] ?? 'Unknown error'
        ];
    }
}
