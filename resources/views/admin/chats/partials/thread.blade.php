{{-- Server-rendered initial thread view - mirrors renderThread()/renderMessage()
     in dashboard.blade.php's script exactly, since that JS replaces this same
     markup wholesale whenever the admin switches conversations. $platformLabels,
     $platformColors, $platformIcons, $editCapablePlatforms and
     $deleteCapablePlatforms are set by dashboard.blade.php (and, for the
     capability lists, ultimately by ChatController) and inherited here
     automatically since this partial is included, not rendered in isolation. --}}
<div class="thread-header">
    <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
    <div>
        <div class="fw-semibold">{{ $conversation->customer_name ?: 'Unknown' }}</div>
        <span class="platform-badge" style="background:{{ $platformColors[$conversation->platform] ?? '#6d28d9' }}">{{ $platformLabel($conversation->platform) }}</span>
    </div>
</div>
<div class="thread-messages" id="threadMessages">
    @foreach ($messages as $message)
        <div class="message-row {{ $message->direction }}" data-message-id="{{ $message->id }}">
            <div>
                @if ($message->deleted_at)
                    <div class="message-bubble is-deleted">
                        <i class="bx bx-block"></i> This message was deleted
                    </div>
                @else
                    <div class="message-bubble {{ $message->status === 'failed' ? 'failed' : '' }}" data-message-body="{{ $message->body }}">
                        @foreach ($message->attachments as $attachment)
                            <div class="message-attachment">
                                @if ($attachment->type === 'image')
                                    <img src="{{ $attachment->url }}">
                                @elseif ($attachment->type === 'video')
                                    <video src="{{ $attachment->url }}" controls></video>
                                @else
                                    <a href="{{ $attachment->url }}" target="_blank">📎 Attachment</a>
                                @endif
                            </div>
                        @endforeach
                        @if ($message->body){{ $message->body }}@endif
                    </div>
                @endif
                <div class="message-meta text-{{ $message->direction === 'outbound' ? 'end' : 'start' }}">
                    <span class="message-meta-text">
                        {{ $message->created_at->diffForHumans(null, true) }}@if ($message->status === 'failed') · failed to send @endif @if ($message->edited_at) · edited @endif
                    </span>
                    @if (!$message->deleted_at && $message->direction === 'outbound' && $message->status === 'sent' && (in_array($conversation->platform, $editCapablePlatforms) || in_array($conversation->platform, $deleteCapablePlatforms)))
                        <span class="message-actions">
                            @if (in_array($conversation->platform, $editCapablePlatforms))
                                <button type="button" class="message-action-btn" data-action="edit" title="Edit"><i class="bx bx-pencil"></i></button>
                            @endif
                            @if (in_array($conversation->platform, $deleteCapablePlatforms))
                                <button type="button" class="message-action-btn" data-action="delete" title="Delete"><i class="bx bx-trash"></i></button>
                            @endif
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
<div class="thread-composer">
    <form id="replyForm" enctype="multipart/form-data" class="w-100">
        @csrf
        <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
        <div class="composer-preview d-none" id="composerPreview"></div>
        <div class="composer-row">
            <textarea name="body" class="form-control" rows="1" placeholder="Type a reply..."></textarea>
            <input type="file" name="media" id="replyMedia" hidden accept="image/*,video/*">
            <button type="button" class="btn-composer-attach" onclick="document.getElementById('replyMedia').click()"><i class="bx bx-paperclip"></i></button>
            <button type="submit" class="btn-composer-send">Send</button>
        </div>
    </form>
</div>
<script>
    window.currentConversationId = {{ $conversation->id }};
    window.currentConversationPlatform = @json($conversation->platform);
</script>
