@extends('layouts.app')

@section('title', 'Unified Inbox')

<style>
    .inbox-shell {
        display: flex;
        height: calc(100vh - 180px);
        min-height: 500px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .08);
        overflow: hidden;
    }

    .inbox-sidebar {
        width: 340px;
        flex-shrink: 0;
        border-right: 1px solid #eef1f5;
        display: flex;
        flex-direction: column;
    }

    .inbox-sidebar-header {
        padding: 16px;
        border-bottom: 1px solid #eef1f5;
        font-weight: 600;
    }

    .conversation-list {
        overflow-y: auto;
        flex: 1;
    }

    .conversation-item {
        display: flex;
        gap: 10px;
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #f6f7f9;
        position: relative;
    }

    .conversation-item:hover {
        background: #f8f9fb;
    }

    .conversation-item.active {
        background: #eef3ff;
    }

    .conversation-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #ddd;
        flex-shrink: 0;
        object-fit: cover;
    }

    .platform-dot {
        position: absolute;
        left: 34px;
        top: 34px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #fff;
        font-size: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    .platform-dot.facebook { background: #1877F2; }
    .platform-dot.instagram { background: #E1306C; }
    .platform-dot.whatsapp { background: #25D366; }
    .platform-dot.telegram { background: #229ED9; }
    .platform-dot.x { background: #000; }

    .conversation-meta {
        flex: 1;
        min-width: 0;
    }

    .conversation-name {
        font-weight: 600;
        display: flex;
        justify-content: space-between;
    }

    .conversation-preview {
        color: #6b7280;
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .conversation-time {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    .unread-badge {
        background: #dc3545;
        color: #fff;
        border-radius: 10px;
        font-size: 0.7rem;
        padding: 1px 7px;
        margin-left: 6px;
    }

    .inbox-thread {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .thread-header {
        padding: 16px;
        border-bottom: 1px solid #eef1f5;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .thread-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .message-row {
        display: flex;
    }

    .message-row.outbound {
        justify-content: flex-end;
    }

    .message-bubble {
        max-width: 60%;
        padding: 10px 14px;
        border-radius: 14px;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .message-row.inbound .message-bubble {
        background: #f1f3f5;
        border-bottom-left-radius: 4px;
    }

    .message-row.outbound .message-bubble {
        background: #4285F4;
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .message-row.outbound .message-bubble.failed {
        background: #fbe9e9;
        color: #dc3545;
        border: 1px solid #f3c1c1;
    }

    .message-meta {
        font-size: 0.7rem;
        opacity: 0.7;
        margin-top: 4px;
    }

    .message-attachment img, .message-attachment video {
        max-width: 100%;
        border-radius: 10px;
        margin-bottom: 6px;
    }

    .thread-composer {
        border-top: 1px solid #eef1f5;
        padding: 12px 16px;
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .thread-composer textarea {
        resize: none;
    }

    .inbox-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        flex-direction: column;
        gap: 10px;
    }

    .platform-filter {
        display: flex;
        gap: 6px;
        padding: 10px 16px;
        flex-wrap: wrap;
        border-bottom: 1px solid #eef1f5;
    }

    .platform-filter button {
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 20px;
        padding: 3px 12px;
        font-size: 0.78rem;
    }

    .platform-filter button.active {
        background: #4285F4;
        color: #fff;
        border-color: #4285F4;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Unified Inbox</h4>
            <a href="{{ route('admin.chats.channels') }}" class="btn btn-outline-primary btn-sm">
                <i class="bx bx-plug"></i> Manage Channels
            </a>
        </div>

        <div class="inbox-shell">
            <div class="inbox-sidebar">
                <div class="inbox-sidebar-header">Conversations</div>
                <div class="platform-filter">
                    <button type="button" class="platform-filter-btn active" data-platform="">All</button>
                    <button type="button" class="platform-filter-btn" data-platform="facebook">Messenger</button>
                    <button type="button" class="platform-filter-btn" data-platform="instagram">Instagram</button>
                    <button type="button" class="platform-filter-btn" data-platform="whatsapp">WhatsApp</button>
                    <button type="button" class="platform-filter-btn" data-platform="telegram">Telegram</button>
                    <button type="button" class="platform-filter-btn" data-platform="x">X</button>
                </div>
                <div class="conversation-list" id="conversationList">
                    @forelse ($conversations as $conversation)
                        <div class="conversation-item {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}"
                             data-id="{{ $conversation->id }}"
                             data-platform="{{ $conversation->platform }}">
                            <div style="position:relative">
                                <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                                <span class="platform-dot {{ $conversation->platform }}"></span>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-name">
                                    <span>{{ $conversation->customer_name ?: 'Unknown' }}</span>
                                    <span class="conversation-time">{{ optional($conversation->last_message_at)->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="conversation-preview">
                                    {{ $conversation->last_message_preview }}
                                    @if ($conversation->unread_count > 0)
                                        <span class="unread-badge">{{ $conversation->unread_count }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-muted text-center">No conversations yet. Connect a channel to start receiving messages.</div>
                    @endforelse
                </div>
            </div>

            <div class="inbox-thread" id="inboxThread">
                @if ($activeConversation)
                    @include('admin.chats.partials.thread', ['conversation' => $activeConversation, 'messages' => $messages])
                @else
                    <div class="inbox-empty">
                        <i class="bx bx-message-dots" style="font-size:48px"></i>
                        <div>Select a conversation to start replying</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.addEventListener('load', function() {
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            const currentUserId = {{ Auth::id() }};
            const showUrlTemplate = "{{ route('admin.chats.show', ['conversation' => ':ID']) }}";
            const storeUrl = "{{ route('admin.chats.store') }}";
            const readUrlTemplate = "{{ route('admin.chats.read', ['conversation' => ':ID']) }}";

            function escapeHtml(str) {
                return $('<div>').text(str || '').html();
            }

            function timeAgo(iso) {
                if (!iso) return '';
                const diff = (Date.now() - new Date(iso).getTime()) / 1000;
                if (diff < 60) return 'now';
                if (diff < 3600) return Math.floor(diff / 60) + 'm';
                if (diff < 86400) return Math.floor(diff / 3600) + 'h';
                return Math.floor(diff / 86400) + 'd';
            }

            // ------------------------------------------------------------------
            // CONVERSATION SWITCHING
            // ------------------------------------------------------------------
            function loadConversation(id) {
                $('.conversation-item').removeClass('active');
                $(`.conversation-item[data-id="${id}"]`).addClass('active').find('.unread-badge').remove();

                $.get(showUrlTemplate.replace(':ID', id), function(res) {
                    if (!res.success) return;
                    renderThread(res.conversation, res.messages);
                });
            }

            $(document).on('click', '.conversation-item', function() {
                loadConversation($(this).data('id'));
            });

            function renderThread(conversation, messages) {
                let html = `
                    <div class="thread-header">
                        <img class="conversation-avatar" src="${conversation.customer_avatar_url || '{{ asset('assets/img/avatars/1.png') }}'}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                        <div>
                            <div class="fw-semibold">${escapeHtml(conversation.customer_name || 'Unknown')}</div>
                            <small class="text-muted text-capitalize">${conversation.platform}</small>
                        </div>
                    </div>
                    <div class="thread-messages" id="threadMessages">`;

                messages.forEach(m => html += renderMessage(m));

                html += `</div>
                    <div class="thread-composer">
                        <form id="replyForm" enctype="multipart/form-data" class="w-100 d-flex gap-2 align-items-end">
                            <input type="hidden" name="conversation_id" value="${conversation.id}">
                            <textarea name="body" class="form-control" rows="1" placeholder="Type a reply..."></textarea>
                            <input type="file" name="media" id="replyMedia" hidden accept="image/*,video/*">
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('replyMedia').click()"><i class="bx bx-paperclip"></i></button>
                            <button type="submit" class="btn btn-primary">Send</button>
                        </form>
                    </div>`;

                $('#inboxThread').html(html);
                scrollThreadToBottom();
                window.currentConversationId = conversation.id;
            }

            function renderMessage(m) {
                let attachmentHtml = '';
                (m.attachments || []).forEach(a => {
                    if (a.type === 'image') attachmentHtml += `<div class="message-attachment"><img src="${a.url}"></div>`;
                    else if (a.type === 'video') attachmentHtml += `<div class="message-attachment"><video src="${a.url}" controls></video></div>`;
                    else attachmentHtml += `<div class="message-attachment"><a href="${a.url}" target="_blank">📎 Attachment</a></div>`;
                });

                const failedClass = m.status === 'failed' ? 'failed' : '';

                return `
                    <div class="message-row ${m.direction}">
                        <div>
                            <div class="message-bubble ${failedClass}">
                                ${attachmentHtml}
                                ${m.body ? escapeHtml(m.body) : ''}
                            </div>
                            <div class="message-meta text-${m.direction === 'outbound' ? 'end' : 'start'}">
                                ${timeAgo(m.created_at)}${m.status === 'failed' ? ' · failed to send' : ''}
                            </div>
                        </div>
                    </div>`;
            }

            function scrollThreadToBottom() {
                const el = document.getElementById('threadMessages');
                if (el) el.scrollTop = el.scrollHeight;
            }

            scrollThreadToBottom();

            // ------------------------------------------------------------------
            // SEND REPLY
            // ------------------------------------------------------------------
            $(document).on('submit', '#replyForm', function(e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);

                if (!formData.get('body') && !$('#replyMedia')[0].files.length) return;

                $.ajax({
                    url: storeUrl,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(res) {
                        form.reset();
                        if (!res.success) {
                            Swal.fire('Error', res.error || 'Failed to send message.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to send message.', 'error');
                    }
                });
            });

            // Enter to send, Shift+Enter for newline.
            $(document).on('keydown', 'textarea[name="body"]', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $(this).closest('form').trigger('submit');
                }
            });

            // ------------------------------------------------------------------
            // PLATFORM FILTER
            // ------------------------------------------------------------------
            $('.platform-filter-btn').on('click', function() {
                $('.platform-filter-btn').removeClass('active');
                $(this).addClass('active');
                const platform = $(this).data('platform');

                $('.conversation-item').each(function() {
                    const show = !platform || $(this).data('platform') === platform;
                    $(this).toggle(show);
                });
            });

            // ------------------------------------------------------------------
            // REAL-TIME UPDATES (Laravel Echo / Reverb)
            // ------------------------------------------------------------------
            if (window.Echo) {
                window.Echo.private('inbox.' + currentUserId)
                    .listen('.message.created', function(e) {
                        const conversationId = e.message.conversation_id;
                        let $item = $(`.conversation-item[data-id="${conversationId}"]`);

                        if ($item.length) {
                            $item.find('.conversation-preview').contents().filter(function() {
                                return this.nodeType === 3;
                            }).first().replaceWith(escapeHtml(e.conversation.last_message_preview) + ' ');

                            if (conversationId != window.currentConversationId) {
                                let $badge = $item.find('.unread-badge');
                                if ($badge.length) {
                                    $badge.text(e.conversation.unread_count);
                                } else {
                                    $item.find('.conversation-preview').append(`<span class="unread-badge">${e.conversation.unread_count}</span>`);
                                }
                            }

                            $('#conversationList').prepend($item);
                        } else {
                            // A brand-new conversation - simplest correct
                            // behaviour is a fresh load of the sidebar entry.
                            location.reload();
                            return;
                        }

                        if (conversationId == window.currentConversationId) {
                            $('#threadMessages').append(renderMessage(e.message));
                            scrollThreadToBottom();
                            $.post(readUrlTemplate.replace(':ID', conversationId));
                        }
                    });
            }
        });
    </script>
@endpush
