<?php

namespace App\Http\Controllers\Admin;

use App\Events\Messaging\MessageCreated;
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

        return view('admin.chats.dashboard', compact('conversations', 'activeConversation', 'messages'));
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
            Storage::disk('s3')->put($s3Path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);
            $mediaUrl = Storage::disk('s3')->url($s3Path);
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
