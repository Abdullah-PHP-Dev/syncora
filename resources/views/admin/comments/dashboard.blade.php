@extends('layouts.app')

@section('title', __('Comments'))

@php
    $platformMeta = [
        'facebook'  => ['Facebook', 'bxl-facebook', '#1877F2'],
        'instagram' => ['Instagram', 'bxl-instagram', '#E1306C'],
        'tiktok'    => ['TikTok', 'bxl-tiktok', '#111827'],
        'x'         => ['X', 'bxl-x-logo', '#111827'],
        'linkedin'  => ['LinkedIn', 'bxl-linkedin', '#0A66C2'],
        'youtube'   => ['YouTube', 'bxl-youtube', '#FF0000'],
        'google'    => ['Google', 'bxl-google', '#4285F4'],
        'threads'   => ['Threads', 'bx-at', '#000000'],
        'pinterest' => ['Pinterest', 'bxl-pinterest', '#E60023'],
    ];
    $meta = fn ($p) => $platformMeta[$p] ?? [ucfirst((string) $p), 'bx-globe', '#6d4aff'];
    $query = fn (array $changes) => route('admin.comments.dashboard', array_filter(array_merge(['platform' => $platform ?: null, 'filter' => $filter === 'unread' ? 'unread' : null, 'q' => $search ?: null, 'post' => $postId], $changes), fn ($v) => $v !== null && $v !== ''));
    $activeName = $platform ? $meta($platform)[0] : null;
@endphp

