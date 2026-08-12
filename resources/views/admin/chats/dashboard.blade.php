@extends('layouts.app')

@section('title', 'Unified Inbox')

<style>
    .inbox-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 20px;
    }

    .inbox-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .inbox-title i {
        width: 38px;
        height: 38px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #4338ca 0%, #6d28d9 45%, #9333ea 100%);
        color: #fff;
        font-size: 19px;
    }

    .inbox-subtitle {
        color: #8a93a6;
        font-size: .85rem;
        margin-bottom: 0;
    }

    .inbox-shell {
        display: flex;
        height: calc(100vh - 200px);
        min-height: 500px;
        background: #fff;
        border-radius: 18px;
        border: 1px solid #eef1f5;
        box-shadow: 0 10px 34px rgba(20, 20, 43, .06);
        overflow: hidden;
    }

    .inbox-sidebar {
        width: 350px;
        flex-shrink: 0;
        border-right: 1px solid #eef1f5;
        display: flex;
        flex-direction: column;
        background: #fbfbfe;
    }

    .inbox-sidebar-header {
        padding: 18px 18px 14px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fff;
    }

    .inbox-sidebar-count {
        font-size: .72rem;
        font-weight: 600;
        color: #6d28d9;
        background: rgba(109, 40, 217, .1);
        border-radius: 20px;
        padding: 2px 10px;
    }

    .platform-filter {
        display: flex;
        gap: 6px;
        padding: 0 14px 14px;
        flex-wrap: wrap;
        background: #fff;
        border-bottom: 1px solid #eef1f5;
    }

    .platform-filter button {
        border: 1px solid #eef1f5;
        background: #f8f9fc;
        color: #5b6373;
        border-radius: 20px;
        padding: 4px 13px;
        font-size: .74rem;
        font-weight: 600;
        transition: all .15s ease;
    }

    .platform-filter button:hover {
        border-color: #d9defa;
        background: #f0f1fb;
    }

    .platform-filter button.active {
        background: linear-gradient(135deg, #4338ca 0%, #6d28d9 45%, #9333ea 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(109, 40, 217, .25);
    }

    .conversation-list {
        overflow-y: auto;
        flex: 1;
    }

    .conversation-item {
        display: flex;
        gap: 12px;
        padding: 13px 18px;
        cursor: pointer;
        border-bottom: 1px solid #f3f5f9;
        border-left: 3px solid transparent;
        position: relative;
        transition: background .15s ease;
    }

    .conversation-item:hover {
        background: #f3f4fb;
    }

    .conversation-item.active {
        background: #eeeafc;
        border-left-color: #6d28d9;
    }

    .conversation-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #e5e7f0;
        flex-shrink: 0;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px #eef1f5;
    }

    .platform-dot {
        position: absolute;
        left: 36px;
        top: 35px;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        border: 2px solid #fbfbfe;
        font-size: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
    }

    .platform-dot.facebook { background: #1877F2; }
    .platform-dot.instagram { background: linear-gradient(135deg,#f58529,#dd2a7b 55%,#8134af); }
    .platform-dot.whatsapp { background: #25D366; }
    .platform-dot.telegram { background: #229ED9; }
    .platform-dot.x { background: #000; }
    .platform-dot.line { background: #00B900; }
    .platform-dot.zalo { background: #0068ff; }
    .platform-dot.discord { background: #5865F2; }
    .platform-dot.slack { background: #4A154B; }
    .platform-dot.teams { background: #5B5FC7; }
    .platform-dot.google_chat { background: #4285F4; }
    .platform-dot.matrix { background: #0DBD8B; }

    .conversation-meta {
        flex: 1;
        min-width: 0;
    }

    .conversation-name {
        font-weight: 600;
        font-size: .92rem;
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 2px;
    }

    .conversation-name > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .conversation-preview {
        color: #8a93a6;
        font-size: 0.83rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .conversation-time {
        font-size: 0.72rem;
        color: #a7adba;
        flex-shrink: 0;
    }

    .unread-badge {
        background: linear-gradient(135deg, #4338ca, #9333ea);
        color: #fff;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 1px 7px;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(109, 40, 217, .35);
    }

    .inbox-thread {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        background: #fdfdff;
    }

    .thread-header {
        padding: 16px 22px;
        border-bottom: 1px solid #eef1f5;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #fff;
    }

    .platform-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .68rem;
        font-weight: 700;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        text-transform: capitalize;
        margin-top: 2px;
    }

    .thread-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: #fdfdff;
    }

    .message-row {
        display: flex;
    }

    .message-row.outbound {
        justify-content: flex-end;
    }

    .message-bubble {
        max-width: 60%;
        padding: 11px 15px;
        border-radius: 16px;
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.45;
        font-size: .92rem;
    }

    .message-row.inbound .message-bubble {
        background: #fff;
        border: 1px solid #eef1f5;
        box-shadow: 0 2px 8px rgba(20, 20, 43, .04);
        border-bottom-left-radius: 4px;
    }

    .message-row.outbound .message-bubble {
        background: linear-gradient(135deg, #4338ca 0%, #6d28d9 55%, #9333ea 100%);
        color: #fff;
        border-bottom-right-radius: 4px;
        box-shadow: 0 4px 14px rgba(109, 40, 217, .22);
    }

    .message-row.outbound .message-bubble.failed {
        background: #fbe9e9;
        color: #dc3545;
        border: 1px solid #f3c1c1;
        box-shadow: none;
    }

    .message-meta {
        font-size: 0.7rem;
        color: #a7adba;
        opacity: 0.9;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .message-row.outbound .message-meta {
        justify-content: flex-end;
    }

    .message-actions {
        display: none;
        align-items: center;
        gap: 2px;
    }

    .message-row:hover .message-actions {
        display: inline-flex;
    }

    .message-action-btn {
        border: none;
        background: transparent;
        color: inherit;
        opacity: .55;
        padding: 0 2px;
        font-size: .85rem;
        line-height: 1;
        transition: opacity .15s ease;
    }

    .message-action-btn:hover {
        opacity: 1;
    }

    .message-bubble.is-deleted {
        background: transparent !important;
        border: 1px dashed #d7dbe6 !important;
        color: #a7adba !important;
        box-shadow: none !important;
        font-style: italic;
        font-size: .85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .message-edit-box textarea {
        width: 100%;
        min-width: 220px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, .4);
        padding: 6px 10px;
        font-size: .92rem;
        resize: vertical;
        color: #1a1a2e;
    }

    .message-edit-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
        margin-top: 6px;
    }

    .message-edit-actions button {
        border: none;
        border-radius: 14px;
        padding: 3px 12px;
        font-size: .75rem;
        font-weight: 600;
    }

    .message-edit-save {
        background: #fff;
        color: #6d28d9;
    }

    .message-edit-cancel {
        background: rgba(255, 255, 255, .25);
        color: inherit;
    }

    .message-attachment img, .message-attachment video {
        max-width: 100%;
        border-radius: 12px;
        margin-bottom: 6px;
        display: block;
    }

    .thread-composer {
        border-top: 1px solid #eef1f5;
        padding: 14px 20px;
        background: #fff;
    }

    .composer-row {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .composer-preview {
        margin-bottom: 10px;
    }

    .composer-preview-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8f9fc;
        border: 1px solid #eef1f5;
        border-radius: 12px;
        padding: 6px 8px 6px 6px;
        max-width: 280px;
    }

    .composer-preview-thumb {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .composer-preview-file-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #eeeafc;
        color: #6d28d9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        flex-shrink: 0;
    }

    .composer-preview-name {
        font-size: .8rem;
        color: #5b6373;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .composer-preview-remove {
        border: none;
        background: transparent;
        color: #a7adba;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        flex-shrink: 0;
        font-size: 16px;
        transition: all .15s ease;
    }

    .composer-preview-remove:hover {
        background: #fbe9e9;
        color: #dc3545;
    }

    .thread-composer textarea {
        resize: none;
        border-radius: 20px;
        padding: 10px 18px;
        border: 1px solid #e5e7f0;
        background: #f8f9fc;
    }

    .thread-composer textarea:focus {
        background: #fff;
        border-color: #c7bbf0;
        box-shadow: 0 0 0 .18rem rgba(109, 40, 217, .12);
    }

    .btn-composer-attach {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 1px solid #eef1f5;
        background: #f8f9fc;
        color: #6d28d9;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all .15s ease;
    }

    .btn-composer-attach:hover {
        background: #eeeafc;
        border-color: #d9defa;
        color: #6d28d9;
    }

    .btn-composer-send {
        border: none;
        border-radius: 20px;
        padding: 9px 22px;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, #4338ca 0%, #6d28d9 55%, #9333ea 100%);
        box-shadow: 0 4px 12px rgba(109, 40, 217, .3);
        flex-shrink: 0;
        transition: transform .15s ease;
    }

    .btn-composer-send:hover {
        color: #fff;
        transform: translateY(-1px);
    }

    .inbox-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #a7adba;
        flex-direction: column;
        gap: 12px;
    }

    .inbox-empty-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: #f3f4fb;
        color: #9333ea;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 34px;
    }

    .conversation-list-empty {
        padding: 40px 20px;
        text-align: center;
        color: #a7adba;
        font-size: .85rem;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="inbox-topbar">
            <div>
                <h4 class="inbox-title"><i class="bx bx-message-dots"></i> Unified Inbox</h4>
                <p class="inbox-subtitle">{{ $conversations->count() }} conversation{{ $conversations->count() === 1 ? '' : 's' }} across every connected channel</p>
            </div>
            <a href="{{ route('admin.chats.channels') }}" class="btn btn-outline-primary btn-sm">
                <i class="bx bx-plug"></i> Manage Channels
            </a>
        </div>

        @php
            // Derived from whatever platforms actually have conversations,
            // rather than a fixed list - a fixed list is exactly what went
            // stale before (five platforms had filter pills, seven newer
            // ones never got added as the messaging module grew). Shared
            // with partials/thread.blade.php (via @include's inherited
            // scope) and with the JS thread-swap renderer below (via
            // window.platformLabels/platformIcons) so every place a
            // platform name/icon appears stays in sync from one source.
            $platformLabels = [
                'facebook'    => 'Messenger',
                'instagram'   => 'Instagram',
                'whatsapp'    => 'WhatsApp',
                'telegram'    => 'Telegram',
                'x'           => 'X',
                'line'        => 'LINE',
                'zalo'        => 'Zalo',
                'discord'     => 'Discord',
                'slack'       => 'Slack',
                'teams'       => 'Teams',
                'google_chat' => 'Google Chat',
                'matrix'      => 'Matrix',
            ];
            $platformIcons = [
                'facebook'    => 'bxl-facebook',
                'instagram'   => 'bxl-instagram',
                'whatsapp'    => 'bxl-whatsapp',
                'telegram'    => 'bxl-telegram',
                'x'           => 'bxl-twitter',
                'line'        => 'bx-message-rounded-dots',
                'zalo'        => 'bx-message-rounded-dots',
                'discord'     => 'bxl-discord',
                'slack'       => 'bxl-slack',
                'teams'       => 'bxl-microsoft-teams',
                'google_chat' => 'bx-message-rounded-dots',
                'matrix'      => 'bx-message-rounded-dots',
            ];
            $platformColors = [
                'facebook' => '#1877F2', 'instagram' => '#dd2a7b', 'whatsapp' => '#25D366',
                'telegram' => '#229ED9', 'x' => '#000', 'line' => '#00B900', 'zalo' => '#0068ff',
                'discord' => '#5865F2', 'slack' => '#4A154B', 'teams' => '#5B5FC7',
                'google_chat' => '#4285F4', 'matrix' => '#0DBD8B',
            ];
            $platformLabel = fn($p) => $platformLabels[$p] ?? ucwords(str_replace('_', ' ', $p));
            $activePlatforms = $conversations->pluck('platform')->unique()->values();
        @endphp

        <div class="inbox-shell">
            <div class="inbox-sidebar">
                <div class="inbox-sidebar-header">
                    Conversations
                    <span class="inbox-sidebar-count">{{ $conversations->count() }}</span>
                </div>
                <div class="platform-filter">
                    <button type="button" class="platform-filter-btn active" data-platform="">All</button>
                    @foreach ($activePlatforms as $platform)
                        <button type="button" class="platform-filter-btn" data-platform="{{ $platform }}">{{ $platformLabel($platform) }}</button>
                    @endforeach
                </div>
                <div class="conversation-list" id="conversationList">
                    @forelse ($conversations as $conversation)
                        <div class="conversation-item {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}"
                             data-id="{{ $conversation->id }}"
                             data-platform="{{ $conversation->platform }}">
                            <div style="position:relative">
                                <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                                <span class="platform-dot {{ $conversation->platform }}"><i class="bx {{ $platformIcons[$conversation->platform] ?? 'bx-message-rounded-dots' }}"></i></span>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-name">
                                    <span>{{ $conversation->customer_name ?: 'Unknown' }}</span>
                                    <span class="conversation-time">{{ optional($conversation->last_message_at)->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="conversation-preview">
                                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $conversation->last_message_preview }}</span>
                                    @if ($conversation->unread_count > 0)
                                        <span class="unread-badge">{{ $conversation->unread_count }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="conversation-list-empty">No conversations yet. Connect a channel to start receiving messages.</div>
                    @endforelse
                </div>
            </div>

            <div class="inbox-thread" id="inboxThread">
                @if ($activeConversation)
                    @include('admin.chats.partials.thread', ['conversation' => $activeConversation, 'messages' => $messages])
                @else
                    <div class="inbox-empty">
                        <div class="inbox-empty-icon"><i class="bx bx-message-dots"></i></div>
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
            const messageUpdateUrlTemplate = "{{ route('admin.chats.messages.update', ['message' => ':ID']) }}";
            const messageDeleteUrlTemplate = "{{ route('admin.chats.messages.destroy', ['message' => ':ID']) }}";

            // renderThread() (the AJAX conversation-switch path) is the
            // only place that normally sets these - on a fresh page load
            // the active conversation's thread is server-rendered instead
            // (see the partials/thread.blade.php include below), so
            // without this, both the Echo listener and the polling
            // fallback stay silent on a new inbound message until the
            // admin manually clicks a conversation at least once.
            @if ($activeConversation)
                window.currentConversationId = {{ $activeConversation->id }};
                window.currentConversationPlatform = @json($activeConversation->platform);
            @endif

            // Same label/icon/color maps as the Blade side above (kept in
            // sync manually since one is computed server-side and the
            // other runs in the browser when swapping conversations via
            // AJAX) - used only by renderThread()'s platform badge.
            const platformLabels = @json($platformLabels);
            const platformColors = @json($platformColors);

            // Straight from MessagingManagerService, not a second
            // hand-maintained list - see that class's editCapablePlatforms()/
            // deleteCapablePlatforms() docblock for why that matters here.
            const editCapablePlatforms = @json($editCapablePlatforms);
            const deleteCapablePlatforms = @json($deleteCapablePlatforms);

            function platformLabel(p) {
                if (platformLabels[p]) return platformLabels[p];
                return p.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            }

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
                const badgeColor = platformColors[conversation.platform] || '#6d28d9';

                let html = `
                    <div class="thread-header">
                        <img class="conversation-avatar" src="${conversation.customer_avatar_url || '{{ asset('assets/img/avatars/1.png') }}'}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                        <div>
                            <div class="fw-semibold">${escapeHtml(conversation.customer_name || 'Unknown')}</div>
                            <span class="platform-badge" style="background:${badgeColor}">${platformLabel(conversation.platform)}</span>
                        </div>
                    </div>
                    <div class="thread-messages" id="threadMessages">`;

                messages.forEach(m => html += renderMessage(m, conversation.platform));

                html += `</div>
                    <div class="thread-composer">
                        <form id="replyForm" enctype="multipart/form-data" class="w-100">
                            <input type="hidden" name="conversation_id" value="${conversation.id}">
                            <div class="composer-preview d-none" id="composerPreview"></div>
                            <div class="composer-row">
                                <textarea name="body" class="form-control" rows="1" placeholder="Type a reply..."></textarea>
                                <input type="file" name="media" id="replyMedia" hidden accept="image/*,video/*">
                                <button type="button" class="btn-composer-attach" onclick="document.getElementById('replyMedia').click()"><i class="bx bx-paperclip"></i></button>
                                <button type="submit" class="btn-composer-send">Send</button>
                            </div>
                        </form>
                    </div>`;

                $('#inboxThread').html(html);
                scrollThreadToBottom();
                window.currentConversationId = conversation.id;
                window.currentConversationPlatform = conversation.platform;
            }

            function escapeAttr(str) {
                return (str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function renderMessage(m, platform) {
                if (m.deleted_at) {
                    return renderDeletedMessageRow(m);
                }

                let attachmentHtml = '';
                (m.attachments || []).forEach(a => {
                    if (a.type === 'image') attachmentHtml += `<div class="message-attachment"><img src="${a.url}"></div>`;
                    else if (a.type === 'video') attachmentHtml += `<div class="message-attachment"><video src="${a.url}" controls></video></div>`;
                    else attachmentHtml += `<div class="message-attachment"><a href="${a.url}" target="_blank">📎 Attachment</a></div>`;
                });

                const failedClass = m.status === 'failed' ? 'failed' : '';
                const canEdit = m.direction === 'outbound' && m.status === 'sent' && editCapablePlatforms.includes(platform);
                const canDelete = m.direction === 'outbound' && m.status === 'sent' && deleteCapablePlatforms.includes(platform);

                let actionsHtml = '';
                if (canEdit || canDelete) {
                    actionsHtml = '<span class="message-actions">';
                    if (canEdit) actionsHtml += '<button type="button" class="message-action-btn" data-action="edit" title="Edit"><i class="bx bx-pencil"></i></button>';
                    if (canDelete) actionsHtml += '<button type="button" class="message-action-btn" data-action="delete" title="Delete"><i class="bx bx-trash"></i></button>';
                    actionsHtml += '</span>';
                }

                return `
                    <div class="message-row ${m.direction}" data-message-id="${m.id}">
                        <div>
                            <div class="message-bubble ${failedClass}" data-message-body="${escapeAttr(m.body)}">
                                ${attachmentHtml}
                                ${m.body ? escapeHtml(m.body) : ''}
                            </div>
                            <div class="message-meta text-${m.direction === 'outbound' ? 'end' : 'start'}">
                                <span class="message-meta-text">${timeAgo(m.created_at)}${m.status === 'failed' ? ' · failed to send' : ''}${m.edited_at ? ' · edited' : ''}</span>
                                ${actionsHtml}
                            </div>
                        </div>
                    </div>`;
            }

            function renderDeletedMessageRow(m) {
                return `
                    <div class="message-row ${m.direction}" data-message-id="${m.id}">
                        <div>
                            <div class="message-bubble is-deleted">
                                <i class="bx bx-block"></i> This message was deleted
                            </div>
                            <div class="message-meta text-${m.direction === 'outbound' ? 'end' : 'start'}">
                                <span class="message-meta-text">${timeAgo(m.created_at)}</span>
                            </div>
                        </div>
                    </div>`;
            }

            function scrollThreadToBottom() {
                const el = document.getElementById('threadMessages');
                if (el) el.scrollTop = el.scrollHeight;
            }

            // Shared by the send-reply AJAX response and the Echo
            // broadcast listener - both can end up delivering the same
            // outbound message (the sender's own tab is subscribed to its
            // own inbox channel), so this guards against rendering it
            // twice if the broadcast arrives after the AJAX response
            // already appended it.
            function appendMessageIfNew(message, platform) {
                if ($(`#threadMessages .message-row[data-message-id="${message.id}"]`).length) {
                    return;
                }
                $('#threadMessages').append(renderMessage(message, platform));
                scrollThreadToBottom();
            }

            scrollThreadToBottom();

            // ------------------------------------------------------------------
            // ATTACHMENT PREVIEW
            // ------------------------------------------------------------------
            let composerPreviewUrl = null;

            function clearComposerPreview() {
                if (composerPreviewUrl) {
                    URL.revokeObjectURL(composerPreviewUrl);
                    composerPreviewUrl = null;
                }
                $('#composerPreview').addClass('d-none').empty();
            }

            $(document).on('change', '#replyMedia', function() {
                const file = this.files[0];
                clearComposerPreview();

                if (!file) return;

                const removeBtn = '<button type="button" class="composer-preview-remove" title="Remove attachment"><i class="bx bx-x"></i></button>';
                let chip;

                if (file.type.startsWith('image/')) {
                    composerPreviewUrl = URL.createObjectURL(file);
                    chip = `<div class="composer-preview-chip"><img src="${composerPreviewUrl}" class="composer-preview-thumb">${removeBtn}</div>`;
                } else {
                    const icon = file.type.startsWith('video/') ? 'bx-video' : 'bx-file';
                    chip = `<div class="composer-preview-chip"><span class="composer-preview-file-icon"><i class="bx ${icon}"></i></span><span class="composer-preview-name">${escapeHtml(file.name)}</span>${removeBtn}</div>`;
                }

                $('#composerPreview').html(chip).removeClass('d-none');
            });

            $(document).on('click', '.composer-preview-remove', function() {
                $('#replyMedia').val('');
                clearComposerPreview();
            });

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
                        clearComposerPreview();
                        if (!res.success) {
                            Swal.fire('Error', res.error || 'Failed to send message.', 'error');
                            return;
                        }
                        if (res.message && res.message.conversation_id == window.currentConversationId) {
                            appendMessageIfNew(res.message, window.currentConversationPlatform);
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
            // POLLING FALLBACK (new inbound messages)
            // ------------------------------------------------------------------
            // Customer replies are saved correctly the moment the webhook
            // fires (ProcessInboundMessage job), but showing them live in
            // an already-open thread otherwise depends entirely on the
            // Echo/Reverb broadcast below reaching this tab - if that
            // WebSocket connection isn't up (Reverb not running, blocked by
            // a proxy, etc.) a genuinely new customer message just sits in
            // the database until the admin manually reloads or re-selects
            // the conversation. This polls the open thread on a short
            // interval as a safety net; appendMessageIfNew() dedupes by
            // message id, so it's harmless to run alongside a working Echo
            // connection too - whichever notices a new message first wins.
            setInterval(function() {
                if (!window.currentConversationId) return;

                $.get(showUrlTemplate.replace(':ID', window.currentConversationId), function(res) {
                    if (!res.success) return;

                    res.messages.forEach(m => appendMessageIfNew(m, res.conversation.platform));

                    const $item = $(`.conversation-item[data-id="${res.conversation.id}"]`);
                    if ($item.length) {
                        $item.find('.conversation-preview span').first().text(res.conversation.last_message_preview);
                    }
                });
            }, 5000);

            // ------------------------------------------------------------------
            // EDIT / DELETE A SENT MESSAGE
            // ------------------------------------------------------------------
            // Icons only ever render for platforms whose API actually
            // supports the action (see renderMessage()/partials/thread.blade.php),
            // so reaching this handler at all already implies it's allowed -
            // no further capability check needed here.
            $(document).on('click', '.message-action-btn[data-action="edit"]', function() {
                const $row = $(this).closest('.message-row');
                const $bubble = $row.find('.message-bubble');
                const currentBody = $bubble.attr('data-message-body') || '';

                $bubble.html(`
                    <div class="message-edit-box">
                        <textarea class="form-control">${escapeHtml(currentBody)}</textarea>
                        <div class="message-edit-actions">
                            <button type="button" class="message-edit-cancel">Cancel</button>
                            <button type="button" class="message-edit-save">Save</button>
                        </div>
                    </div>
                `);
                $bubble.find('textarea').trigger('focus');
            });

            // Simplest correct way to restore the original bubble without
            // risking the client-side markup drifting from the server's
            // version - the same "just reload from the server" approach
            // already used elsewhere in this file for a brand-new conversation.
            $(document).on('click', '.message-edit-cancel', function() {
                loadConversation(window.currentConversationId);
            });

            $(document).on('click', '.message-edit-save', function() {
                const $row = $(this).closest('.message-row');
                const $btn = $(this);
                const messageId = $row.data('message-id');
                const newBody = $row.find('.message-edit-box textarea').val().trim();

                if (!newBody) return;

                $btn.prop('disabled', true);

                $.ajax({
                    url: messageUpdateUrlTemplate.replace(':ID', messageId),
                    type: 'PATCH',
                    data: { body: newBody },
                    success: function(res) {
                        if (!res.success) {
                            Swal.fire('Error', res.error || 'Failed to edit message.', 'error');
                            loadConversation(window.currentConversationId);
                        }
                        // On success the bubble updates via the
                        // message.updated broadcast, not here directly.
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.error || 'Failed to edit message.', 'error');
                        loadConversation(window.currentConversationId);
                    }
                });
            });

            $(document).on('click', '.message-action-btn[data-action="delete"]', function() {
                const $row = $(this).closest('.message-row');
                const messageId = $row.data('message-id');

                Swal.fire({
                    title: 'Delete this message?',
                    text: 'This removes it on the customer\'s side too, not just yours.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#dc3545',
                }).then(function(result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: messageDeleteUrlTemplate.replace(':ID', messageId),
                        type: 'DELETE',
                        success: function(res) {
                            if (!res.success) {
                                Swal.fire('Error', res.error || 'Failed to delete message.', 'error');
                            }
                            // On success the bubble turns into the "deleted"
                            // placeholder via the message.updated broadcast.
                        },
                        error: function(xhr) {
                            Swal.fire('Error', xhr.responseJSON?.error || 'Failed to delete message.', 'error');
                        }
                    });
                });
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
                            $item.find('.conversation-preview span').first().text(e.conversation.last_message_preview);

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
                            appendMessageIfNew(e.message, window.currentConversationPlatform);
                            $.post(readUrlTemplate.replace(':ID', conversationId));
                        }
                    });

                // Fired after a successful edit or delete (see
                // ChatController::updateMessage()/destroyMessage()) - both
                // the actor's own tab and any other open tab on this inbox
                // update the same way, through this one listener, rather
                // than the AJAX success handlers touching the DOM directly.
                window.Echo.private('inbox.' + currentUserId)
                    .listen('.message.updated', function(e) {
                        const $row = $(`.message-row[data-message-id="${e.message.id}"]`);

                        if ($row.length) {
                            if (e.message.deleted_at) {
                                $row.replaceWith(renderDeletedMessageRow(e.message));
                            } else {
                                $row.find('.message-bubble')
                                    .attr('data-message-body', escapeAttr(e.message.body))
                                    .html(e.message.body ? escapeHtml(e.message.body) : '');
                                $row.find('.message-meta-text').text(
                                    $row.find('.message-meta-text').text().replace(' · edited', '') + (e.message.edited_at ? ' · edited' : '')
                                );
                            }
                        }

                        if (e.conversation) {
                            $(`.conversation-item[data-id="${e.conversation.id}"] .conversation-preview span`).first().text(e.conversation.last_message_preview);
                        }
                    });
            }
        });
    </script>
@endpush
