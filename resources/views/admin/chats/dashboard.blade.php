@extends('layouts.app')

@section('title', 'Unified Inbox')

{{-- Was a bare <style> tag outside any @section/@push - with @extends,
     that renders before layouts.app's own <head> content, so this loaded
     BEFORE admin.css/socialeaz-admin.css instead of after. Harmless today
     (nothing in those files currently targets these classes), but any
     future rule with equal specificity there would have silently won the
     cascade over this page's own styling. @push puts it in the right
     place: inside <head>, after those stylesheets. --}}
@push('styles')
<style>
    /* =========================================================
       UNIFIED INBOX - shared tokens (same palette as Channels).
       Class names are used by the page's JS (thread swap, filters,
       live updates) - restyle here, don't rename.
    ========================================================= */
    .inbox-page {
        --ib-ln: #e7e9f0;
        --ib-ln-soft: #f1f3f7;
        --ib-ink: #161b2b;
        --ib-ink2: #545d70;
        --ib-muted: #8a92a3;
        --ib-brand: #6d4aff;
        --ib-brand-2: #8f6bff;
        --ib-brand-soft: #f2eeff;
        --ib-brand-ink: #4f2fd6;
        --ib-grad: linear-gradient(135deg, var(--ib-brand) 0%, var(--ib-brand-2) 100%);
        --ib-ok: #079455;
        --ib-danger: #d92d20;
        --ib-surface: #fbfbfd;
    }

    /* ---------- Topbar ---------- */
    .inbox-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 18px;
    }

    .inbox-heading {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .inbox-heading-icon {
        width: 46px;
        height: 46px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        background: var(--ib-grad);
        color: #fff;
        font-size: 23px;
        box-shadow: 0 8px 18px rgba(109, 74, 255, .28);
        flex-shrink: 0;
    }

    .inbox-title {
        font-weight: 700;
        font-size: 1.3rem;
        letter-spacing: -.01em;
        color: var(--ib-ink);
        margin: 0 0 3px;
    }

    .inbox-subtitle {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 14px;
        color: var(--ib-muted);
        font-size: .82rem;
        margin: 0;
    }

    .inbox-subtitle strong {
        color: var(--ib-ink);
        font-weight: 700;
    }

    .inbox-subtitle .sep {
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: #cfd3dc;
    }

    .inbox-subtitle .is-unread strong {
        color: var(--ib-brand);
    }

    .inbox-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .inbox-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        padding: 0 14px;
        border-radius: 10px;
        font-size: .82rem;
        font-weight: 600;
        border: 1px solid var(--ib-ln);
        background: #fff;
        color: var(--ib-ink);
        white-space: nowrap;
        text-decoration: none;
        transition: background .15s, border-color .15s, color .15s, box-shadow .15s;
    }

    .inbox-btn i {
        font-size: 1.05rem;
    }

    .inbox-btn:hover {
        background: #fafbfd;
        border-color: #cfd4de;
        color: var(--ib-ink);
    }

    .inbox-btn-group {
        display: inline-flex;
        border: 1px solid var(--ib-ln);
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }

    .inbox-btn-group .inbox-btn {
        border: none;
        border-radius: 0;
    }

    .inbox-btn-group .inbox-btn + .inbox-btn {
        border-left: 1px solid var(--ib-ln);
    }

    .btn-ai-feature {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 38px;
        border: 1px solid #ddd3ff;
        border-radius: 10px;
        padding: 0 14px;
        font-size: .82rem;
        font-weight: 600;
        color: var(--ib-brand-ink);
        background: var(--ib-brand-soft);
        transition: background .15s, box-shadow .15s;
    }

    .btn-ai-feature i {
        font-size: 1.05rem;
    }

    .btn-ai-feature:hover {
        background: #e9e2ff;
        color: var(--ib-brand-ink);
        box-shadow: 0 4px 12px rgba(109, 74, 255, .14);
    }

    .inbox-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        border-radius: 12px;
        padding: 11px 16px;
        font-size: .86rem;
        font-weight: 500;
        margin-bottom: 16px;
        border: 1px solid;
    }

    .inbox-alert i {
        font-size: 1.15rem;
    }

    .inbox-alert-ok {
        background: #ecfdf3;
        border-color: #abefc6;
        color: var(--ib-ok);
    }

    .inbox-alert-err {
        background: #fef3f2;
        border-color: #fecdca;
        color: var(--ib-danger);
    }

    /* ---------- Shell ---------- */
    .inbox-shell {
        display: flex;
        height: calc(100vh - 200px);
        min-height: 520px;
        background: #fff;
        border-radius: 18px;
        border: 1px solid var(--ib-ln);
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px rgba(16, 24, 40, .05);
        overflow: hidden;
    }

    /* ---------- Conversation sidebar ---------- */
    .inbox-sidebar {
        width: 340px;
        flex-shrink: 0;
        border-right: 1px solid var(--ib-ln);
        display: flex;
        flex-direction: column;
        background: #fff;
    }

    .inbox-sidebar-header {
        padding: 16px 16px 12px;
        font-weight: 700;
        font-size: .95rem;
        color: var(--ib-ink);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .inbox-sidebar-count {
        font-size: .72rem;
        font-weight: 700;
        color: var(--ib-brand-ink);
        background: var(--ib-brand-soft);
        border-radius: 999px;
        padding: 2px 9px;
    }

    .inbox-search {
        position: relative;
        padding: 0 16px 10px;
    }

    .inbox-search i {
        position: absolute;
        inset-inline-start: 28px;
        top: 50%;
        transform: translateY(calc(-50% - 5px));
        color: var(--ib-muted);
        font-size: 1.05rem;
        pointer-events: none;
    }

    .inbox-search input {
        width: 100%;
        height: 38px;
        border: 1px solid var(--ib-ln);
        border-radius: 10px;
        background: var(--ib-surface);
        padding-block: 0;
        padding-inline: 36px 12px;
        font-size: .84rem;
        color: var(--ib-ink);
        outline: none;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }

    .inbox-search input:focus {
        background: #fff;
        border-color: var(--ib-brand);
        box-shadow: 0 0 0 3px rgba(109, 74, 255, .12);
    }

    /* Legacy pill filter (unused markup kept compatible). */
    .platform-filter {
        display: flex;
        gap: 6px;
        padding: 0 14px 14px;
        flex-wrap: wrap;
    }

    .inbox-filter-bar {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 0 16px 12px;
        border-bottom: 1px solid var(--ib-ln);
    }

    .filter-pill-all {
        height: 30px;
        border: 1px solid transparent;
        border-radius: 8px;
        padding: 0 11px;
        font-size: .74rem;
        font-weight: 700;
        color: #fff;
        background: var(--ib-ink);
        flex-shrink: 0;
        transition: opacity .15s;
    }

    .filter-pill-all:hover {
        opacity: .88;
    }

    .filter-select {
        flex: 1 1 0;
        min-width: 0;
        height: 30px;
        border: 1px solid var(--ib-ln);
        background-color: #fff;
        color: var(--ib-ink2);
        border-radius: 8px;
        padding-block: 0;
        padding-inline: 9px 22px;
        font-size: .74rem;
        font-weight: 600;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%238a92a3' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 10px 6px;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: border-color .15s;
    }

    [dir="rtl"] .filter-select {
        background-position: left 8px center;
    }

    .filter-select:hover {
        border-color: #cfd4de;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--ib-brand);
    }

    .conversation-list {
        overflow-y: auto;
        flex: 1;
        padding: 6px 8px;
    }

    .conversation-item {
        display: flex;
        gap: 12px;
        padding: 11px 10px;
        margin-bottom: 2px;
        cursor: pointer;
        border-radius: 12px;
        position: relative;
        transition: background .15s ease;
    }

    .conversation-item:hover {
        background: #f6f7fa;
    }

    .conversation-item.active {
        background: var(--ib-brand-soft);
    }

    .conversation-item.active::before {
        content: '';
        position: absolute;
        inset-inline-start: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-start-end-radius: 3px;
        border-end-end-radius: 3px;
        background: var(--ib-brand);
    }

    .conversation-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #e8eaf0;
        flex-shrink: 0;
        object-fit: cover;
        box-shadow: 0 0 0 1px var(--ib-ln);
    }

    .platform-dot {
        position: absolute;
        inset-inline-start: 29px;
        top: 28px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #fff;
        font-size: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    .conversation-item.active .platform-dot {
        border-color: var(--ib-brand-soft);
    }

    .platform-dot.facebook { background: #1877F2; }
    .platform-dot.instagram { background: linear-gradient(135deg,#f58529,#dd2a7b 55%,#8134af); }
    .platform-dot.whatsapp { background: #25D366; }
    .platform-dot.telegram { background: #229ED9; }
    .platform-dot.x { background: #0f1419; }
    .platform-dot.line { background: #06C755; }
    .platform-dot.zalo { background: #0068ff; }
    .platform-dot.discord { background: #5865F2; }
    .platform-dot.slack { background: #4A154B; }
    .platform-dot.teams { background: #5B5FC7; }
    .platform-dot.google_chat { background: #1a73e8; }
    .platform-dot.matrix { background: #0DBD8B; }

    .conversation-meta {
        flex: 1;
        min-width: 0;
        padding-top: 1px;
    }

    .conversation-name {
        font-weight: 600;
        font-size: .88rem;
        color: var(--ib-ink);
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 3px;
    }

    .conversation-name > span:first-child {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .conversation-preview {
        color: var(--ib-muted);
        font-size: .8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .conversation-item:has(.unread-badge) .conversation-name > span:first-child,
    .conversation-item:has(.unread-badge) .conversation-preview > span:first-child {
        color: var(--ib-ink);
        font-weight: 700;
    }

    .conversation-time {
        font-size: .7rem;
        font-weight: 500;
        color: var(--ib-muted);
        flex-shrink: 0;
    }

    .unread-badge {
        min-width: 20px;
        height: 20px;
        display: inline-grid;
        place-items: center;
        background: var(--ib-brand);
        color: #fff;
        border-radius: 999px;
        font-size: .66rem;
        font-weight: 700;
        padding: 0 6px;
        flex-shrink: 0;
    }

    .conversation-list-empty,
    .conversation-list-nomatch {
        padding: 40px 20px;
        text-align: center;
        color: var(--ib-muted);
        font-size: .84rem;
    }

    .conversation-list-nomatch {
        display: none;
    }

    /* ---------- Thread ---------- */
    .inbox-thread {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        background: var(--ib-surface);
    }

    .thread-header {
        padding: 12px 20px;
        min-height: 68px;
        border-bottom: 1px solid var(--ib-ln);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: #fff;
    }

    .thread-header .fw-semibold {
        color: var(--ib-ink);
        font-size: .95rem;
    }

    .platform-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .66rem;
        font-weight: 700;
        color: #fff;
        padding: 2px 8px;
        border-radius: 6px;
        text-transform: capitalize;
        letter-spacing: .01em;
    }

    .thread-messages {
        flex: 1;
        overflow-y: auto;
        padding: 22px 26px;
        display: flex;
        flex-direction: column;
        background-color: var(--ib-surface);
        background-image: radial-gradient(rgba(22, 27, 43, .035) 1px, transparent 1px);
        background-size: 18px 18px;
    }

    /* Day divider: "Today" / "Yesterday" / "Aug 25, 2026". */
    .date-separator {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 18px 0 10px;
    }

    .date-separator::before,
    .date-separator::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--ib-ln);
    }

    .date-separator:first-child {
        margin-top: 0;
    }

    .date-separator span {
        background: #fff;
        border: 1px solid var(--ib-ln);
        color: var(--ib-muted);
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 999px;
    }

    .message-row {
        display: flex;
        margin-top: 14px;
    }

    .date-separator + .message-row {
        margin-top: 0;
    }

    /* Same sender within a few minutes: grouped tightly. */
    .message-row.is-grouped {
        margin-top: 3px;
    }

    .message-row.outbound {
        justify-content: flex-end;
    }

    .message-row-inner {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        max-width: 70%;
        min-width: 0;
    }

    .message-row.outbound .message-row-inner {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        margin-bottom: 20px;
    }

    /* Keeps grouped bubbles aligned with the one showing the avatar. */
    .message-row.is-grouped .message-avatar {
        visibility: hidden;
    }

    /* align-items flex-start/end makes bubbles hug their content instead
       of stretching to the column width. */
    .message-col {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
    }

    .message-row.outbound .message-col {
        align-items: flex-end;
    }

    .message-bubble {
        width: fit-content;
        max-width: 100%;
        align-self: flex-start;
        padding: 10px 14px;
        border-radius: 16px;
        line-height: 1.5;
        font-size: .9rem;
    }

    /* pre-wrap only on the text: keeps the customer's line breaks without
       turning template indentation into visible spaces. */
    .message-bubble-text {
        white-space: pre-wrap;
        word-break: break-word;
    }

    .message-bubble:empty {
        display: none;
    }

    .message-row.inbound .message-bubble {
        background: #fff;
        color: var(--ib-ink);
        border: 1px solid var(--ib-ln);
        box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
        border-end-start-radius: 5px;
    }

    .message-row.outbound .message-bubble {
        align-self: flex-end;
        background: var(--ib-grad);
        color: #fff;
        border-end-end-radius: 5px;
        box-shadow: 0 4px 12px rgba(109, 74, 255, .2);
    }

    .message-row.outbound .message-bubble a {
        color: #fff;
        text-decoration: underline;
    }

    .message-row.outbound .message-bubble.failed {
        background: #fef3f2;
        color: var(--ib-danger);
        border: 1px solid #fecdca;
        box-shadow: none;
    }

    .message-meta {
        font-size: .68rem;
        color: var(--ib-muted);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .message-row.outbound .message-meta {
        justify-content: flex-end;
    }

    .message-status-icon {
        font-size: .82rem;
        line-height: 1;
        display: inline-flex;
    }

    .message-status-icon.sent {
        color: var(--ib-brand);
    }

    .message-status-icon.failed {
        color: var(--ib-danger);
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
        border: 1px dashed #d0d5dd !important;
        color: var(--ib-muted) !important;
        box-shadow: none !important;
        font-style: italic;
        font-size: .84rem;
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
        font-size: .9rem;
        resize: vertical;
        color: var(--ib-ink);
    }

    .message-edit-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
        margin-top: 6px;
    }

    .message-edit-actions button {
        border: none;
        border-radius: 8px;
        padding: 3px 12px;
        font-size: .75rem;
        font-weight: 600;
    }

    .message-edit-save {
        background: #fff;
        color: var(--ib-brand-ink);
    }

    .message-edit-cancel {
        background: rgba(255, 255, 255, .25);
        color: inherit;
    }

    .message-attachment img,
    .message-attachment video {
        max-width: 100%;
        max-height: 320px;
        border-radius: 12px;
        margin-bottom: 6px;
        display: block;
    }

    /* ---------- Composer ---------- */
    .thread-composer {
        border-top: 1px solid var(--ib-ln);
        padding: 14px 20px 16px;
        background: #fff;
    }

    .composer-row {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        padding: 6px;
        border: 1px solid var(--ib-ln);
        border-radius: 14px;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }

    .composer-row:focus-within {
        border-color: var(--ib-brand);
        box-shadow: 0 0 0 3px rgba(109, 74, 255, .12);
    }

    .composer-preview {
        margin-bottom: 10px;
    }

    .composer-preview-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--ib-surface);
        border: 1px solid var(--ib-ln);
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
        background: var(--ib-brand-soft);
        color: var(--ib-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        flex-shrink: 0;
    }

    .composer-preview-name {
        font-size: .8rem;
        color: var(--ib-ink2);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .composer-preview-remove {
        border: none;
        background: transparent;
        color: var(--ib-muted);
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
        background: #fef3f2;
        color: var(--ib-danger);
    }

    .thread-composer textarea,
    .thread-composer textarea.form-control {
        flex: 1;
        min-width: 0;
        resize: none;
        border: none;
        background: transparent;
        box-shadow: none;
        padding: 8px 10px;
        font-size: .9rem;
        color: var(--ib-ink);
        min-height: 38px;
        max-height: 160px;
    }

    .thread-composer textarea:focus {
        background: transparent;
        border: none;
        box-shadow: none;
    }

    .btn-composer-attach {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: var(--ib-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
        transition: background .15s, color .15s;
    }

    .btn-composer-attach:hover {
        background: var(--ib-ln-soft);
        color: var(--ib-ink);
    }

    .btn-ai-compose {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 38px;
        border: 1px solid #ddd3ff;
        background: var(--ib-brand-soft);
        color: var(--ib-brand-ink);
        border-radius: 10px;
        padding: 0 12px;
        font-size: .8rem;
        font-weight: 600;
        flex-shrink: 0;
        transition: background .15s;
    }

    .btn-ai-compose:hover {
        background: #e9e2ff;
        color: var(--ib-brand-ink);
    }

    .btn-composer-send {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 38px;
        border: none;
        border-radius: 10px;
        padding: 0 18px;
        font-size: .84rem;
        font-weight: 600;
        color: #fff;
        background: var(--ib-grad);
        box-shadow: 0 4px 12px rgba(109, 74, 255, .28);
        flex-shrink: 0;
        transition: box-shadow .15s, transform .15s;
    }

    .btn-composer-send:hover {
        color: #fff;
        box-shadow: 0 6px 16px rgba(109, 74, 255, .36);
        transform: translateY(-1px);
    }

    .btn-composer-send:disabled {
        opacity: .65;
        transform: none;
    }

    /* ---------- Empty states ---------- */
    .inbox-empty {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 6px;
        color: var(--ib-muted);
        font-size: .86rem;
        text-align: center;
        padding: 24px;
    }

    .inbox-empty strong {
        color: var(--ib-ink);
        font-size: 1rem;
    }

    .inbox-empty-icon {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: var(--ib-brand-soft);
        color: var(--ib-brand);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        margin-bottom: 8px;
    }

    /* ---------- Thread header ---------- */
    .thread-header-identity {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .thread-header-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 3px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .7rem;
        font-weight: 600;
        color: var(--ib-muted);
    }

    .status-pill-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #c4c9d4;
    }

    .status-pill.open {
        color: var(--ib-ok);
    }

    .status-pill.open .status-pill-dot {
        background: #17b26a;
        box-shadow: 0 0 0 3px rgba(23, 178, 106, .15);
    }

    .thread-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .btn-thread-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 34px;
        border: 1px solid var(--ib-ln);
        background: #fff;
        color: var(--ib-ink);
        border-radius: 9px;
        padding: 0 12px;
        font-size: .78rem;
        font-weight: 600;
        transition: background .15s, border-color .15s;
    }

    .btn-thread-action i {
        color: var(--ib-brand);
        font-size: 1rem;
    }

    .btn-thread-action:hover {
        background: #fafbfd;
        border-color: #cfd4de;
        color: var(--ib-ink);
    }

    .btn-details-toggle {
        border: 1px solid var(--ib-ln);
        background: #fff;
        color: var(--ib-muted);
        border-radius: 9px;
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        transition: background .15s, color .15s;
    }

    .btn-details-toggle:hover {
        background: #fafbfd;
        color: var(--ib-ink);
    }

    /* Channel of each message, next to its timestamp. */
    .message-platform-badge {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 8px;
        flex-shrink: 0;
    }

    /* ---------- AI Copilot panel (opened by "AI Compose") ---------- */
    .ai-copilot-panel {
        display: none;
        margin: 0 20px 12px;
        border: 1px solid #e4dcff;
        border-radius: 14px;
        background: linear-gradient(180deg, #faf8ff 0%, #f4f0ff 100%);
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(109, 74, 255, .08);
    }

    .ai-copilot-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid #ebe5ff;
    }

    .ai-copilot-title {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: .82rem;
        font-weight: 700;
        color: var(--ib-brand-ink);
    }

    .ai-copilot-title i {
        font-size: 15px;
    }

    .ai-copilot-close {
        border: none;
        background: transparent;
        color: var(--ib-muted);
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 7px;
    }

    .ai-copilot-close:hover {
        background: #ebe5ff;
        color: var(--ib-ink);
    }

    .ai-copilot-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 12px 14px;
    }

    .ai-copilot-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid var(--ib-ln);
        background: #fff;
        color: var(--ib-ink);
        border-radius: 10px;
        padding: 9px 11px;
        font-size: .78rem;
        font-weight: 600;
        text-align: start;
        transition: border-color .15s, box-shadow .15s;
    }

    .ai-copilot-btn:hover {
        border-color: #cfc2ff;
        box-shadow: 0 2px 8px rgba(109, 74, 255, .1);
    }

    .ai-copilot-btn i {
        font-size: 15px;
        color: var(--ib-brand);
        flex-shrink: 0;
    }

    /* ---------- Chat details panel ---------- */
    .chat-details-panel {
        width: 290px;
        flex-shrink: 0;
        border-left: 1px solid var(--ib-ln);
        background: #fff;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .chat-details-panel.is-hidden {
        display: none;
    }

    .details-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 68px;
        padding: 12px 18px;
        border-bottom: 1px solid var(--ib-ln);
        font-weight: 700;
        font-size: .95rem;
        color: var(--ib-ink);
    }

    .details-close {
        border: none;
        background: transparent;
        color: var(--ib-muted);
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .details-close:hover {
        background: var(--ib-ln-soft);
        color: var(--ib-ink);
    }

    .details-section {
        padding: 16px 18px;
        border-bottom: 1px solid var(--ib-ln-soft);
    }

    .details-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ib-muted);
        margin-bottom: 10px;
    }

    .details-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: .83rem;
        margin-bottom: 8px;
    }

    .details-row:last-child {
        margin-bottom: 0;
    }

    .details-row-label {
        color: var(--ib-muted);
    }

    .details-row-value {
        color: var(--ib-ink);
        font-weight: 600;
        text-align: end;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .details-platform-icons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .details-platform-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 14px;
    }

    .details-empty {
        color: var(--ib-muted);
        font-size: .82rem;
    }

    .details-placeholder {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--ib-muted);
        font-size: .8rem;
        background: var(--ib-surface);
        border: 1px dashed var(--ib-ln);
        border-radius: 10px;
        padding: 10px 12px;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 1399.98px) {
        .chat-details-panel { width: 260px; }
        .inbox-sidebar { width: 310px; }
    }

    @media (max-width: 767.98px) {
        .inbox-shell { flex-direction: column; height: auto; }
        .inbox-sidebar { width: 100%; max-height: 340px; border-right: none; border-bottom: 1px solid var(--ib-ln); }
        .inbox-thread { min-height: 560px; }
        .message-row-inner { max-width: 88%; }
        .btn-ai-compose span { display: none; }
        .ai-copilot-actions { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="col-xxl-12 mb-0 inbox-page">
        {{-- Only reachable now via a channel connect started from this page
             (see ?return_to=dashboard on the Manage Channels modal's OAuth
             links) - admin.chats.channels has its own identical block for
             everything else. --}}
        @if (session('success'))
            <div class="inbox-alert inbox-alert-ok"><i class="bx bx-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="inbox-alert inbox-alert-err"><i class="bx bx-error-circle"></i> {{ session('error') }}</div>
        @endif

        @php
            $unreadTotal = $conversations->sum('unread_count');
            $openTotal = $conversations->where('status', 'open')->count();
            $unassignedTotal = $conversations->whereNull('assigned_user_id')->count();
        @endphp
        <div class="inbox-topbar">
            <div class="inbox-heading">
                <div class="inbox-heading-icon"><i class="bx bx-message-square-dots"></i></div>
                <div>
                    <h4 class="inbox-title">{{ __('Unified Inbox') }}</h4>
                    <p class="inbox-subtitle">
                        <span>{!! __(':count conversations', ['count' => '<strong>' . $conversations->count() . '</strong>']) !!}</span>
                        <span class="sep"></span>
                        <span class="{{ $unreadTotal ? 'is-unread' : '' }}">{!! __(':count unread', ['count' => '<strong>' . $unreadTotal . '</strong>']) !!}</span>
                        <span class="sep"></span>
                        <span>{!! __(':count open', ['count' => '<strong>' . $openTotal . '</strong>']) !!}</span>
                        <span class="sep"></span>
                        <span>{!! __(':count unassigned', ['count' => '<strong>' . $unassignedTotal . '</strong>']) !!}</span>
                    </p>
                </div>
            </div>
            <div class="inbox-actions">
                <button type="button" class="btn-ai-feature" id="exploreAiFeaturesBtn">
                    <i class="bx bx-bot"></i> {{ __('Explore AI Features') }}
                </button>
                {{--
                    "Connect" opens the quick-connect modal (#manageChannelsModal
                    below); "Channels" goes to the full admin.chats.channels
                    page (every account, X Chat PIN, credential forms).
                --}}
                <div class="inbox-btn-group">
                    <button type="button" class="inbox-btn" data-bs-toggle="modal" data-bs-target="#manageChannelsModal" title="{{ __('Connect a new channel') }}">
                        <i class="bx bx-plus"></i> {{ __('Connect') }}
                    </button>
                    <a href="{{ route('admin.chats.channels') }}" class="inbox-btn" title="{{ __('View and manage all connected accounts') }}">
                        <i class="bx bx-plug"></i> {{ __('Channels') }}
                    </a>
                </div>
            </div>
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
                'x'           => 'bxl-x-logo',
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
                    {{ __('Conversations') }}
                    <span class="inbox-sidebar-count">{{ $conversations->count() }}</span>
                </div>
                <label class="inbox-search mb-0">
                    <i class="bx bx-search"></i>
                    <input type="search" id="conversationSearch" placeholder="{{ __('Search name or message…') }}" autocomplete="off" aria-label="{{ __('Search conversations') }}">
                </label>
                <div class="inbox-filter-bar">
                    <button type="button" class="filter-pill-all" id="clearFiltersBtn" title="{{ __('Clear search and filters') }}">{{ __('All') }}</button>
                    <select class="filter-select" id="channelFilter" title="{{ __('Filter by channel') }}">
                        <option value="">{{ __('Channel') }}</option>
                        @foreach ($activePlatforms as $platform)
                            <option value="{{ $platform }}">{{ $platformLabel($platform) }}</option>
                        @endforeach
                    </select>
                    <select class="filter-select" id="statusFilter" title="{{ __('Filter by status') }}">
                        <option value="">{{ __('Status') }}</option>
                        <option value="open">{{ __('Active') }}</option>
                        <option value="closed">{{ __('Closed') }}</option>
                        <option value="archived">{{ __('Archived') }}</option>
                    </select>
                    <select class="filter-select" id="agentFilter" title="{{ __('Filter by agent') }}">
                        <option value="">{{ __('Agent') }}</option>
                        <option value="unassigned">{{ __('Unassigned') }}</option>
                        @foreach ($assignableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="conversation-list" id="conversationList">
                    @forelse ($conversations as $conversation)
                        <div class="conversation-item {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}"
                             data-id="{{ $conversation->id }}"
                             data-platform="{{ $conversation->platform }}"
                             data-status="{{ $conversation->status }}"
                             data-agent="{{ $conversation->assigned_user_id ?: 'unassigned' }}">
                            <div style="position:relative">
                                <img class="conversation-avatar" src="{{ $conversation->customer_avatar_url ?: asset('assets/img/avatars/1.png') }}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                                <span class="platform-dot {{ $conversation->platform }}"><i class="bx {{ $platformIcons[$conversation->platform] ?? 'bx-message-rounded-dots' }}"></i></span>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-name">
                                    <span>{{ $conversation->customer_name ?: __('Unknown') }}</span>
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
                        <div class="conversation-list-empty">{{ __('No conversations yet. Connect a channel to start receiving messages.') }}</div>
                    @endforelse
                    <div class="conversation-list-nomatch" id="conversationNoMatch">{{ __('No conversations match your search or filters.') }}</div>
                </div>
            </div>

            <div class="inbox-thread" id="inboxThread">
                @if ($activeConversation)
                    @include('admin.chats.partials.thread', ['conversation' => $activeConversation, 'messages' => $messages])
                @else
                    <div class="inbox-empty">
                        <div class="inbox-empty-icon"><i class="bx bx-message-square-dots"></i></div>
                        <strong>{{ __('Select a conversation') }}</strong>
                        <div>{{ __('Pick a chat on the left to read it and reply.') }}</div>
                    </div>
                @endif
            </div>

            <div class="chat-details-panel {{ $activeConversation ? '' : 'is-hidden' }}" id="chatDetailsPanel">
                @if ($activeConversation)
                    @include('admin.chats.partials.details-panel', [
                        'conversation' => $activeConversation,
                        'platformHistory' => $platformHistory,
                        'messageCount' => $messageCount,
                    ])
                @endif
            </div>
        </div>
    </div>

    {{--
        MANAGE CHANNELS MODAL - opened from the topbar button above instead
        of navigating to admin.chats.channels. Renders the same shared
        social-connect-modal component the Posts dashboard's Add Account
        modal and the Ads dashboard use - see
        resources/views/components/social-connect-modal.blade.php.

        Only the platforms with a genuinely one-click connect belong here;
        Telegram/LINE/Zalo/Discord/Teams/Matrix all need a multi-field
        credential form first, which is exactly what the full
        admin.chats.channels page is still for (linked from the modal
        footer below).

        WhatsApp and Google Chat aren't OAuth links (see each tile's
        'note' below) - clicking them switches to the same form-modal
        components admin/chats/channels.blade.php uses (x-whatsapp-
        connect-modal / x-google-chat-connect-modal), via the click
        handler further down this file, rather than a second copy of
        those forms.
    --}}
    @php
        // ?return_to=dashboard is read by SocialAccountController::redirect()
        // / MessageChannelController's redirect* methods (stashed in
        // session, since the OAuth round-trip won't echo an arbitrary
        // query param back) so the corresponding callback sends the user
        // back here instead of admin.chats.channels/admin.posts.create -
        // the full channels page's own identical-looking links omit this,
        // so their behavior is unchanged.
        $manageChannelsPlatforms = [
            // Meta connects once in the Connection Hub.
            ['key' => 'facebook',    'class' => 'facebook',    'icon' => 'bxl-facebook',  'label' => __('Meta Messenger'),    'url' => \App\Support\Connections\HubLink::for('facebook'), 'note' => \App\Support\Connections\HubLink::note()],
            ['key' => 'instagram',   'class' => 'instagram',   'icon' => 'bxl-instagram', 'label' => __('Instagram Messenger'), 'url' => \App\Support\Connections\HubLink::for('instagram'), 'note' => \App\Support\Connections\HubLink::note()],
            ['key' => 'x',           'class' => 'twitter',     'icon' => 'bxl-x-logo',   'label' => __('X Messenger'),       'url' => \App\Support\Connections\HubLink::for('x'), 'note' => \App\Support\Connections\HubLink::note()],
            // No posting-permission gate here (unlike the Posts dashboard's
            // Add Account tiles) - has_messaging_permission is what actually
            // matters for the inbox.
            ['key' => 'tiktok',      'class' => 'tiktok',      'icon' => 'bxl-tiktok',    'label' => __('TikTok Messenger'),  'url' => route('admin.messaging.auth.tiktok.redirect') . '?return_to=dashboard'],
            ['key' => 'whatsapp-hub', 'class' => 'whatsapp',    'icon' => 'bxl-whatsapp',  'label' => 'WhatsApp',          'url' => \App\Support\Connections\HubLink::for('whatsapp'), 'note' => \App\Support\Connections\HubLink::note()],
            ['key' => 'google_chat', 'class' => 'google_chat', 'icon' => 'bx-message-rounded-dots', 'label' => 'Google Chat', 'url' => '#', 'note' => __('Paste a service account key')],
        ];
    @endphp
    <x-social-connect-modal
        id="manageChannelsModal"
        :title="__('Manage Channels')"
        :subtitle="__('Connect an account and it\'s ready in your inbox above - no page reload.')"
        :platforms="$manageChannelsPlatforms"
    />
    <x-whatsapp-connect-modal id="whatsappQuickModal" />
    <x-google-chat-connect-modal id="googleChatQuickModal" />
@endsection

@push('scripts')
    @php
        // Every string the inbox JS renders (t('...') below), translated
        // server-side so ar.json covers the live-rendered UI too.
        $inboxI18n = collect([
            ':name\'s Contact Details',
            'AI Compose',
            'AI Copilot',
            'AI Sentiment Analysis',
            'AI conversation summary - coming soon',
            'AI-powered replies are on the roadmap and not wired up yet.',
            'Active',
            'Adjust Tone (Friendly/Helpful)',
            'Agent',
            'Archived',
            'Attachment',
            'Cancel',
            'Chat Details',
            'Chat details',
            'Closed',
            'Coming soon',
            'Delete',
            'Delete this message?',
            'Draft a Professional Reply',
            'E-mail',
            'Edit',
            'Error',
            'Failed to delete message.',
            'Failed to edit message.',
            'Failed to send',
            'Failed to send message.',
            'Hide',
            'Hide panel',
            'Last activity',
            'Messages',
            'Name',
            'No open cases.',
            'Not available yet',
            'Open',
            'Open AI Copilot',
            'Open Cases',
            'Phone',
            'Platform History',
            'Recent Activity',
            'Remove attachment',
            'Save',
            'Send',
            'Sent',
            'Summarize',
            'Summarize this conversation',
            'This message was deleted',
            'This removes it on the customer\'s side too, not just yours.',
            'Today',
            'Translate',
            'Type a reply...',
            'Unassigned',
            'Unknown',
            'Yesterday',
            'edited',
        ])->mapWithKeys(fn ($s) => [$s => __($s)]);
    @endphp
    <script>
        // Page language for dates/times/relative times (html lang = app locale).
        const pageLocale = document.documentElement.lang || 'en';
        const inboxI18n = @json($inboxI18n);
        const t = (s) => inboxI18n[s] ?? s;
        function relativeTime(iso) {
            const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
            const rtf = new Intl.RelativeTimeFormat(pageLocale, { numeric: 'auto' });
            for (const [unit, size] of units) {
                if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
            }
            return rtf.format(0, 'second');
        }
    </script>
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
            const copilotFindAnswerUrlTemplate = "{{ route('admin.chats.copilot.find-answer', ['conversation' => ':ID']) }}";
            const copilotFeedbackUrlTemplate = "{{ route('admin.chats.copilot.feedback', ['copilotMessage' => 'COPILOT_MESSAGE_ID']) }}";

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
            const platformIcons = @json($platformIcons);

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
                    renderDetailsPanel(res.conversation, res.platformHistory || [], res.messageCount || res.messages.length);
                });
            }

            $(document).on('click', '.conversation-item', function() {
                loadConversation($(this).data('id'));
            });

            function renderThread(conversation, messages) {
                const badgeColor = platformColors[conversation.platform] || '#6d28d9';
                const isActive = conversation.status === 'open';
                const statusLabel = isActive ? t('Active') : (conversation.status ? t(conversation.status.charAt(0).toUpperCase() + conversation.status.slice(1)) : t('Open'));

                let html = `
                    <div class="thread-header">
                        <div class="thread-header-identity">
                            <img class="conversation-avatar" src="${conversation.customer_avatar_url || '{{ asset('assets/img/avatars/1.png') }}'}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                            <div>
                                <div class="fw-semibold">${escapeHtml(conversation.customer_name || t('Unknown'))}</div>
                                <div class="thread-header-badges">
                                    <span class="platform-badge" style="background:${badgeColor}">${platformLabel(conversation.platform)}</span>
                                    <span class="status-pill ${isActive ? 'open' : ''}">
                                        <span class="status-pill-dot"></span>
                                        ${statusLabel}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="thread-header-actions">
                            <button type="button" class="btn-thread-action" id="summarizeBtn" title="${t('AI conversation summary - coming soon')}"><i class="bx bx-list-check"></i> ${t('Summarize')}</button>
                            <button type="button" class="btn-details-toggle" id="toggleDetailsBtn" title="${t('Chat details')}"><i class="bx bx-info-circle"></i></button>
                        </div>
                    </div>
                    <div class="thread-messages" id="threadMessages">`;

                let previousMessage = null;
                messages.forEach(m => {
                    html += renderMessage(m, conversation.platform, previousMessage);
                    previousMessage = m;
                });

                html += `</div>
                    <div class="ai-copilot-panel" id="aiCopilotPanel">
                        <div class="ai-copilot-header">
                            <span class="ai-copilot-title"><i class="bx bx-bulb"></i> ${t('AI Copilot')}</span>
                            <button type="button" class="ai-copilot-close" id="aiCopilotCloseBtn" title="${t('Hide')}"><i class="bx bx-x"></i></button>
                        </div>
                        <div id="copilotWidgetMount"></div>
                        <div class="ai-copilot-actions">
                            <button type="button" class="ai-copilot-btn" data-ai-action="draft"><i class="bx bx-edit-alt"></i> ${t('Draft a Professional Reply')}</button>
                            <button type="button" class="ai-copilot-btn" data-ai-action="summarize"><i class="bx bx-list-ul"></i> ${t('Summarize this conversation')}</button>
                            <button type="button" class="ai-copilot-btn" data-ai-action="tone"><i class="bx bx-happy-alt"></i> ${t('Adjust Tone (Friendly/Helpful)')}</button>
                            <button type="button" class="ai-copilot-btn" data-ai-action="translate"><i class="bx bx-globe"></i> ${t('Translate')}</button>
                        </div>
                    </div>
                    <div class="thread-composer">
                        <form id="replyForm" enctype="multipart/form-data" class="w-100">
                            <input type="hidden" name="conversation_id" value="${conversation.id}">
                            <div class="composer-preview d-none" id="composerPreview"></div>
                            <div class="composer-row">
                                <textarea name="body" class="form-control" rows="1" placeholder="${t('Type a reply...')}"></textarea>
                                <input type="file" name="media" id="replyMedia" hidden accept="image/*,video/*">
                                <button type="button" class="btn-composer-attach" onclick="document.getElementById('replyMedia').click()"><i class="bx bx-paperclip"></i></button>
                                <button type="button" class="btn-ai-compose" id="aiComposeToggleBtn" title="${t('Open AI Copilot')}"><i class="bx bx-pen"></i><span>${t('AI Compose')}</span></button>
                                <button type="submit" class="btn-composer-send">${t('Send')}</button>
                            </div>
                        </form>
                    </div>`;

                $('#inboxThread').html(html);
                scrollThreadToBottom();
                window.currentConversationId = conversation.id;
                window.currentConversationPlatform = conversation.platform;
                window.currentConversationAvatar = conversation.customer_avatar_url || '{{ asset('assets/img/avatars/1.png') }}';
                mountCopilotWidget(conversation.id);
            }

            // #inboxThread's whole subtree (including any previously
            // mounted copilot widget) is torn down and rebuilt by the
            // .html(html) call above on every conversation switch - a
            // Vue instance mounted into a node jQuery just replaced would
            // be silently orphaned, not reused, so this destroys the old
            // instance and mounts a fresh one against the fresh
            // #copilotWidgetMount node the new HTML just introduced.
            // copilot-find-answer is registered globally via
            // Vue.component() in app.js, so any new Vue() instance here
            // can reference it by tag name without needing to be a child
            // of the page's main #app root.
            let copilotWidgetInstance = null;
            function mountCopilotWidget(conversationId) {
                if (copilotWidgetInstance) {
                    copilotWidgetInstance.$destroy();
                    copilotWidgetInstance = null;
                }

                const mountEl = document.getElementById('copilotWidgetMount');
                if (!mountEl || typeof Vue === 'undefined') {
                    return;
                }

                copilotWidgetInstance = new Vue({
                    render: h => h('copilot-find-answer', {
                        props: {
                            findAnswerUrl: copilotFindAnswerUrlTemplate.replace(':ID', conversationId),
                            feedbackUrlTemplate: copilotFeedbackUrlTemplate,
                        },
                    }),
                }).$mount(mountEl);
            }

            function renderDetailsPanel(conversation, platformHistory, messageCount) {
                const $panel = $('#chatDetailsPanel');
                const name = escapeHtml(conversation.customer_name || t('Unknown'));
                const agentName = escapeHtml((conversation.assigned_user && conversation.assigned_user.name) || t('Unassigned'));
                const meta = conversation.meta || {};

                let icons = '';
                (platformHistory || []).forEach(p => {
                    const color = platformColors[p] || '#6d28d9';
                    const icon = platformIcons[p] || 'bx-message-rounded-dots';
                    icons += `<span class="details-platform-icon" style="background:${color}" title="${platformLabel(p)}"><i class="bx ${icon}"></i></span>`;
                });

                const lastActivity = conversation.last_message_at ? relativeTime(conversation.last_message_at) : '—';

                $panel.removeClass('is-hidden').html(`
                    <div class="details-header">
                        ${t('Chat Details')}
                        <button type="button" class="details-close" id="closeDetailsBtn" title="${t('Hide panel')}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="details-section">
                        <div class="details-section-title">${t(':name\'s Contact Details').replace(':name', name)}</div>
                        <div class="details-row"><span class="details-row-label">${t('Name')}</span><span class="details-row-value">${name}</span></div>
                        <div class="details-row"><span class="details-row-label">${t('Agent')}</span><span class="details-row-value">${agentName}</span></div>
                        ${meta.phone ? `<div class="details-row"><span class="details-row-label">${t('Phone')}</span><span class="details-row-value">${escapeHtml(meta.phone)}</span></div>` : ''}
                        ${meta.email ? `<div class="details-row"><span class="details-row-label">${t('E-mail')}</span><span class="details-row-value">${escapeHtml(meta.email)}</span></div>` : ''}
                    </div>
                    <div class="details-section">
                        <div class="details-section-title">${t('Platform History')}</div>
                        <div class="details-platform-icons">${icons}</div>
                    </div>
                    <div class="details-section">
                        <div class="details-section-title">${t('Recent Activity')}</div>
                        <div class="details-row"><span class="details-row-label">${t('Messages')}</span><span class="details-row-value">${messageCount}</span></div>
                        <div class="details-row"><span class="details-row-label">${t('Last activity')}</span><span class="details-row-value">${lastActivity}</span></div>
                    </div>
                    <div class="details-section">
                        <div class="details-section-title">${t('Open Cases')}</div>
                        <div class="details-empty">${t('No open cases.')}</div>
                    </div>
                    <div class="details-section">
                        <div class="details-section-title">${t('AI Sentiment Analysis')}</div>
                        <div class="details-placeholder"><i class="bx bx-time-five"></i> ${t('Not available yet')}</div>
                    </div>
                `);
            }

            function escapeAttr(str) {
                return (str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            // Two messages sit close together (no divider gap) when
            // they're the same side of the conversation and land within
            // a few minutes of each other, same day - the same clustering
            // rule WhatsApp/Messenger/Slack use.
            function shouldGroupWithPrevious(current, previous) {
                if (!previous || !previous.direction || !previous.created_at) return false;
                if (current.direction !== previous.direction) return false;
                if (!isSameDay(previous.created_at, current.created_at)) return false;
                const curTime = new Date(current.created_at).getTime();
                const prevTime = new Date(previous.created_at).getTime();
                if (isNaN(curTime) || isNaN(prevTime)) return false;
                return Math.abs(curTime - prevTime) < 3 * 60 * 1000;
            }

            function isSameDay(aIso, bIso) {
                const a = new Date(aIso), b = new Date(bIso);
                return a.toDateString() === b.toDateString();
            }

            function dateSeparatorLabel(iso) {
                const d = new Date(iso);
                const today = new Date();
                const yesterday = new Date();
                yesterday.setDate(today.getDate() - 1);
                if (d.toDateString() === today.toDateString()) return t('Today');
                if (d.toDateString() === yesterday.toDateString()) return t('Yesterday');
                const opts = { month: 'short', day: 'numeric' };
                if (d.getFullYear() !== today.getFullYear()) opts.year = 'numeric';
                return d.toLocaleDateString(pageLocale, opts);
            }

            function formatMessageTime(iso) {
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                return d.toLocaleTimeString(pageLocale, { hour: 'numeric', minute: '2-digit' });
            }

            function renderAvatarHtml(direction) {
                if (direction !== 'inbound') return '';
                const src = window.currentConversationAvatar || '{{ asset('assets/img/avatars/1.png') }}';
                return `<img class="message-avatar" src="${src}" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">`;
            }

            function renderMessage(m, platform, previous) {
                let separatorHtml = '';
                if (!previous || !isSameDay(previous.created_at, m.created_at)) {
                    separatorHtml = `<div class="date-separator"><span>${dateSeparatorLabel(m.created_at)}</span></div>`;
                }

                const isGrouped = shouldGroupWithPrevious(m, previous);

                if (m.deleted_at) {
                    return separatorHtml + renderDeletedMessageRow(m, isGrouped);
                }

                let attachmentHtml = '';
                (m.attachments || []).forEach(a => {
                    if (a.type === 'image') attachmentHtml += `<div class="message-attachment"><img src="${a.url}"></div>`;
                    else if (a.type === 'video') attachmentHtml += `<div class="message-attachment"><video src="${a.url}" controls></video></div>`;
                    else attachmentHtml += `<div class="message-attachment"><a href="${a.url}" target="_blank">📎 ${t('Attachment')}</a></div>`;
                });

                const failedClass = m.status === 'failed' ? 'failed' : '';
                const canEdit = m.direction === 'outbound' && m.status === 'sent' && editCapablePlatforms.includes(platform);
                const canDelete = m.direction === 'outbound' && m.status === 'sent' && deleteCapablePlatforms.includes(platform);

                let actionsHtml = '';
                if (canEdit || canDelete) {
                    actionsHtml = '<span class="message-actions">';
                    if (canEdit) actionsHtml += '<button type="button" class="message-action-btn" data-action="edit" title="' + t('Edit') + '"><i class="bx bx-pencil"></i></button>';
                    if (canDelete) actionsHtml += '<button type="button" class="message-action-btn" data-action="delete" title="' + t('Delete') + '"><i class="bx bx-trash"></i></button>';
                    actionsHtml += '</span>';
                }

                let statusIconHtml = '';
                if (m.direction === 'outbound') {
                    if (m.status === 'failed') statusIconHtml = '<i class="bx bx-error-circle message-status-icon failed" title="' + t('Failed to send') + '"></i>';
                    else if (m.status === 'sent') statusIconHtml = '<i class="bx bx-check message-status-icon sent" title="' + t('Sent') + '"></i>';
                }

                const badgeColor = platformColors[platform] || '#6d28d9';
                const badgeIcon = platformIcons[platform] || 'bx-message-rounded-dots';
                const platformBadgeHtml = `<span class="message-platform-badge" style="background:${badgeColor}" title="${platformLabel(platform)}"><i class="bx ${badgeIcon}"></i></span>`;

                return separatorHtml + `
                    <div class="message-row ${m.direction} ${isGrouped ? 'is-grouped' : ''}" data-message-id="${m.id}" data-direction="${m.direction}" data-created-at="${m.created_at}">
                        <div class="message-row-inner">
                            ${renderAvatarHtml(m.direction)}
                            <div class="message-col">
                                <div class="message-bubble ${failedClass}" data-message-body="${escapeAttr(m.body)}">${attachmentHtml}${m.body ? `<span class="message-bubble-text">${escapeHtml(m.body.trim())}</span>` : ''}</div>
                                <div class="message-meta text-${m.direction === 'outbound' ? 'end' : 'start'}">
                                    ${platformBadgeHtml}
                                    <span class="message-meta-text">${formatMessageTime(m.created_at)}${m.edited_at ? ' · ' + t('edited') : ''}</span>
                                    ${statusIconHtml}
                                    ${actionsHtml}
                                </div>
                            </div>
                        </div>
                    </div>`;
            }

            function renderDeletedMessageRow(m, isGrouped) {
                return `
                    <div class="message-row ${m.direction} ${isGrouped ? 'is-grouped' : ''}" data-message-id="${m.id}" data-direction="${m.direction}" data-created-at="${m.created_at}">
                        <div class="message-row-inner">
                            ${renderAvatarHtml(m.direction)}
                            <div class="message-col">
                                <div class="message-bubble is-deleted">
                                    <i class="bx bx-block"></i> ${t('This message was deleted')}
                                </div>
                                <div class="message-meta text-${m.direction === 'outbound' ? 'end' : 'start'}">
                                    <span class="message-meta-text">${formatMessageTime(m.created_at)}</span>
                                </div>
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
                const $lastRow = $('#threadMessages .message-row').last();
                const previous = $lastRow.length ? {
                    direction: $lastRow.data('direction'),
                    created_at: $lastRow.data('created-at'),
                } : null;
                $('#threadMessages').append(renderMessage(message, platform, previous));
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

                const removeBtn = '<button type="button" class="composer-preview-remove" title="' + t('Remove attachment') + '"><i class="bx bx-x"></i></button>';
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
                            Swal.fire(t('Error'), res.error || t('Failed to send message.'), 'error');
                            return;
                        }
                        if (res.message && res.message.conversation_id == window.currentConversationId) {
                            appendMessageIfNew(res.message, window.currentConversationPlatform);
                        }
                    },
                    error: function() {
                        Swal.fire(t('Error'), t('Failed to send message.'), 'error');
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
                            <button type="button" class="message-edit-cancel">${t('Cancel')}</button>
                            <button type="button" class="message-edit-save">${t('Save')}</button>
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
                            Swal.fire(t('Error'), res.error || t('Failed to edit message.'), 'error');
                            loadConversation(window.currentConversationId);
                        }
                        // On success the bubble updates via the
                        // message.updated broadcast, not here directly.
                    },
                    error: function(xhr) {
                        Swal.fire(t('Error'), xhr.responseJSON?.error || t('Failed to edit message.'), 'error');
                        loadConversation(window.currentConversationId);
                    }
                });
            });

            $(document).on('click', '.message-action-btn[data-action="delete"]', function() {
                const $row = $(this).closest('.message-row');
                const messageId = $row.data('message-id');

                Swal.fire({
                    title: t('Delete this message?'),
                    text: t('This removes it on the customer\'s side too, not just yours.'),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: t('Delete'),
                    confirmButtonColor: '#dc3545',
                }).then(function(result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: messageDeleteUrlTemplate.replace(':ID', messageId),
                        type: 'DELETE',
                        success: function(res) {
                            if (!res.success) {
                                Swal.fire(t('Error'), res.error || t('Failed to delete message.'), 'error');
                            }
                            // On success the bubble turns into the "deleted"
                            // placeholder via the message.updated broadcast.
                        },
                        error: function(xhr) {
                            Swal.fire(t('Error'), xhr.responseJSON?.error || t('Failed to delete message.'), 'error');
                        }
                    });
                });
            });

            // ------------------------------------------------------------------
            // CHANNEL / STATUS / AGENT FILTERS - all client-side over the
            // already-loaded conversation list (it's already fully in the
            // DOM), so no extra request per filter change. "All" clears
            // every select back to its default option.
            // ------------------------------------------------------------------
            function applyConversationFilters() {
                const channel = $('#channelFilter').val();
                const status = $('#statusFilter').val();
                const agent = $('#agentFilter').val();
                const query = ($('#conversationSearch').val() || '').trim().toLowerCase();
                let shown = 0;

                $('.conversation-item').each(function() {
                    const $item = $(this);
                    const matches = (!channel || $item.data('platform') === channel)
                        && (!status || $item.data('status') === status)
                        && (!agent || String($item.data('agent')) === agent)
                        // Name + last message preview, as shown in the list.
                        && (!query || $item.find('.conversation-meta').text().toLowerCase().includes(query));
                    $item.toggle(matches);
                    shown += matches ? 1 : 0;
                });

                $('#conversationNoMatch').toggle($('.conversation-item').length > 0 && shown === 0);
            }

            $('#channelFilter, #statusFilter, #agentFilter').on('change', applyConversationFilters);
            $('#conversationSearch').on('input', applyConversationFilters);

            $('#clearFiltersBtn').on('click', function() {
                $('#channelFilter, #statusFilter, #agentFilter, #conversationSearch').val('');
                applyConversationFilters();
            });

            // Arriving with ?platform= (sidebar / another module keeps the
            // working platform - App\Support\WorkContext): pre-filter the
            // list to that channel, when this inbox has one.
            (function () {
                const platform = new URLSearchParams(window.location.search).get('platform');
                if (platform && $(`#channelFilter option[value="${CSS.escape(platform)}"]`).length) {
                    $('#channelFilter').val(platform);
                    applyConversationFilters();
                }
            })();

            // ------------------------------------------------------------------
            // AI FEATURES - Summarize / Copilot actions / Explore AI Features
            // are presentational for now (no AI backend wired up yet). A
            // toast rather than silent no-ops so it's honest about not being
            // implemented instead of looking broken.
            // ------------------------------------------------------------------
            function aiComingSoonToast() {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: t('Coming soon'),
                        text: t('AI-powered replies are on the roadmap and not wired up yet.'),
                        confirmButtonColor: '#6d28d9',
                    });
                } else {
                    alert(t('Coming soon') + ' - ' + t('AI-powered replies are on the roadmap and not wired up yet.'));
                }
            }

            $(document).on('click', '#exploreAiFeaturesBtn, #summarizeBtn, .ai-copilot-btn', aiComingSoonToast);

            $(document).on('click', '#aiComposeToggleBtn', function() {
                $('#aiCopilotPanel').toggle();
            });

            $(document).on('click', '#aiCopilotCloseBtn', function() {
                $('#aiCopilotPanel').hide();
            });

            // ------------------------------------------------------------------
            // CHAT DETAILS PANEL toggle
            // ------------------------------------------------------------------
            $(document).on('click', '#toggleDetailsBtn', function() {
                $('#chatDetailsPanel').toggleClass('is-hidden');
            });

            $(document).on('click', '#closeDetailsBtn', function() {
                $('#chatDetailsPanel').addClass('is-hidden');
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
                                    $row.find('.message-meta-text').text().replace(' · ' + t('edited'), '') + (e.message.edited_at ? ' · ' + t('edited') : '')
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

    <script>
        // WhatsApp/Google Chat's tiles in #manageChannelsModal are '#'
        // placeholders, not OAuth links (see the platforms array building
        // that modal, further up this file) - clicking one closes this
        // modal and opens the real credential-form modal instead, matching
        // the exact forms admin/chats/channels.blade.php uses.
        document.addEventListener('DOMContentLoaded', function () {
            var manageChannelsModalEl = document.getElementById('manageChannelsModal');
            if (!manageChannelsModalEl) return;

            var formModalTargets = {
                whatsapp: 'whatsappQuickModal',
                google_chat: 'googleChatQuickModal',
            };

            Object.keys(formModalTargets).forEach(function (platformKey) {
                var tile = document.querySelector('.social-card-link-' + platformKey);
                if (!tile) return;

                tile.addEventListener('click', function (e) {
                    e.preventDefault();

                    var targetModalEl = document.getElementById(formModalTargets[platformKey]);
                    if (!targetModalEl) return;

                    manageChannelsModalEl.addEventListener('hidden.bs.modal', function openTarget() {
                        manageChannelsModalEl.removeEventListener('hidden.bs.modal', openTarget);
                        bootstrap.Modal.getOrCreateInstance(targetModalEl).show();
                    }, { once: true });

                    bootstrap.Modal.getOrCreateInstance(manageChannelsModalEl).hide();
                });
            });
        });
    </script>
@endpush
