<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Models\PostComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Backs the navbar's notification center (unread Comments + Messages,
 * combined). Deliberately thin: unread *Messages* state already lives on
 * Conversation::unread_count (see ChatController::markRead() and the same
 * sum-query already used in PostController::dashboard() for the Posts
 * dashboard's own bell) - this controller doesn't duplicate that logic, it
 * just reads it. Only Comments needed a new read_at column, since none
 * existed before.
 */
class NotificationController extends Controller
{
    /**
     * Combined unread count + a merged, recency-sorted preview list for the
     * navbar dropdown.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $unreadComments = PostComment::where('user_id', $userId)->unread()->count();

        $conversationsQuery = fn () => Conversation::whereHas(
            'channel',
            fn ($q) => $q->where('user_id', $userId)
        );

        $unreadMessages = (int) $conversationsQuery()->sum('unread_count');

        $commentItems = PostComment::where('user_id', $userId)
            ->unread()
            ->latest()
            ->take(10)
            ->get()
            ->map(function (PostComment $comment) {
                return [
                    'type' => 'comment',
                    'id' => $comment->id,
                    'author' => $comment->user_name ?: 'Someone',
                    'avatar' => $comment->user_avatar_url,
                    'preview' => Str::limit($comment->content ?: '(no content)', 80),
                    'platform' => $comment->platform,
                    'url' => route('admin.posts.show', $comment->post_id) . '#comment-' . $comment->id,
                    'created_at' => $comment->created_at,
                ];
            });

        $conversationItems = $conversationsQuery()
            ->where('unread_count', '>', 0)
            ->latest('last_message_at')
            ->take(10)
            ->get()
            ->map(function (Conversation $conversation) {
                return [
                    'type' => 'conversation',
                    'id' => $conversation->id,
                    'author' => $conversation->customer_name ?: 'Someone',
                    'avatar' => $conversation->customer_avatar_url,
                    'preview' => $conversation->last_message_preview,
                    'platform' => $conversation->platform,
                    'url' => route('admin.chats.dashboard', ['conversation' => $conversation->id]),
                    'created_at' => $conversation->last_message_at,
                    'badge' => (int) $conversation->unread_count,
                ];
            });

        $items = $commentItems->concat($conversationItems)
            ->sortByDesc('created_at')
            ->values()
            ->take(15);

        return response()->json([
            'count' => $unreadComments + $unreadMessages,
            'items' => $items,
        ]);
    }

    /**
     * Marks a single comment read - the comment-notification equivalent of
     * ChatController::markRead() for conversations (that existing route is
     * reused as-is for conversation-type items; this is the new one
     * comments needed).
     */
    public function markCommentRead(PostComment $comment): JsonResponse
    {
        abort_unless($comment->user_id === Auth::id(), 403);

        $comment->markAsRead();

        return response()->json(['success' => true]);
    }
}