@push('styles')
<style>
    .cm { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; color: var(--ink2); }
    .cm-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
    .cm-head-title { display: flex; gap: 14px; align-items: flex-start; }
    .cm-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 18px rgba(109, 74, 255, .28); flex-shrink: 0; }
    .cm-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 2px; }
    .cm-head h4 { color: var(--ink); font-weight: 700; font-size: 1.35rem; margin: 0 0 4px; letter-spacing: -.01em; }
    .cm-head p { margin: 0; font-size: .87rem; }
    .cm-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .cm-stat { background: #fff; border: 1px solid var(--ln); border-radius: 12px; padding: 10px 16px; min-width: 110px; }
    .cm-stat small { display: block; font-size: .72rem; font-weight: 600; color: var(--muted); }
    .cm-stat strong { font-size: 1.25rem; color: var(--ink); }
    .cm-stat.is-unread strong { color: var(--brand); }

    .cm-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
    .cm-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--ln); }
    .cm-tabs { display: inline-flex; gap: 2px; padding: 3px; background: var(--ln-soft); border-radius: 11px; flex-wrap: wrap; }
    .cm-tab { height: 32px; padding: 0 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; font-size: .79rem; font-weight: 600; color: var(--ink2); text-decoration: none; }
    .cm-tab i { font-size: 1rem; }
    .cm-tab em { font-style: normal; font-weight: 500; color: var(--muted); }
    .cm-tab:hover { color: var(--ink); }
    .cm-tab.is-active { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
    .cm-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .cm-search { position: relative; margin: 0; }
    .cm-search i { position: absolute; inset-inline-start: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .cm-search input { height: 36px; width: 230px; border: 1px solid var(--ln); border-radius: 9px; padding-inline: 34px 10px; font-size: .83rem; outline: none; }
    .cm-search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
    .cm-toggle { height: 36px; padding: 0 12px; border-radius: 9px; border: 1px solid var(--ln); background: #fff; display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 600; color: var(--ink2); text-decoration: none; }
    .cm-toggle.is-on { background: var(--brand-soft); border-color: #d9d0ff; color: #4f2fd6; }
    .cm-filter-note { display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #fbfaff; border-bottom: 1px solid var(--ln); font-size: .8rem; }
    .cm-filter-note a { color: var(--brand); font-weight: 600; text-decoration: none; }

    .cm-row { display: flex; gap: 14px; padding: 16px; border-bottom: 1px solid var(--ln-soft); transition: background .15s; }
    .cm-row:last-child { border-bottom: none; }
    .cm-row:hover { background: #fafaff; }
    .cm-row.is-unread { background: #fcfbff; }
    .cm-avatar { position: relative; width: 40px; height: 40px; flex-shrink: 0; }
    .cm-avatar img, .cm-avatar .cm-initial { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .cm-initial { display: grid; place-items: center; background: #eef0f5; color: var(--ink2); font-weight: 700; font-size: .8rem; }
    .cm-avatar .cm-pf { position: absolute; right: -3px; bottom: -3px; width: 18px; height: 18px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 10px; border: 2px solid #fff; }
    [dir="rtl"] .cm-avatar .cm-pf { right: auto; left: -3px; }
    .cm-main { flex: 1; min-width: 0; }
    .cm-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .76rem; color: var(--muted); margin-bottom: 4px; }
    .cm-meta strong { color: var(--ink); font-size: .86rem; }
    .cm-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); }
    .cm-text { color: var(--ink); font-size: .88rem; line-height: 1.55; word-break: break-word; margin-bottom: 10px; }
    .cm-post { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--ln); border-radius: 10px; background: #fbfbfd; max-width: 560px; text-decoration: none; transition: border-color .15s; }
    .cm-post:hover { border-color: #d9d0ff; }
    .cm-post-thumb { width: 38px; height: 38px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--ln-soft); display: grid; place-items: center; color: var(--muted); }
    .cm-post-text { min-width: 0; font-size: .78rem; color: var(--ink2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cm-post-text small { display: block; font-size: .68rem; color: var(--muted); }
    .cm-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; flex-shrink: 0; }
    .cm-btn { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 11px; border-radius: 8px; font-size: .78rem; font-weight: 600; text-decoration: none; border: 1px solid var(--ln); background: #fff; color: var(--ink); white-space: nowrap; }
    .cm-btn:hover { background: #fafbfd; border-color: #cfd4de; color: var(--ink); }
    .cm-btn.is-primary { background: linear-gradient(135deg, var(--brand), var(--brand-2)); border-color: transparent; color: #fff; }
    .cm-btn.is-ghost { border-color: transparent; color: var(--muted); }

    .cm-empty { text-align: center; padding: 56px 20px; }
    .cm-empty-icon { width: 60px; height: 60px; margin: 0 auto 12px; border-radius: 16px; display: grid; place-items: center; font-size: 28px; color: var(--brand); background: var(--brand-soft); }
    .cm-empty h6 { color: var(--ink); font-weight: 700; margin-bottom: 4px; }
    .cm-empty p { font-size: .85rem; color: var(--muted); margin-bottom: 16px; }
    .cm-pages { padding: 12px 16px; border-top: 1px solid var(--ln); }
    .cm-pages .pagination { margin: 0; }

    @media (max-width: 767.98px) {
        .cm-row { flex-wrap: wrap; }
        .cm-actions { flex-direction: row; width: 100%; justify-content: flex-end; }
        .cm-search, .cm-search input { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="cm">

    <div class="cm-head">
        <div class="cm-head-title">
            <div class="cm-head-icon"><i class="bx bx-comment-detail"></i></div>
            <div>
                <div class="cm-eyebrow">{{ __('Engagement') }}</div>
                <h4>{{ __('Comments') }}</h4>
                <p>{{ __('Every comment on your published posts in one place - open a post to reply.') }}</p>
            </div>
        </div>
        <div class="cm-stats">
            <div class="cm-stat"><small>{{ __('All comments') }}</small><strong>{{ number_format($totals['all']) }}</strong></div>
            <div class="cm-stat is-unread"><small>{{ __('Unread') }}</small><strong>{{ number_format($totals['unread']) }}</strong></div>
            <div class="cm-stat"><small>{{ __('Today') }}</small><strong>{{ number_format($totals['today']) }}</strong></div>
        </div>
    </div>

    <div class="cm-card">
        <div class="cm-toolbar">
            <nav class="cm-tabs" aria-label="{{ __('Platform') }}">
                <a href="{{ $query(['platform' => null]) }}" class="cm-tab {{ $platform === '' ? 'is-active' : '' }}">{{ __('All') }} <em>{{ $totals['all'] }}</em></a>
                @foreach ($platformCounts as $key => $count)
                    @php [$name, $icon, $color] = $meta($key); @endphp
                    <a href="{{ $query(['platform' => $key]) }}" class="cm-tab {{ $platform === $key ? 'is-active' : '' }}"><i class="bx {{ $icon }}" style="color: {{ $color }}"></i> {{ $name }} <em>{{ $count }}</em></a>
                @endforeach
            </nav>
            <div class="cm-tools">
                <form method="GET" action="{{ route('admin.comments.dashboard') }}" class="cm-search" role="search">
                    <i class="bx bx-search"></i>
                    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search comments or names…') }}" aria-label="{{ __('Search comments') }}">
                    @foreach (array_filter(['platform' => $platform, 'filter' => $filter === 'unread' ? 'unread' : null, 'post' => $postId]) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                </form>
                <a href="{{ $query(['filter' => $filter === 'unread' ? null : 'unread']) }}" class="cm-toggle {{ $filter === 'unread' ? 'is-on' : '' }}"><i class="bx bx-envelope"></i> {{ __('Unread only') }}</a>
            </div>
        </div>

        @if ($postId)
            <div class="cm-filter-note"><i class="bx bx-filter-alt"></i> {{ __('Showing comments on one post.') }} <a href="{{ $query(['post' => null]) }}">{{ __('Show all posts') }}</a></div>
        @endif

        @forelse ($comments as $comment)
            @php
                [$pName, $pIcon, $pColor] = $meta($comment->platform);
                $post = $comment->post;
                $thumb = dash_media_preview($post?->media->first());
                $postUrl = $post ? route('admin.posts.preview', ['post' => $post->id, 'platform' => $comment->platform]) . '#comments' : null;
                $when = $comment->posted_at ?? $comment->created_at;
            @endphp
            <div class="cm-row {{ $comment->read_at ? '' : 'is-unread' }}" data-comment-id="{{ $comment->id }}">
                <div class="cm-avatar">
                    @if ($comment->user_avatar_url)
                        <img src="{{ $comment->user_avatar_url }}" alt="" onerror="this.outerHTML='<span class=\'cm-initial\'>{{ mb_strtoupper(mb_substr($comment->user_name ?: '?', 0, 1)) }}</span>'">
                    @else
                        <span class="cm-initial">{{ mb_strtoupper(mb_substr($comment->user_name ?: '?', 0, 1)) }}</span>
                    @endif
                    <span class="cm-pf" style="background: {{ $pColor }}"><i class="bx {{ $pIcon }}"></i></span>
                </div>
                <div class="cm-main">
                    <div class="cm-meta">
                        @unless ($comment->read_at)<span class="cm-dot" title="{{ __('Unread') }}"></span>@endunless
                        <strong>{{ $comment->user_name ?: __('Unknown') }}</strong>
                        <span>{{ $pName }}@if($comment->socialAccount) · {{ $comment->socialAccount->name }}@endif</span>
                        <span title="{{ $when?->translatedFormat('M j, Y g:i A') }}">{{ $when?->diffForHumans() }}</span>
                        @if ($comment->replies_count)<span><i class="bx bx-reply"></i> {{ trans_choice(':count reply|:count replies', $comment->replies_count, ['count' => $comment->replies_count]) }}</span>@endif
                    </div>
                    <div class="cm-text">{{ $comment->content }}</div>
                    @if ($post)
                        <a href="{{ $postUrl }}" class="cm-post" title="{{ __('View post') }}">
                            @if ($thumb && $thumb['kind'] === 'image' && $thumb['url'])
                                <img src="{{ $thumb['url'] }}" class="cm-post-thumb" alt="">
                            @else
                                <span class="cm-post-thumb"><i class="bx {{ $thumb && $thumb['kind'] === 'video' ? 'bx-play' : 'bx-file-blank' }}"></i></span>
                            @endif
                            <span class="cm-post-text"><small>{{ __('On your post') }}</small>{{ \Illuminate\Support\Str::limit($post->content ?: __('(no caption)'), 90) }}</span>
                        </a>
                    @endif
                </div>
                <div class="cm-actions">
                    @if ($postUrl)
                        <a href="{{ $postUrl }}" class="cm-btn is-primary" data-cm-open><i class="bx bx-reply"></i> {{ __('Reply') }}</a>
                    @endif
                    @unless ($comment->read_at)
                        <button type="button" class="cm-btn is-ghost" data-cm-read="{{ route('admin.comments.read', $comment) }}"><i class="bx bx-check"></i> {{ __('Mark read') }}</button>
                    @endunless
                </div>
            </div>
        @empty
            <div class="cm-empty">
                <div class="cm-empty-icon"><i class="bx {{ $search || $filter === 'unread' ? 'bx-search-alt' : 'bx-comment-detail' }}"></i></div>
                @if ($search || $filter === 'unread' || $postId)
                    <h6>{{ __('No comments match these filters') }}</h6>
                    <p>{{ __('Try another search, or show all comments.') }}</p>
                    <a href="{{ route('admin.comments.dashboard', array_filter(['platform' => $platform ?: null])) }}" class="cm-btn"><i class="bx bx-reset"></i> {{ __('Clear filters') }}</a>
                @elseif ($activeName)
                    <h6>{{ __('No :platform comments yet', ['platform' => $activeName]) }}</h6>
                    <p>{{ __('Comments on your :platform posts appear here as they come in.', ['platform' => $activeName]) }}</p>
                    <a href="{{ route('admin.posts.index', ['platform' => $platform]) }}" class="cm-btn is-primary"><i class="bx bx-edit-alt"></i> {{ __('Go to :platform posts', ['platform' => $activeName]) }}</a>
                @else
                    <h6>{{ __('No comments yet') }}</h6>
                    <p>{{ __('When people comment on your published posts, you can read and reply to them here.') }}</p>
                    <a href="{{ route('admin.posts.index') }}" class="cm-btn is-primary"><i class="bx bx-edit-alt"></i> {{ __('Go to your posts') }}</a>
                @endif
            </div>
        @endforelse

        @if ($comments->hasPages())
            <div class="cm-pages">{{ $comments->onEachSide(1)->links() }}</div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    // Delegated: the Vue #app root re-mounts after load.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-cm-read]');
        const open = e.target.closest('[data-cm-open]');
        const row = (btn || open)?.closest('.cm-row');
        if (!row) return;

        const url = btn ? btn.dataset.cmRead : row.querySelector('[data-cm-read]')?.dataset.cmRead;
        if (!url) return;

        fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, keepalive: true })
            .then(() => {
                row.classList.remove('is-unread');
                row.querySelector('.cm-dot')?.remove();
                row.querySelector('[data-cm-read]')?.remove();
            })
            .catch(() => {});
    });
</script>
@endpush
