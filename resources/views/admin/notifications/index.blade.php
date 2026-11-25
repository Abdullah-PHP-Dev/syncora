@extends('layouts.app')
@section('title', __('notifications.ui.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v={{ filemtime(public_path('assets/css/notifications.css')) }}">
@endpush

@section('content')
<div class="account-page">
    <header class="account-heading" style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
        <div>
            <span class="account-eyebrow">{{ __('notifications.ui.title') }}</span>
            <h1>{{ __('notifications.ui.title') }}</h1>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('admin.notifications.settings') }}"><i class="bx bx-cog"></i> {{ __('notifications.ui.settings') }}</a>
    </header>

    <section class="account-card" id="ntf-center" data-feed="{{ $feedUrl }}" data-read-all="{{ route('admin.notifications.read-all') }}" data-clear="{{ route('admin.notifications.clear-read') }}">
        <div class="ntf-toolbar">
            <div class="ntf-tabs" role="group" aria-label="{{ __('notifications.ui.title') }}">
                <button type="button" class="ntf-tab" data-filter="all" aria-pressed="true">{{ __('notifications.ui.all') }}</button>
                <button type="button" class="ntf-tab" data-filter="unread" aria-pressed="false">{{ __('notifications.ui.unread') }} <span data-unread-count></span></button>
            </div>
            <div class="ntf-actions">
                <button type="button" class="ntf-link-btn" data-action="read-all">{{ __('notifications.ui.mark_all_read') }}</button>
                <button type="button" class="ntf-link-btn" data-action="clear">{{ __('notifications.ui.clear_read') }}</button>
            </div>
        </div>
        <div class="ntf-chips" role="group">
            <button type="button" class="ntf-chip" data-category="" aria-pressed="true">{{ __('notifications.ui.all') }}</button>
            @foreach($categories as $category)
                @continue($category === 'product')
                <button type="button" class="ntf-chip" data-category="{{ $category }}" aria-pressed="false">{{ __('notifications.categories.'.$category) }}</button>
            @endforeach
        </div>
        <ul class="ntf-list" data-list aria-live="polite"></ul>
        <div class="ntf-empty" data-empty hidden><i class="bx bx-bell-off"></i><span>{{ __('notifications.ui.empty') }}</span></div>
        <div class="ntf-more" data-more hidden><button type="button" class="btn btn-light btn-sm">{{ __('notifications.ui.load_more') }}</button></div>
    </section>
</div>
@endsection

@push('scripts')
@php
    $i18n = [
        'mark_read' => __('notifications.ui.mark_read'), 'dismiss' => __('notifications.ui.dismiss'), 'loading' => __('notifications.ui.loading'),
        'just_now' => __('notifications.ui.just_now'), 'm' => __('notifications.ui.minutes_ago'), 'h' => __('notifications.ui.hours_ago'),
        'd' => __('notifications.ui.days_ago'), 'attention' => __('notifications.ui.needs_attention'), 'critical' => __('notifications.tones.critical'),
    ];
@endphp
<script>
(() => {
    const root = document.getElementById('ntf-center');
    if (!root) return;
    const i18n = @json($i18n);
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const state = { filter: 'all', category: '', before: null };
    const list = root.querySelector('[data-list]');
    const send = (url, method) => fetch(url, { method, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const ago = (iso) => {
        const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (s < 60) return i18n.just_now;
        if (s < 3600) return i18n.m.replace(':count', Math.floor(s / 60));
        if (s < 86400) return i18n.h.replace(':count', Math.floor(s / 3600));
        return i18n.d.replace(':count', Math.floor(s / 86400));
    };
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };

    function row(item) {
        const li = el('li', 'ntf-item' + (item.read ? '' : ' is-unread'));
        const icon = el('span', 'ntf-icon ntf-tone-' + item.tone); icon.appendChild(el('i', 'bx ' + item.icon)); icon.setAttribute('aria-hidden', 'true');
        const body = el('div', 'ntf-body');
        const meta = el('div', 'ntf-meta');
        if (item.priority === 0) meta.appendChild(el('span', 'ntf-pill p0', i18n.critical));
        else if (item.priority === 1 && !item.read) meta.appendChild(el('span', 'ntf-pill p1', i18n.attention));
        meta.appendChild(el('span', null, item.category_label));
        if (item.workspace) { const w = el('bdi', null, item.workspace); meta.appendChild(w); }
        meta.appendChild(el('time', null, ago(item.created_at)));
        const title = el('a', 'ntf-title', item.title + (item.count > 1 ? ' (×' + item.count + ')' : '')); title.href = item.url;
        body.append(meta, title);
        if (item.preview) body.appendChild(el('p', 'ntf-preview', item.preview));
        const actions = el('div', 'ntf-item-actions');
        if (!item.read) {
            const r = el('button', 'ntf-icon-btn'); r.type = 'button'; r.title = i18n.mark_read; r.setAttribute('aria-label', i18n.mark_read); r.appendChild(el('i', 'bx bx-check'));
            r.onclick = () => send(item.read_url, 'PATCH').then(() => { li.classList.remove('is-unread'); r.remove(); refreshCount(); });
            actions.appendChild(r);
        }
        const x = el('button', 'ntf-icon-btn'); x.type = 'button'; x.title = i18n.dismiss; x.setAttribute('aria-label', i18n.dismiss); x.appendChild(el('i', 'bx bx-x'));
        x.onclick = () => send(item.dismiss_url, 'DELETE').then(() => { li.remove(); refreshCount(); if (!list.children.length) load(true); });
        actions.appendChild(x);
        li.append(icon, body, actions);
        return li;
    }

    function refreshCount(n) {
        if (n === undefined) return load(false, true);
        root.querySelector('[data-unread-count]').textContent = n ? '(' + n + ')' : '';
    }

    function load(reset = true, countOnly = false) {
        const params = new URLSearchParams({ filter: state.filter });
        if (state.category) params.set('category', state.category);
        if (!reset && !countOnly && state.before) params.set('before', state.before);
        return fetch(root.dataset.feed + '?' + params, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(data => {
                refreshCount(data.unread);
                if (countOnly) return;
                if (reset) list.innerHTML = '';
                data.items.forEach(item => list.appendChild(row(item)));
                state.before = data.items.length ? data.items[data.items.length - 1].id : state.before;
                root.querySelector('[data-empty]').hidden = list.children.length > 0;
                root.querySelector('[data-more]').hidden = !data.has_more;
            });
    }

    root.querySelectorAll('[data-filter]').forEach(b => b.onclick = () => {
        root.querySelectorAll('[data-filter]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
        state.filter = b.dataset.filter; state.before = null; load();
    });
    root.querySelectorAll('[data-category]').forEach(b => b.onclick = () => {
        root.querySelectorAll('[data-category]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
        state.category = b.dataset.category; state.before = null; load();
    });
    root.querySelector('[data-more] button').onclick = () => load(false);
    root.querySelector('[data-action="read-all"]').onclick = () => send(root.dataset.readAll, 'POST').then(() => load());
    root.querySelector('[data-action="clear"]').onclick = () => send(root.dataset.clear, 'DELETE').then(() => load());
    load();
})();
</script>
@endpush
