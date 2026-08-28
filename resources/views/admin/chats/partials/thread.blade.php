{{-- Server-rendered initial thread view - mirrors renderThread()/renderMessage()
     in dashboard.blade.php's script exactly, since that JS replaces this same
     markup wholesale whenever the admin switches conversations. $platformLabels,
     $platformColors, $platformIcons, $editCapablePlatforms and
     $deleteCapablePlatforms are set by dashboard.blade.php (and, for the
     capability lists, ultimately by ChatController) and inherited here
     automatically since this partial is included, not rendered in isolation. --}}
<div class="thread-header">
    <div class="thread-header-identity">
        <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
        <div>
            <div class="fw-semibold">{{ $conversation->customer_name ?: 'Unknown' }}</div>
            <div class="thread-header-badges">
                <span class="platform-badge" style="background:{{ $platformColors[$conversation->platform] ?? '#6d28d9' }}">{{ $platformLabel($conversation->platform) }}</span>
                <span class="status-pill {{ $conversation->status }}">
                    <span class="status-pill-dot"></span>
                    {{ $conversation->status === 'open' ? 'Active' : ucfirst($conversation->status) }}
                </span>
            </div>
        </div>
    </div>
    <div class="thread-header-actions">
        <button type="button" class="btn-thread-action" id="summarizeBtn" title="AI conversation summary - coming soon"><i class="bx bx-list-check"></i> Summarize</button>
        <button type="button" class="btn-details-toggle" id="toggleDetailsBtn" title="Chat details"><i class="bx bx-info-circle"></i></button>
    </div>
</div>
<div class="thread-messages" id="threadMessages">
    @php $previousMessage = null; @endphp
    @foreach ($messages as $message)
        @php
            $isGrouped = $previousMessage
                && $previousMessage->direction === $message->direction
                && $previousMessage->created_at->isSameDay($message->created_at)
                && $previousMessage->created_at->diffInMinutes($message->created_at) < 3;
            $showDateSeparator = !$previousMessage || !$previousMessage->created_at->isSameDay($message->created_at);
        @endphp
        @if ($showDateSeparator)
            <div class="date-separator">
                <span>
                    @if ($message->created_at->isToday())
                        Today
                    @elseif ($message->created_at->isYesterday())
                        Yesterday
                    @else
                        {{ $message->created_at->format($message->created_at->year === now()->year ? 'M j' : 'M j, Y') }}
                    @endif
                </span>
            </div>
        @endif
        <div
                class="message-row {{ $message->direction }} {{ $isGrouped ? 'is-grouped' : '' }}"
                data-message-id="{{ $message->id }}"
                data-direction="{{ $message->direction }}"
                data-created-at="{{ $message->created_at->toIso8601String() }}"
        >
            <div class="message-row-inner">
                @if ($message->direction === 'inbound')
                    <img class="message-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                @endif
                <div class="message-col">
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
                            @if ($message->body)<span class="message-bubble-text">{{ trim($message->body) }}</span>@endif
                        </div>
                    @endif
                    <div class="message-meta text-{{ $message->direction === 'outbound' ? 'end' : 'start' }}">
                        <span class="message-platform-badge" style="background:{{ $platformColors[$conversation->platform] ?? '#6d28d9' }}" title="{{ $platformLabel($conversation->platform) }}">
                            <i class="bx {{ $platformIcons[$conversation->platform] ?? 'bx-message-rounded-dots' }}"></i>
                        </span>
                        <span class="message-meta-text">
                            {{ $message->created_at->format('g:i A') }}@if ($message->edited_at) · edited @endif
                        </span>
                        @if (!$message->deleted_at && $message->direction === 'outbound')
                            @if ($message->status === 'failed')
                                <i class="bx bx-error-circle message-status-icon failed" title="Failed to send"></i>
                            @elseif ($message->status === 'sent')
                                <i class="bx bx-check message-status-icon sent" title="Sent"></i>
                            @endif
                        @endif
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
        </div>
        @php $previousMessage = $message; @endphp
    @endforeach
</div>
<div class="ai-copilot-panel" id="aiCopilotPanel">
    <div class="ai-copilot-header">
        <span class="ai-copilot-title"><i class="bx bx-bulb"></i> AI Copilot</span>
        <button type="button" class="ai-copilot-close" id="aiCopilotCloseBtn" title="Hide"><i class="bx bx-x"></i></button>
    </div>
    <div class="ai-copilot-actions">
        <button type="button" class="ai-copilot-btn" data-ai-action="draft"><i class="bx bx-edit-alt"></i> Draft a Professional Reply</button>
        <button type="button" class="ai-copilot-btn" data-ai-action="summarize"><i class="bx bx-list-ul"></i> Summarize this conversation</button>
        <button type="button" class="ai-copilot-btn" data-ai-action="tone"><i class="bx bx-happy-alt"></i> Adjust Tone (Friendly/Helpful)</button>
        <button type="button" class="ai-copilot-btn" data-ai-action="translate"><i class="bx bx-globe"></i> Translate</button>
    </div>
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
            <button type="button" class="btn-ai-compose" id="aiComposeToggleBtn" title="Open AI Copilot"><i class="bx bx-magic-wand"></i> AI Compose</button>
            <button type="submit" class="btn-composer-send">Send</button>
        </div>
    </form>
</div>
<script>
    window.currentConversationId = {{ $conversation->id }};
    window.currentConversationPlatform = @json($conversation->platform);
    window.currentConversationAvatar = @json($conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png'));
</script>
