<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PostComment;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\PostCommentRequest;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\GooglePostService;
use App\Services\PostServices\YoutubePostService;
use App\Services\PostServices\TiktokPostService;
use App\Services\PostServices\XPostService;
use App\Services\PostServices\LinkedInPostService;

class PostCommentController extends Controller
{
    protected $metaService, $instagramService, $googleService, $_config, $youtubeService, $tiktokService, $xService, $linkedinService, $nanoBananaAI;

    public function __construct(MetaPostService $metaService, InstagramPostService $instagramService, GooglePostService $googleService, YoutubePostService $youtubeService, TiktokPostService $tiktokService, XPostService $xService, LinkedInPostService $linkedinService)
    {
        $this->metaService       = $metaService;
        $this->instagramService  = $instagramService;
        $this->googleService     = $googleService;
        $this->youtubeService    = $youtubeService;
        $this->tiktokService     = $tiktokService;
        $this->xService          = $xService;
        $this->linkedinService   = $linkedinService;
     }

    public function dashboard()
    {
        return view('admin.comments.dashboard');
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostCommentRequest $request)
    {
        $userId = Auth::id();
        $validated = $request->validated();

        $results = [];
        $errors = [];
    
        if (!empty($validated['platforms'])) {
    
            try {
                $comment = PostComment::with(['postAccount', 'post'])->where(
                    'comment_id',
                    $validated['comment_id']
                )->first();
            
                

                switch ($comment->platform) {

                    case 'facebook':
                        $response = $this->metaService->publishComment($validated, $comment);
                        break;

                    case 'instagram':
                        $response = $this->instagramService->publishComment($validated, $comment);
                        break;

                    case 'google':
                        $response = $this->googleService->publishComment($validated, $comment);
                        break;

                    case 'youtube':
                        $response = $this->youtubeService->publishComment($validated, $comment);
                        break;

                    case 'x':
                        $response = $this->xService->publishComment($validated, $comment);
                        break;

                    case 'linkedin':
                        $response = $this->linkedinService->publishComment($validated, $comment);
                        break;

                    case 'tiktok':
                        $response = $this->tiktokService->publishComment($validated, $comment);
                        break;

                    default:
                        $errors[] = [
                            'message' => "Unsupported platform: {$comment->platform}"
                        ];

                        throw new \Exception("Unsupported platform");
                }

                if (!$response['success']) {
                
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
