<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PostServices\TiktokCommentsService;
use Illuminate\Http\Request;

/** "TikTok comments" connect (TiktokCommentsService) - started from the Connection Hub. */
class TiktokCommentsController extends Controller
{
    public function __construct(private TiktokCommentsService $comments)
    {
    }

    public function redirect()
    {
        return $this->comments->redirect();
    }

    public function callback(Request $request)
    {
        $result = $this->comments->handleCallback($request);

        return redirect()->to(route('admin.connections.index') . '#tiktok')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
