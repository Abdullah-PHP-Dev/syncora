<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\PostRequest;
use App\Models\Post;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\GooglePostService;
use App\Services\PostServices\YoutubePostService;
use App\Services\PostServices\TiktokPostService;
use App\Services\PostServices\XPostService;
use App\Services\PostServices\LinkedInPostService;
use App\Models\PostCategory;
use App\Models\PostAccount;
use App\Models\PostMedia;
use App\Models\PostComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    protected $metaService, $instagramService, $googleService, $_config, $youtubeService, $tiktokService, $xService, $linkedinService, $nanoBananaAI;

    public function __construct(MetaPostService $metaService, InstagramPostService $instagramService, GooglePostService $googleService, YoutubePostService $youtubeService, TiktokPostService $tiktokService, XPostService $xService, LinkedInPostService $linkedinService)
    {
    //     session(['platform' => request()->platform ?? session('platform')]);
       
        $this->metaService = $metaService;
        $this->instagramService = $instagramService;
        $this->googleService = $googleService;
        $this->youtubeService = $youtubeService;
        $this->tiktokService = $tiktokService;
        $this->xService = $xService;
        $this->linkedinService = $linkedinService;
    //     $this->nanoBananaAI = $nanoBananaAI;
        $this->_config = request('_config');
     }
    /**
     * Display a listing of the resource.
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();
    
        // ---- Accounts ----
        $accounts = PostAccount::whereUserId($userId)->get();
        $totalAccounts = $accounts->count();
        $accountsByPlatform = $accounts->groupBy('platform')->map->count();
    
        // ---- Posts Query ----
        $postQuery = Post::where('user_id', $userId);
        // Status counts
        $totalPosts = (clone $postQuery)->count();
        $publishedPosts = (clone $postQuery)->where('status', 'published')->count();
        $scheduledPosts = (clone $postQuery)->where('status', 'scheduled')->count();
        $pendingPosts   = (clone $postQuery)->whereIn('status', ['pending', 'PROCESSING', 'IN_PROGRESS'])->count();
        $failedPosts    = (clone $postQuery)->where('status', 'failed')->count();
        $draftPosts     = (clone $postQuery)->where('status', 'draft')->count();
    
        // Engagement totals
        $totalLikes    = (clone $postQuery)->sum('likes');
        $totalComments = PostComment::whereHas('post', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
        $totalShares   = (clone $postQuery)->sum('shares');
        $totalViews    = (clone $postQuery)->sum('views');
        $totalEngagement = $totalLikes + $totalComments + $totalShares + $totalViews;
        
        // ---- Categories with post counts ----
        $categories = PostCategory::where('user_id', $userId)
            ->withCount(['posts' => function ($q) {
                $q->where('status', 'published');
            }])
            ->orderBy('id', 'desc')
            ->get();
        // ---- Monthly data for charts (last 7 months) ----
        $months = [];
        $monthlyPostCounts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M');
            $monthlyPostCounts[] = (clone $postQuery)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
        }
    
        // Compute month-over-month growth for the "Growth" card
        $growthPercent = 0;
        if (count($monthlyPostCounts) >= 2) {
            $prev = $monthlyPostCounts[count($monthlyPostCounts) - 2];
            $curr = $monthlyPostCounts[count($monthlyPostCounts) - 1];
            $growthPercent = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;
        }
    
        // ---- Recent comments (for Transactions list) ----
        $recentComments = PostComment::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
    
        // ---- Additional: total comments count (for Payments card) ----
        $totalCommentsAll = PostComment::where('user_id', $userId)->count();
    
        return view($this->_config['view'], compact(
            'totalAccounts',
            'accountsByPlatform',
            'totalPosts',
            'publishedPosts',
            'scheduledPosts',
            'pendingPosts',
            'failedPosts',
            'draftPosts',
            'totalLikes',
            'totalComments',
            'totalShares',
            'totalViews',
            'totalEngagement',
            'categories',
            'months',
            'monthlyPostCounts',
            'growthPercent',
            'recentComments',
            'totalCommentsAll'
        ));
    }
    public function index(Request $request)
    {
        $platform = strtolower($request->platform) ?? 'facebook';
        if ($request->platform === null) {
            $platform = session('platform');
        }
        
        $platform = $platform ?? 'facebook';
 
        $posts = Post::with(['postAccount', 'category', 'media'])
        ->where('user_id', Auth::user()->id)
        // ->where('platform', $platform)
        ->latest()
        ->paginate(10);
        
        return view('admin.posts.index_vue', compact('posts', 'platform'));
    }

	public function index_vue(Request $request)
    {
        $platform = strtolower($request->platform) ?? 'facebook';
        if ($request->platform === null) {
            $platform = session('platform');
        }

        $platform = $platform ?? 'facebook';

        $posts = Post::with(['postAccount', 'category', 'media'])
        ->where('user_id', Auth::user()->id)
        // ->where('platform', $platform)
        ->latest()
        ->paginate(10);

        return view('admin.posts.index_vue', compact('posts', 'platform'));
    }

    /**
     * Show a dedicated per-platform preview of a post, with a sidebar
     * to switch between the other connected platforms for the same post.
     */
    public function preview($postId, $platform)
    {
        return view('admin.posts.preview', [
            'postId' => $postId,
            'platform' => $platform,
        ]);
    }

    public function create(Request $request)
    {
        $userId = Auth::id();
        $socialPlatform = $request->platform ?? session('platform');
    
        $categories = PostCategory::where('user_id', $userId)
            ->withCount(['posts' => function ($q) {
                $q->where('status', 'published');
            }])
            ->orderBy('id', 'desc')
            ->get();
    
        $accounts = PostAccount::whereUserId($userId)->get();
     
        // Fetch scheduled posts (past and future) for this platform
        $scheduledPosts = Post::where('user_id', $userId)
            // ->where('platform', $platform)
            ->where('status', 'scheduled')
            ->orderBy('schedule_at', 'asc')
            ->get();
    
        // Fetch all media belonging to the user (across all posts)
        $userMedia = PostMedia::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    
        return view('admin.posts.create', compact('categories', 'accounts', 'socialPlatform', 'scheduledPosts', 'userMedia'));
    }

    public function store(PostRequest $request)
    {
        $userId = Auth::id();
        $validated = $request->validated();

        $results = [];
        $errors = [];
    
        if (!empty($validated['platforms'])) {
    
            try {
                DB::transaction(function () use (
                    $validated,
                    $userId,
                    &$results,
                    &$errors
                ) {
    
                    foreach ($validated['platforms'] as $platform) {
    
                        $pages = PostAccount::where([
                            'user_id' => $userId,
                            'platform' => $platform
                        ])->whereIn(
                            'id',
                            $validated['selected_pages'][$platform] ?? []
                        )->get();
                 
                        if ($pages->isEmpty()) {
                            $errors[] = [
                                'message' => "No pages found for platform: {$platform}"
                            ];
    
                            throw new \Exception("No pages found");
                        }
    
                        switch ($platform) {
    
                            case 'facebook':
                                $response = $this->metaService->store($validated, $pages);
                                break;
    
                            case 'instagram':
                                $response = $this->instagramService->store($validated, $pages);
                                break;
    
                            case 'google':
                                $response = $this->googleService->store($validated, $pages);
                                break;
    
                            case 'youtube':
                                $response = $this->youtubeService->store($validated, $pages);
                                break;
    
                            case 'x':
                                $response = $this->xService->store($validated, $pages);
                                break;
    
                            case 'linkedin':
                                $response = $this->linkedinService->store($validated, $pages);
                                break;
    
                            case 'tiktok':
                                $response = $this->tiktokService->store($validated, $pages);
                                break;
    
                            default:
                                $errors[] = [
                                    'message' => "Unsupported platform: {$platform}"
                                ];
    
                                throw new \Exception("Unsupported platform");
                        }
    
                        if (!$response['success']) {
                            DB::rollback();
                            if (isset($response['errors']) && is_array($response['errors'])) {
                                foreach ($response['errors'] as $error) {
                                    $errors[] = $error;
                                }
                            } else {
                                $errors[] = [
                                    'page_id'   => $pages->first()->external_id ?? null,
                                    'page_name' => $pages->first()->page_name ?? $pages->first()->name ?? null,
                                    'message'   => $response['message'] ?? 'Unknown error occurred'
                                ];
                            }
    
                            // This triggers automatic rollback
                            throw new \Exception($response['message'] ?? 'Publishing failed');
                        }
    
                        $results[] = $response['data'] ?? $response['results'] ?? [];
                    }
                });

                DB::commit();
    
            } catch (\Throwable $e) {
    
                return response()->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Posts published successfully!',
            'results' => $results,
            'redirect_url' => route('admin.posts.dashboard')
        ]);
    }

    public function destroy($postId)
    {
        $post = Post::with('postAccount', 'postComments' ,'media')->find($postId);
        $error = [];
        if (!$post) {
            return response()->json([
                'success' => false,
                'data' => 'Post not found'
            ], 404);
        }

        try {
            switch ($post->platform) {
                case 'facebook':
                    $response = $this->metaService->destroy($post);
                    break;
                case 'instagram':
                   
                    $response = $this->instagramService->destroy($post);
                    break;
                case 'x':
                
                    $response = $this->xService->destroy($post);
                    break;
                case 'google':
            
                    $response = $this->googleService->destroy($post);
                    break;
                case 'youtube':
        
                    $response = $this->youtubeService->destroy($post);
                    break;
                // case 'tiktok':
    
                //     $response = $this->tiktokService->destroy($post);
                //     break;
                case 'linkedin':
                    $response = $this->linkedinService->destroy($post);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'data' => "Unsupported platform: {$post->platform}"
                    ], 400);
            }
           
            // Check external API response
            if ((!$response || !$response['success']) && !in_array($response['status'], ['400', '404'])) {
                return response()->json([
                    'success' => false,
                    'data' => 'Failed to delete post from platform',
                    'error' => $response['message'] ?? $response['error']
                ], 500);
            }
      
            // Delete locally
            if (count($post->media)) {
                foreach ($post->media as $media) {
                    $path = parse_url($media->media_url, PHP_URL_PATH);
                    if ($path) {
                        $path = ltrim($path, '/'); 
                        Storage::disk('s3')->delete($path);
                    }
                }
            }
            $post->delete();
          
            return response()->json([
                'success' => true,
                'data' => 'Post deleted successfully'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($postId, Request $request)
    {
        $post = Post::with([
            'user',
            'postAccount',
           // 'category',
            'category',
            'media',
            'postComments' => function ($query) {
                // Fetch top-level parent comments and eager load nested replies with their authors
                $query->topLevel()->with(['replies.user', 'user']);
            }
        ])->findOrFail($postId);

        return view('admin.posts.show', compact('post'));
        // $post = Post::with('postAccount')->findOrFail($postId);
        // $socialPlatform = $post->postAccount->platform;
    
        // $post->load([
        //     'postComments',
        //     'category',
        // ]);

        // return view('admin.posts.show', compact(
        //     'post',
        //     'socialPlatform'
        // ));
    }

    public function getTypePost(Request $request) {
        $query = Post::with(['media', 'postAccount']) ->where('user_id', Auth::user()->id);
        $type = $request->type;

        switch ($type) {
            case 'scheduled':
                $query->where('schedule_mode', 1)
                    ->where('schedule_at', '<=', now());
                break;
        
            case 'published':
                $query->where('schedule_mode', 0);
                break;
        
            case 'failed':
                $query->where('status', 'failed');
                break;
        
            case 'pending':
                $query->where('status', 'pending');
                break;
        }
        
        $posts = $query->paginate(10);

        $categories = PostCategory::with(['postAccount', 'posts'])->where([
            'user_id' => Auth::user()->id
        ])->orderBy('id', 'desc')->paginate(50);

        return view($this->_config['view'], compact('posts', 'categories', 'type'));
    }

    /**
     * Generate Content using Gemini with media analysis
     */
    public function askOpenAI(Request $request)
    {
        set_time_limit(300);
        try {
            $prompt = $request->input('prompt');
            $mediaFile = $request->file('media');
        
            $generateImage = $request->input('generate_image', false);
            $mediaDescription = $request->input('media_description', '');

            $imageData = null;
            $mimeType = null;

            // Process media file if provided
            if ($mediaFile) {
                $mimeType = $mediaFile->getMimeType();
                $imageData = base64_encode(file_get_contents($mediaFile->getRealPath()));
            }
           
            // Generate content with Gemini - using the new method that returns structured text AND image
            $response = $this->nanoBananaAI->generateSocialMediaContentWithImage(
                $prompt,
                $imageData,
                $mimeType,
                $mediaDescription
            );
        //     $logos = $this->nanoBananaAI->generateImage($prompt, '16:9', $imageData, $mimeType);
        //   //  dd($logos);
        //     $response = $this->nanoBananaAI->generate($prompt, $imageData, $mimeType);
           // $response = $this->nanoBananaAI->generateImage($prompt, '16:9', $imageData, $mimeType);
      
            // Initialize parsed data
            $parsed = [
                'title' => '',
                'description' => '',
                'hashtags' => '',
                'media_type' => 'image',
                'media_description' => '',
                'media_prompt' => '',
                'media_analysis' => '',
                'generated_image_url' => null,
                'generated_image_data' => null,
                'generated_image_mime' => null
            ];
  
            // Extract all parts from response
            $parts = $this->nanoBananaAI->extractAllParts($response);
         
            $textContent = $parts['text'];
            $imageContent = $parts['image'];
            $imageMimeType = $parts['image_mime'];

            // Parse text content if available - this will extract Title, Description, Hashtags
            if ($textContent) {
                $parsedData = $this->parseGeminiResponse($textContent);
                $parsed = array_merge($parsed, $parsedData);
            }

            // If image was generated, upload to S3 and get URL
            if ($imageContent) {
                // Decode the base64 image data
                $imageBinaryData = base64_decode($imageContent);

                // Generate a unique filename
                $extension = $this->getExtensionFromMimeType($imageMimeType);
                $filename = 'ai-image/' . uniqid() . '.' . $extension;

                // Upload to S3
                Storage::disk('s3')->put($filename, $imageBinaryData, 'public');
                $imageUrl = Storage::disk('s3')->url($filename);
                
                $parsed['generated_image_url'] = $imageUrl;
                $parsed['generated_image_data'] = $imageContent;
                $parsed['generated_image_mime'] = $imageMimeType;
            }

            // If media was provided, return the media analysis
            if ($mediaFile) {
                $parsed['analyzed_media'] = [
                    'filename' => $mediaFile->getClientOriginalName(),
                    'mime_type' => $mediaFile->getMimeType(),
                    'size' => $mediaFile->getSize()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $parsed
            ]);
        } catch (\Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse Gemini response with media description
     */
    private function parseGeminiResponse($content)
    {
        $data = [
            'title' => '',
            'description' => '',
            'hashtags' => '',
            'media_type' => 'image',
            'media_description' => '',
            'media_prompt' => '',
            'media_analysis' => ''
        ];

        // If content is empty, return default values
        if (empty($content)) {
            return $data;
        }

        // Try to extract structured data
        // Extract Title
        if (preg_match('/Title:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $data['title'] = trim($matches[1]);
        }

        // Extract Description - handles multiline content
        if (preg_match('/Description:\s*(.+?)(?:\nHashtags:|\nMedia:|\nMediaDescription:|\Z)/is', $content, $matches)) {
            $data['description'] = trim($matches[1]);
        }

        // Extract Hashtags
        if (preg_match('/Hashtags:\s*(.+?)(?:\nMedia:|\nMediaDescription:|\Z)/is', $content, $matches)) {
            $data['hashtags'] = trim($matches[1]);
        }

        // Extract Media Type
        if (preg_match('/Media:\s*(image|video)/i', $content, $matches)) {
            $data['media_type'] = strtolower(trim($matches[1]));
        }

        // Extract Media Description
        if (preg_match('/MediaDescription:\s*(.+?)(?:\nTitle:|\nDescription:|\nHashtags:|\nMedia:|\Z)/is', $content, $matches)) {
            $data['media_description'] = trim($matches[1]);
        }

        // If no structured data found, try to intelligently parse
        if (empty($data['title']) && empty($data['description'])) {
            // Try to find a title (first line or line with colon)
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) < 80 && !str_contains($line, '#')) {
                    $data['title'] = $line;
                    break;
                }
            }

            // Use remaining content as description
            if (empty($data['description'])) {
                $data['description'] = $content;
            }
        }

        // If no media description found, use a fallback
        if (empty($data['media_description']) && !empty($data['description'])) {
            $data['media_description'] = substr($data['description'], 0, 150) . '...';
        }

        // Generate image prompt from description
        if (!empty($data['description'])) {
            $data['media_prompt'] = $this->createImagePrompt($data);
        }

        // Add media analysis
        if (!empty($data['media_description'])) {
            $data['media_analysis'] = $data['media_description'];
        }

        return $data;
    }

    /**
     * Generate Image using Gemini (standalone)
     */
    public function generateGeminiImage(Request $request)
    {
        set_time_limit(300);
        try {
            $prompt = $request->input('prompt');
            $aspectRatio = $request->input('aspect_ratio', '1:1');
            
            // Truncate prompt if too long
            if (strlen($prompt) > 2000) {
                $prompt = substr($prompt, 0, 2000);
            }
            
            // Call Gemini to generate image
            $response = $this->nanoBananaAI->generateImage($prompt, $aspectRatio);
            
            // Check if response has image data
            if (isset($response['candidates'][0]['content']['parts'])) {
                foreach ($response['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                        $imageData = $part['inlineData']['data'];
                        $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                        
                        // Upload to S3
                        $imageBinaryData = base64_decode($imageData);
                        $extension = $this->getExtensionFromMimeType($mimeType);
                        $filename = 'ai-image/' . uniqid() . '.' . $extension;
                        
                        Storage::disk('s3')->put($filename, $imageBinaryData, 'public');
                        $imageUrl = Storage::disk('s3')->url($filename);
                        
                        return response()->json([
                            'success' => true,
                            'image_data' => $imageData,
                            'image_url' => $imageUrl,
                            'mime_type' => $mimeType
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No image generated in response',
                'raw_response' => $response
            ], 500);

        } catch (\Exception $e) {
            \Log::error('generateGeminiImage Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file extension from mime type
     */
    private function getExtensionFromMimeType($mimeType)
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
        ];

        return $mimeMap[$mimeType] ?? 'png';
    }

    /**
     * Create image generation prompt from content
     */
    private function createImagePrompt($data)
    {
        $prompt = "Create a professional social media post image for: ";

        if (!empty($data['title'])) {
            $prompt .= $data['title'] . ". ";
        }

        if (!empty($data['media_description'])) {
            $prompt .= "The image should show: " . $data['media_description'] . ". ";
        }

        if (!empty($data['description'])) {
            $prompt .= substr($data['description'], 0, 100) . ". ";
        }

        $prompt .= "Style: Clean, modern, professional. High quality. Social media ready.";

        return $prompt;
    }
}