<?php

namespace App\Http\Controllers\Admin;

use App\Events\Messaging\MessageCreated;
use App\Events\Messaging\MessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Services\MessagingServices\MessagingManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The unified messaging inbox - one thread list spanning every connected
 * Facebook Messenger/Instagram/WhatsApp/Telegram/X channel, with reply
 * sent back out through whichever platform the conversation belongs to
 * (MessagingManagerService resolves that per conversation, the same
 * dispatcher-by-platform pattern as SocialAdManagerService/
 * MessagingManagerService elsewhere in this app).
 */
class ChatController extends Controller
{
    public function __construct(protected MessagingManagerService $messagingManager)
    {
    }

    /**
     * Main inbox shell - the conversation list sidebar plus, if one is
     * selected (or defaulted to the most recent), its message thread.
     */
    public function dashboard(Request $request)
    {
        $conversations = Conversation::with('channel')
            ->whereHas('channel', fn($q) => $q->where('user_id', Auth::id()))
            ->orderByDesc('last_message_at')
            ->get();

        $activeConversation = $request->filled('conversation')
            ? $conversations->firstWhere('id', (int) $request->query('conversation'))
            : $conversations->first();

        $messages = collect();

        if ($activeConversation) {
            $messages = Message::with('attachments')
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at')
                ->get();

            $activeConversation->update(['unread_count' => 0]);
        }

        // Passed through rather than the view resolving MessagingManagerService
        // itself, so there's exactly one place (this service) that knows
        // which platforms support editing/deleting a sent message.
        $editCapablePlatforms = $this->messagingManager->editCapablePlatforms();
        $deleteCapablePlatforms = $this->messagingManager->deleteCapablePlatforms();

        return view('admin.chats.dashboard', compact(
            'conversations', 'activeConversation', 'messages',
            'editCapablePlatforms', 'deleteCapablePlatforms'
        ));
    }

