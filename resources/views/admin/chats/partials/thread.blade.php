{{-- Server-rendered initial thread view - mirrors renderThread()/renderMessage()
     in dashboard.blade.php's script exactly, since that JS replaces this same
     markup wholesale whenever the admin switches conversations. --}}
<div class="thread-header">
    <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
    <div>
        <div class="fw-semibold">{{ $conversation->customer_name ?: 'Unknown' }}</div>
        <small class="text-muted text-capitalize">{{ $conversation->platform }}</small>
    </div>
</div>
<div class="thread-messages" id="threadMessages">
    @foreach ($messages as $message)
        <div class="message-row {{ $message->direction }}">
            <div>
                <div class="message-bubble {{ $message->status === 'failed' ? 'failed' : '' }}">
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
                <div class="message-meta text-{{ $message->direction === 'outbound' ? 'end' : 'start' }}">
                    {{ $message->created_at->diffForHumans(null, true) }}@if ($message->status === 'failed') · failed to send @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
<div class="thread-composer">
    <form id="replyForm" enctype="multipart/form-data" class="w-100 d-flex gap-2 align-items-end">
        @csrf
        <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
        <textarea name="body" class="form-control" rows="1" placeholder="Type a reply..."></textarea>
        <input type="file" name="media" id="replyMedia" hidden accept="image/*,video/*">
        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('replyMedia').click()"><i class="bx bx-paperclip"></i></button>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</div>
<script>
    window.currentConversationId = {{ $conversation->id }};
</script>