    /**
     * AJAX: full message history for a thread, used when switching
     * conversations without a full page reload. Opening a thread implies
     * reading it, so this also clears its unread count.
     */
    public function show(Conversation $conversation)
    {
        abort_unless($conversation->channel->user_id === Auth::id(), 403);

        $conversation->update(['unread_count' => 0]);

        $messages = Message::with('attachments')
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success'      => true,
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    /**
     * AJAX: send a reply. Persists the outbound message locally first
     * (status "queued"), attempts delivery through the conversation's
     * platform, then reconciles the status - this way a failed send still
     * shows up in the thread (marked failed) rather than silently
     * vanishing, and every open inbox tab sees it via the same
     * MessageCreated broadcast inbound messages use.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'body'            => ['required_without:media', 'nullable', 'string'],
            'media'           => ['nullable', 'file', 'max:20480'],
        ]);

        $conversation = Conversation::with('channel')->findOrFail($validated['conversation_id']);

        abort_unless($conversation->channel->user_id === Auth::id(), 403);

        $mediaUrl = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : (str_starts_with($file->getMimeType(), 'audio/') ? 'audio' : 'image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $s3Path = "uploads/messaging/{$conversation->platform}/{$fileName}";
            Storage::disk('r2')->put($s3Path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);
            $mediaUrl = Storage::disk('r2')->url($s3Path);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'agent',
            'user_id'         => Auth::id(),
            'type'            => $mediaType ?? 'text',
            'body'            => $validated['body'] ?? null,
            'status'          => 'queued',
        ]);

        if ($mediaUrl) {
            $message->attachments()->create(['type' => $mediaType, 'url' => $mediaUrl]);
        }

        $result = $this->messagingManager->send($conversation, [
            'body'       => $validated['body'] ?? null,
            'media_url'  => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        if ($result['success'] ?? false) {
            $message->update([
                'status'               => 'sent',
                'sent_at'              => now(),
                'external_message_id'  => $result['external_message_id'] ?? null,
            ]);

            if (!empty($result['external_conversation_id'])) {
                $conversation->update(['external_conversation_id' => $result['external_conversation_id']]);
            }
        } else {
            $message->update(['status' => 'failed', 'error_message' => $result['error'] ?? 'Send failed.']);
        }

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => \Illuminate\Support\Str::limit($validated['body'] ?? ('[' . ucfirst($mediaType ?? 'file') . ']'), 120),
        ]);

        broadcast(new MessageCreated($message->load('attachments', 'conversation.channel')));

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $message,
            'error'   => $result['error'] ?? null,
        ]);
    }

    /**
     * Edits an already-sent outbound message through the conversation's
     * platform - only six of the twelve platforms this module supports
     * have an API for that at all (MessagingManagerService::supportsEdit()
     * is the single source of truth), so this fails cleanly with a clear
     * error for the other six rather than silently doing nothing. Local
     * state (body/edited_at) is only updated once the platform itself
     * confirms the edit succeeded - a rejected edit (eg. Telegram past
     * whatever time limit applies) leaves the original message intact
     * rather than showing edited text the customer never actually saw.
     */
    public function updateMessage(Request $request, Message $message)
    {
        abort_unless($message->conversation->channel->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        if ($message->direction !== 'outbound' || $message->status !== 'sent') {
            return response()->json(['success' => false, 'error' => 'Only a successfully sent message you sent can be edited.'], 422);
        }

        $result = $this->messagingManager->editMessage($message, $validated['body']);

        if (!($result['success'] ?? false)) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to edit message.'], 422);
        }

        $message->update(['body' => $validated['body'], 'edited_at' => now()]);
        $this->refreshPreviewIfLatest($message, $validated['body']);

        broadcast(new MessageUpdated($message));

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Deletes an already-sent outbound message from the platform side.
     * The local row is kept (its original body included) rather than
     * hard-deleted - deleted_at marks it as removed for display purposes
     * (the thread renders a "message deleted" placeholder instead) while
     * preserving it for any later audit/history need, the same reasoning
     * as destroy() keeping a "closed" conversation's history instead of
     * erasing it.
     */
    public function destroyMessage(Message $message)
    {
        abort_unless($message->conversation->channel->user_id === Auth::id(), 403);

        if ($message->direction !== 'outbound' || $message->status !== 'sent') {
            return response()->json(['success' => false, 'error' => 'Only a successfully sent message you sent can be deleted.'], 422);
        }

        $result = $this->messagingManager->deleteMessage($message);

        if (!($result['success'] ?? false)) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to delete message.'], 422);
        }

        $message->update(['deleted_at' => now()]);
        $this->refreshPreviewIfLatest($message, 'This message was deleted');

        broadcast(new MessageUpdated($message));

        return response()->json(['success' => true]);
    }

    /**
     * The sidebar's last-message preview is denormalized onto the
     * conversation for cheap listing - editing or deleting a message
     * only needs to touch it when that message is the most recent one in
     * the thread, otherwise the real latest message's preview is still
     * correct and shouldn't be overwritten.
     */
    private function refreshPreviewIfLatest(Message $message, string $preview): void
    {
        $latestMessageId = Message::where('conversation_id', $message->conversation_id)
            ->latest('created_at')
            ->value('id');

        if ($latestMessageId === $message->id) {
            $message->conversation->update(['last_message_preview' => \Illuminate\Support\Str::limit($preview, 120)]);
        }
    }

    /**
     * Archives a conversation - the message history is kept, it's just
     * hidden from the default open-conversations view (there's no
     * "delete a conversation" concept on any of these five platforms'
     * APIs anyway, so this only ever affects this app's local record).
     */
    public function destroy(Conversation $conversation)
    {
        abort_unless($conversation->channel->user_id === Auth::id(), 403);

        $conversation->update(['status' => 'closed']);

        return response()->json(['success' => true]);
    }

    public function markRead(Conversation $conversation)
    {
        abort_unless($conversation->channel->user_id === Auth::id(), 403);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['success' => true]);
    }
}
