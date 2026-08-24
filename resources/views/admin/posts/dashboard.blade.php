@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $platformMeta = [
        'facebook'  => ['icon' => 'bxl-facebook',  'class' => 'facebook',  'label' => 'Facebook',  'tag' => 'Page'],
        'instagram' => ['icon' => 'bxl-instagram', 'class' => 'instagram', 'label' => 'Instagram', 'tag' => 'Business'],
        'tiktok'    => ['icon' => 'bxl-tiktok',    'class' => 'tiktok',    'label' => 'TikTok',     'tag' => 'Business'],
        'x'         => ['icon' => 'bxl-twitter',   'class' => 'twitter',   'label' => 'X',          'tag' => 'Profile'],
        'twitter'   => ['icon' => 'bxl-twitter',   'class' => 'twitter',   'label' => 'X',          'tag' => 'Profile'],
        'linkedin'  => ['icon' => 'bxl-linkedin',  'class' => 'linkedin',  'label' => 'LinkedIn',   'tag' => 'Page'],
        'youtube'   => ['icon' => 'bxl-youtube',   'class' => 'youtube',   'label' => 'YouTube',    'tag' => 'Channel'],
        'google'    => ['icon' => 'bxl-google',    'class' => 'google',    'label' => 'Google',     'tag' => 'Business'],
        'pinterest' => ['icon' => 'bx-share-alt',  'class' => 'pinterest', 'label' => 'Pinterest',  'tag' => 'Profile'],
        'whatsapp'  => ['icon' => 'bxl-whatsapp',  'class' => 'whatsapp',  'label' => 'WhatsApp',   'tag' => 'Business'],
        'threads'   => ['icon' => 'bx-at',         'class' => 'threads',  'label' => 'Threads',    'tag' => 'Profile'],
    ];

    // Short display form for large counters (12400 -> "12.4K"), matching the
    // target design's stat-card style without pretending precision we don't have.
    if (! function_exists('dash_short')) {
        function dash_short($n) {
            $n = (float) $n;
            if ($n >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
            if ($n >= 1000)    return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
            return number_format($n);
        }
    }

    $statusMeta = [
        'published' => ['label' => 'Published', 'class' => 'success'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'info'],
        'pending'   => ['label' => 'Pending',   'class' => 'warning'],
        'PROCESSING'=> ['label' => 'Pending',   'class' => 'warning'],
        'failed'    => ['label' => 'Failed',    'class' => 'danger'],
        'draft'     => ['label' => 'Draft',     'class' => 'muted'],
    ];

    // PostMedia::media_type is one of 'image' | 'gif' | 'video' | 'file' (the
    // catch-all the upload services use for everything else - xlsx, pdf, docx,
    // zip, ...). Videos never get a thumbnail_url generated (upload services
    // leave it null - see MetaPostService::uploadMediaToS3()), and "file"
    // uploads obviously aren't renderable as <img> either, so every spot that
    // shows a post's media needs to branch on this rather than assuming
    // media_url is always an image.
    if (! function_exists('dash_media_preview')) {
        function dash_media_preview($media) {
            if (! $media) return null;
            $ext = strtoupper(pathinfo($media->media_url ?? '', PATHINFO_EXTENSION));
            return match ($media->media_type ?? 'file') {
                'image', 'gif' => ['kind' => 'image', 'url' => $media->media_url],
                'video' => ['kind' => 'video', 'url' => $media->thumbnail_url, 'ext' => $ext],
                default => ['kind' => 'file', 'ext' => $ext ?: 'FILE'],
            };
        }
    }

    $isCurrentMonth = $calendarMonth->isSameMonth(now());
    $prevCalMonth = $calendarMonth->copy()->subMonthNoOverflow()->format('Y-m');
    $nextCalMonth = $calendarMonth->copy()->addMonthNoOverflow()->format('Y-m');
@endphp

<div class="socialeaz-dash">

    <!-- Header -->
    <div class="dash-header d-flex flex-wrap align-items-start justify-content-between gap-4 mb-6">
        <div>
            <h4 class="dash-title mb-1">Welcome back, {{ explode(' ', trim(auth()->user()->name ?? 'there'))[0] }}! <span>👋</span></h4>
            <p class="dash-subtitle mb-0">Here's what's happening with your social media presence today.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('admin.posts.dashboard') }}" class="d-flex align-items-center gap-2">
                <input type="text" id="dashboardDateRange" name="date_range" class="dash-input" style="max-width:210px;" placeholder="Select date range" autocomplete="off" value="{{ $dateFrom && $dateTo ? $dateFrom->format('M j').' - '.$dateTo->format('M j, Y') : '' }}" />
                <input type="hidden" name="from" id="dashboardFromInput" value="{{ $dateFrom?->format('Y-m-d') }}" />
                <input type="hidden" name="to" id="dashboardToInput" value="{{ $dateTo?->format('Y-m-d') }}" />
                <button type="submit" class="dash-btn dash-btn-ghost"><i class="bx bx-calendar"></i></button>
                @if($dateFrom && $dateTo)
                <a href="{{ route('admin.posts.dashboard') }}" class="dash-btn dash-btn-ghost"><i class="bx bx-x"></i></a>
                @endif
            </form>
            <a href="{{ route('admin.chats.dashboard') }}" class="dash-btn dash-btn-ghost dash-bell" title="Unread messages">
                <i class="bx bx-bell"></i>
                @if($totalUnreadMessages > 0)
                <span class="dash-bell-badge">{{ $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages }}</span>
                @endif
            </a>
            <a href="{{ route('admin.posts.create') }}" class="dash-btn dash-btn-primary">
                <i class="bx bx-plus"></i> Create Post
            </a>
        </div>
    </div>

    <!-- Overview -->
    <div class="row g-4 mb-6">
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Connected Accounts</div>
                <div class="d-flex align-items-end justify-content-between">
                    <div class="dash-stat-value">{{ $totalAccounts }}</div>
                    <div class="dash-mini-icons">
                        @foreach($accountsByPlatform->keys()->take(4) as $p)
                            @php $m = $platformMeta[$p] ?? null; @endphp
                            @if($m)
                            <span class="social-icon-mini {{ $m['class'] }}"><i class="bx {{ $m['icon'] }}"></i></span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="dash-stat-foot">
                    Across {{ $accountsByPlatform->count() }} platform{{ $accountsByPlatform->count() == 1 ? '' : 's' }}
                    @if($newAccountsThisWeek > 0)
                    <span class="dash-trend dash-trend-up">+{{ $newAccountsThisWeek }} this week</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Total Followers</div>
                <div class="dash-stat-value">{{ dash_short($totalFollowers) }}</div>
                <div class="dash-stat-foot">Across all platforms</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Engagement Rate</div>
                <div class="dash-stat-value">{{ $engagementRate === null ? '—' : $engagementRate.'%' }}</div>
                <div class="dash-stat-foot">
                    @if($engagementChangePercent === null)
                        {{ $engagementRate === null ? 'Not enough reach data yet' : '(likes + comments + shares) / reach' }}
                    @else
                        <span class="dash-trend {{ $engagementChangePercent >= 0 ? 'dash-trend-up' : 'dash-trend-down' }}">
                            <i class="bx {{ $engagementChangePercent >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>
                            {{ abs($engagementChangePercent) }}%
                        </span> vs last 7 days
                    @endif
                </div>
                <div id="engagementSparkline" class="dash-sparkline"></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Total Reach</div>
                <div class="dash-stat-value">{{ dash_short($totalReach) }}</div>
                <div class="dash-stat-foot">
                    @if($reachChangePercent === null)
                        vs previous period
                    @else
                        <span class="dash-trend {{ $reachChangePercent >= 0 ? 'dash-trend-up' : 'dash-trend-down' }}">
                            <i class="bx {{ $reachChangePercent >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>
                            {{ abs($reachChangePercent) }}%
                        </span> vs last 7 days
                    @endif
                </div>
                <div id="reachSparkline" class="dash-sparkline"></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-6">
        <!-- Post Performance -->
        <div class="col-lg-8">
            <div class="dash-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">Performance Overview</h6>
                    <span class="dash-chip">Last 7 Days</span>
                </div>
                <div id="performanceChart"></div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="col-lg-4">
            <div class="dash-card h-100">
                <h6 class="mb-3">Performance Summary</h6>
                <ul class="dash-summary-list">
                    <li>
                        <span class="dash-summary-icon primary"><i class="bx bx-file"></i></span>
                        <span class="dash-summary-label">Total Posts</span>
                        <span class="dash-summary-value">{{ $totalPosts }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon info"><i class="bx bx-show"></i></span>
                        <span class="dash-summary-label">Total Reach</span>
                        <span class="dash-summary-value">{{ dash_short($totalReach) }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon warning"><i class="bx bx-bulb"></i></span>
                        <span class="dash-summary-label">Total Engagements</span>
                        <span class="dash-summary-value">{{ dash_short($totalLikes + $totalComments + $totalShares) }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon success"><i class="bx bx-mouse"></i></span>
                        <span class="dash-summary-label">Total Clicks</span>
                        <span class="dash-summary-value">{{ dash_short(array_sum($dailyClicks)) }}</span>
                    </li>
                </ul>
                <a href="{{ route('admin.posts.index') }}" class="dash-link d-inline-flex align-items-center gap-1">
                    View detailed report <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Connected Accounts row -->
    <div class="dash-card mb-6">
        <div class="dash-card-header">
            <h6 class="mb-0">Connected Accounts</h6>
            <a href="{{ route('admin.posts.create') }}" class="dash-link">Manage Accounts</a>
        </div>
        <div class="row g-3">
            @forelse($accountsOverview as $acct)
            @php $meta = $platformMeta[$acct['platform']] ?? ['icon' => 'bx-globe', 'class' => 'facebook', 'label' => ucfirst($acct['platform'] ?? 'Other'), 'tag' => 'Account']; @endphp
            <div class="col-6 col-md-4 col-xl-2">
                <div class="dash-account-card">
                    <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                    <div class="dash-account-name">{{ $meta['label'] }}</div>
                    <div class="dash-account-tag">{{ $meta['tag'] }}</div>
                    <div class="dash-account-count">{{ dash_short($acct['follower_count']) }} {{ Str::contains($meta['label'], 'YouTube') ? 'Subscribers' : 'Followers' }}</div>
                    <div class="dash-status-pill"><span class="dot"></span> Connected</div>
                </div>
            </div>
            @empty
            <div class="col-12 dash-empty-row">No accounts connected yet.</div>
            @endforelse
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('admin.posts.create') }}" class="dash-add-account-card">
                    <i class="bx bx-plus-circle"></i>
                    <span>Add Account</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Posts table -->
        <div class="col-lg-8">
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Recent Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All Posts</a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Post</th>
                                <th>Platform</th>
                                <th>Reach</th>
                                <th>Engagement</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPosts as $post)
                            @php
                                $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook', 'label' => ucfirst($post->platform)];
                                $preview = dash_media_preview($post->media->first());
                                $sm = $statusMeta[$post->status] ?? ['label' => ucfirst($post->status), 'class' => 'muted'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="dash-list-thumb {{ ($preview['kind'] ?? null) === 'video' ? 'dash-list-thumb-video' : '' }}">
                                            @if($preview && $preview['kind'] === 'image')
                                            <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
                                            @elseif($preview && $preview['kind'] === 'video')
                                                @if($preview['url'])
                                                <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
                                                @endif
                                                <span class="dash-media-video-badge"><i class="bx bx-play-circle"></i></span>
                                            @elseif($preview && $preview['kind'] === 'file')
                                            <span class="dash-media-file-badge">{{ $preview['ext'] }}</span>
                                            @else
                                            <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                                            @endif
                                        </div>
                                        <span class="dash-table-title">{{ Str::limit($post->content ?: '(no caption)', 42) }}</span>
                                    </div>
                                </td>
                                <td><span class="social-icon-mini social-icon-xs {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span></td>
                                <td>{{ dash_short($post->reach) }}</td>
                                <td>{{ dash_short($post->likes + $post->comments + $post->shares) }}</td>
                                <td>{{ $post->created_at->format('M j, Y') }}</td>
                                <td><span class="dash-badge dash-badge-{{ $sm['class'] }}">{{ $sm['label'] }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="dash-empty-row">No posts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Performing Posts -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6 class="mb-0">Top Performing Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All</a>
                </div>
                <div class="row g-3">
                    @forelse($topPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <div class="col-md-6 col-xl-3">
                        <div class="dash-top-post">
                            @if($loop->first && $post->reach > 0)
                            <span class="dash-badge-best">Best Reach</span>
                            @endif
                            @php $preview = dash_media_preview($post->media->first()); @endphp
                            <div class="dash-list-thumb dash-list-thumb-lg {{ ($preview['kind'] ?? null) === 'video' ? 'dash-list-thumb-video' : '' }}">
                                @if($preview && $preview['kind'] === 'image')
                                <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
                                @elseif($preview && $preview['kind'] === 'video')
                                    @if($preview['url'])
                                    <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
                                    @endif
                                    <span class="dash-media-video-badge"><i class="bx bx-play-circle"></i></span>
                                @elseif($preview && $preview['kind'] === 'file')
                                <span class="dash-media-file-badge">{{ $preview['ext'] }}</span>
                                @else
                                <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                                @endif
                            </div>
                            <p class="mb-0 mt-2">{{ Str::limit($post->content ?: '(no caption)', 40) }}</p>
                            <small>{{ dash_short($post->reach) }} reach · {{ dash_short($post->likes) }} likes</small>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 dash-empty-row">No posts yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-4">
            <!-- Content Calendar -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Content Calendar</h6>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('admin.posts.dashboard', array_merge(request()->except('cal'), ['cal' => $prevCalMonth])) }}" class="dash-cal-nav"><i class="bx bx-chevron-left"></i></a>
                        <span class="dash-chip">{{ $calendarMonth->format('F Y') }}</span>
                        <a href="{{ route('admin.posts.dashboard', array_merge(request()->except('cal'), ['cal' => $nextCalMonth])) }}" class="dash-cal-nav"><i class="bx bx-chevron-right"></i></a>
                    </div>
                </div>
                <div class="dash-calendar">
                    @foreach(['S','M','T','W','T','F','S'] as $d)
                    <div class="dash-calendar-head">{{ $d }}</div>
                    @endforeach
                    @php $firstDow = $calendarMonth->copy()->startOfMonth()->dayOfWeek; @endphp
                    @for($i=0;$i<$firstDow;$i++)
                        <div></div>
                    @endfor
                    @for($day=1;$day<=$calendarMonth->daysInMonth;$day++)
                        @php
                            $dayPreview = $dayPosts = $calendarMonthPosts[$day] ?? null;
                            $dayPreview = dash_media_preview($dayPosts?->first()?->media->first());
                            $isToday = $isCurrentMonth && $day == now()->day;
                        @endphp
                        <div class="dash-calendar-day {{ $isToday ? 'is-today' : '' }} {{ $dayPosts ? 'has-post' : '' }} {{ $dayPreview ? 'dash-calendar-day-'.$dayPreview['kind'] : '' }}" title="{{ $dayPosts ? $dayPosts->count().' post(s)' : '' }}">
                            @if($dayPreview && $dayPreview['kind'] === 'image')
                            <img src="{{ $dayPreview['url'] }}" alt="" onerror="this.remove()">
                            @elseif($dayPreview && $dayPreview['kind'] === 'video')
                                @if($dayPreview['url'])
                                <img src="{{ $dayPreview['url'] }}" alt="" onerror="this.remove()">
                                @endif
                                <i class="bx bx-play-circle dash-calendar-media-icon"></i>
                            @elseif($dayPreview && $dayPreview['kind'] === 'file')
                            <i class="bx bxs-file-blank dash-calendar-media-icon"></i>
                            @endif
                            <span>{{ $day }}</span>
                        </div>
                    @endfor
                </div>
                <div class="dash-calendar-legend">
                    <span><i class="dot dot-primary"></i> {{ $calendarPostsThisMonth }} Posts</span>
                    <span><i class="dot dot-success"></i> {{ $calendarCommentsThisMonth }} Comments</span>
                    <span><i class="dot dot-info"></i> {{ $calendarMessagesThisMonth }} Messages</span>
                </div>
            </div>

            <!-- Upcoming Posts -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Upcoming Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All</a>
                </div>
                <ul class="dash-list">
                    @forelse($upcomingPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <li>
                        <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                        <div class="dash-list-body">
                            <p class="mb-0">{{ Str::limit($post->content ?: '(no caption)', 34) }}</p>
                            <small>{{ $meta['label'] ?? ucfirst($post->platform) }}</small>
                        </div>
                        <div class="dash-list-when">
                            <small>{{ $post->schedule_at?->format('M j, Y') }}</small>
                            <small>{{ $post->schedule_at?->format('g:i A') }}</small>
                        </div>
                    </li>
                    @empty
                    <li class="dash-empty-row">Nothing scheduled yet.</li>
                    @endforelse
                </ul>
            </div>

            <!-- Inbox Overview -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Inbox Overview</h6>
                    <a href="{{ route('admin.chats.dashboard') }}" class="dash-link">View All</a>
                </div>
                <div class="dash-inbox-grid">
                    <a href="{{ route('admin.chats.dashboard') }}" class="dash-inbox-tile">
                        <span class="dash-inbox-icon primary"><i class="bx bx-envelope"></i></span>
                        <div class="dash-inbox-value">{{ $totalMessages }}</div>
                        <div class="dash-inbox-label">Messages</div>
                    </a>
                    <a href="{{ route('admin.comments.dashboard') }}" class="dash-inbox-tile">
                        <span class="dash-inbox-icon danger"><i class="bx bx-comment-detail"></i></span>
                        <div class="dash-inbox-value">{{ $totalCommentsAll }}</div>
                        <div class="dash-inbox-label">Comments</div>
                    </a>
                    <div class="dash-inbox-tile" title="Mention tracking isn't wired up yet">
                        <span class="dash-inbox-icon success"><i class="bx bx-at"></i></span>
                        <div class="dash-inbox-value">0</div>
                        <div class="dash-inbox-label">Mentions</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="dash-quick-actions">
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-edit-alt"></i> Create Post</a>
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-calendar-plus"></i> Schedule Post</a>
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-user-plus"></i> Add Account</a>
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-images"></i> Media Library</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.socialeaz-dash {
    --dash-bg: #f5f5fa;
    --dash-card: #ffffff;
    --dash-card-hover: #f7f7fc;
    --dash-border: rgba(20,20,40,.08);
    --dash-text: #4b4d5c;
    --dash-heading: #1e1e2d;
    --dash-muted: #8b8d9c;
    --dash-primary: #7c5cff;
    --dash-primary-2: #a855f7;
    --dash-success: #16a34a;
    --dash-danger: #e11d48;
    --dash-warning: #d97706;
    --dash-info: #0891b2;

    background: var(--dash-bg);
    color: var(--dash-text);
    border-radius: 1rem;
    padding: 1.5rem;
    margin: -1.5rem;
    min-height: calc(100vh - 8rem);
}
.socialeaz-dash .dash-title { color: var(--dash-heading); font-weight: 700; }
.socialeaz-dash .dash-subtitle { color: var(--dash-muted); }
.socialeaz-dash .dash-input {
    background: var(--dash-card); border: 1px solid var(--dash-border); color: var(--dash-text);
    border-radius: .5rem; padding: .4rem .75rem; font-size: .8125rem;
}
.socialeaz-dash .dash-input::placeholder { color: var(--dash-muted); }
.socialeaz-dash .dash-btn {
    display: inline-flex; align-items: center; gap: .375rem;
    border-radius: .5rem; padding: .5rem .9rem; font-size: .8125rem; font-weight: 600;
    border: 1px solid var(--dash-border); text-decoration: none; position: relative;
}
.socialeaz-dash .dash-btn-ghost { background: var(--dash-card); color: var(--dash-text); }
.socialeaz-dash .dash-btn-ghost:hover { background: var(--dash-card-hover); color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-btn-primary { background: linear-gradient(135deg, var(--dash-primary), var(--dash-primary-2)); color: #fff; box-shadow: 0 4px 12px rgba(124,92,255,.28); }
.socialeaz-dash .dash-btn-primary:hover { opacity: .92; color: #fff; }
.socialeaz-dash .dash-bell-badge {
    position: absolute; top: -5px; right: -5px; background: var(--dash-danger); color: #fff;
    font-size: .6rem; font-weight: 700; min-width: 16px; height: 16px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; padding: 0 3px;
}

.socialeaz-dash .dash-card {
    background: var(--dash-card); border: 1px solid var(--dash-border);
    border-radius: .85rem; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(20,20,50,.04);
}
.socialeaz-dash .dash-card-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: .5rem;
}
.socialeaz-dash .dash-card-header h6 { color: var(--dash-heading); font-weight: 600; }
.socialeaz-dash .dash-link { color: var(--dash-primary); font-size: .8125rem; text-decoration: none; font-weight: 500; white-space: nowrap; }
.socialeaz-dash .dash-link:hover { text-decoration: underline; }

.socialeaz-dash .dash-stat-label { color: var(--dash-muted); font-size: .8125rem; margin-bottom: .5rem; }
.socialeaz-dash .dash-stat-value { color: var(--dash-heading); font-size: 1.6rem; font-weight: 700; line-height: 1; }
.socialeaz-dash .dash-stat-foot { color: var(--dash-muted); font-size: .75rem; margin-top: .6rem; }
.socialeaz-dash .dash-mini-icons { display: flex; gap: .25rem; }
.socialeaz-dash .dash-mini-icons .social-icon-mini { width: 22px; height: 22px; font-size: 11px; border-radius: 6px; }
.socialeaz-dash .dash-trend { display: inline-flex; align-items: center; gap: .1rem; font-weight: 700; }
.socialeaz-dash .dash-trend-up { color: var(--dash-success); }
.socialeaz-dash .dash-trend-down { color: var(--dash-danger); }
.socialeaz-dash .dash-sparkline { margin-top: .5rem; height: 32px; }

/* Brand colors - not defined anywhere globally, so the icon chips render flat
   without these; kept scoped to this page rather than touching admin.css. */
.socialeaz-dash .social-icon-mini.facebook  { background: #1877F2; }
.socialeaz-dash .social-icon-mini.instagram { background: linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); }
.socialeaz-dash .social-icon-mini.tiktok    { background: #000000; }
.socialeaz-dash .social-icon-mini.twitter   { background: #1DA1F2; }
.socialeaz-dash .social-icon-mini.linkedin  { background: #0A66C2; }
.socialeaz-dash .social-icon-mini.youtube   { background: #FF0000; }
.socialeaz-dash .social-icon-mini.google    { background: #4285F4; }
.socialeaz-dash .social-icon-mini.pinterest { background: #E60023; }
.socialeaz-dash .social-icon-mini.whatsapp  { background: #25D366; }
.socialeaz-dash .social-icon-mini.threads   { background: #000000; }
.socialeaz-dash .social-icon-xs { width: 26px !important; height: 26px !important; font-size: 12px !important; border-radius: 7px !important; }

.socialeaz-dash .dash-account-card {
    background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: 1rem;
    height: 100%; text-align: center;
}
.socialeaz-dash .dash-account-card .social-icon-mini { margin: 0 auto .6rem; }
.socialeaz-dash .dash-account-name { color: var(--dash-heading); font-weight: 600; font-size: .85rem; }
.socialeaz-dash .dash-account-tag { color: var(--dash-muted); font-size: .7rem; margin-bottom: .5rem; }
.socialeaz-dash .dash-account-count { color: var(--dash-heading); font-size: .8rem; font-weight: 600; margin-bottom: .5rem; }
.socialeaz-dash .dash-status-pill { display: inline-flex; align-items: center; gap: .35rem; color: var(--dash-success); font-size: .7rem; font-weight: 600; }
.socialeaz-dash .dash-status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--dash-success); display: inline-block; }
.socialeaz-dash .dash-add-account-card {
    height: 100%; min-height: 140px; display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 1.5px dashed var(--dash-border); border-radius: .7rem; color: var(--dash-muted); text-decoration: none; gap: .4rem;
}
.socialeaz-dash .dash-add-account-card:hover { color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-add-account-card i { font-size: 1.5rem; }

.socialeaz-dash .dash-chip { background: var(--dash-card-hover); color: var(--dash-muted); font-size: .7rem; padding: .25rem .6rem; border-radius: 1rem; }
.socialeaz-dash .dash-cal-nav { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; color: var(--dash-muted); text-decoration: none; }
.socialeaz-dash .dash-cal-nav:hover { background: var(--dash-card-hover); color: var(--dash-primary); }

.socialeaz-dash .dash-summary-list { list-style: none; margin: 0 0 1rem; padding: 0; }
.socialeaz-dash .dash-summary-list li { display: flex; align-items: center; gap: .6rem; padding: .55rem 0; border-bottom: 1px solid var(--dash-border); }
.socialeaz-dash .dash-summary-list li:last-child { border-bottom: none; }
.socialeaz-dash .dash-summary-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #fff; flex-shrink: 0; }
.socialeaz-dash .dash-summary-icon.primary { background: var(--dash-primary); }
.socialeaz-dash .dash-summary-icon.info { background: var(--dash-info); }
.socialeaz-dash .dash-summary-icon.warning { background: var(--dash-warning); }
.socialeaz-dash .dash-summary-icon.success { background: var(--dash-success); }
.socialeaz-dash .dash-summary-label { color: var(--dash-text); font-size: .8125rem; flex: 1; }
.socialeaz-dash .dash-summary-value { color: var(--dash-heading); font-weight: 700; font-size: .875rem; }

.socialeaz-dash .dash-list { list-style: none; margin: 0; padding: 0; }
.socialeaz-dash .dash-list li { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--dash-border); position: relative; }
.socialeaz-dash .dash-list li:last-child { border-bottom: none; }
.socialeaz-dash .dash-list-thumb { width: 40px; height: 40px; border-radius: .5rem; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--dash-card-hover); position: relative; }
.socialeaz-dash .dash-list-thumb img { width: 100%; height: 100%; object-fit: cover; }
.socialeaz-dash .dash-list-thumb-lg { width: 100%; height: 110px; border-radius: .6rem; }
.socialeaz-dash .dash-media-video-badge {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(20,20,40,.35); color: #fff; font-size: 1.15rem;
}
.socialeaz-dash .dash-list-thumb-video { background: #1e1e2d; }
.socialeaz-dash .dash-media-video-badge i { font-size: 1.5rem; }
.socialeaz-dash .dash-media-file-badge {
    display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 100%;
    background: var(--dash-primary); color: #fff; font-size: .62rem; font-weight: 700; letter-spacing: .02em;
}
.socialeaz-dash .dash-list-body { flex: 1; min-width: 0; }
.socialeaz-dash .dash-list-body p { color: var(--dash-text); font-size: .8125rem; margin: 0; }
.socialeaz-dash .dash-list-body small { color: var(--dash-muted); font-size: .7rem; }
.socialeaz-dash .dash-list-when { text-align: right; display: flex; flex-direction: column; gap: 2px; }
.socialeaz-dash .dash-list-when small { color: var(--dash-muted); font-size: .68rem; white-space: nowrap; }
.socialeaz-dash .dash-empty-row { color: var(--dash-muted); text-align: center; padding: 1.5rem 0 !important; border-bottom: none !important; display: block; }

.socialeaz-dash .dash-top-post { background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: .75rem; height: 100%; position: relative; }
.socialeaz-dash .dash-top-post p { font-size: .78rem; color: var(--dash-text); }
.socialeaz-dash .dash-top-post small { color: var(--dash-muted); font-size: .7rem; }
.socialeaz-dash .dash-badge-best { position: absolute; right: .6rem; top: .6rem; z-index: 1; background: rgba(22,163,74,.9); color: #fff; padding: .15rem .5rem; border-radius: .3rem; font-size: .62rem; font-weight: 600; }

.socialeaz-dash .dash-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.socialeaz-dash .dash-table th { text-align: left; color: var(--dash-muted); font-weight: 600; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; padding: 0 .5rem .6rem; border-bottom: 1px solid var(--dash-border); }
.socialeaz-dash .dash-table td { padding: .6rem .5rem; border-bottom: 1px solid var(--dash-border); vertical-align: middle; color: var(--dash-text); }
.socialeaz-dash .dash-table tr:last-child td { border-bottom: none; }
.socialeaz-dash .dash-table-title { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.socialeaz-dash .dash-badge { display: inline-block; padding: .2rem .55rem; border-radius: .4rem; font-size: .68rem; font-weight: 600; }
.socialeaz-dash .dash-badge-success { background: rgba(22,163,74,.1); color: var(--dash-success); }
.socialeaz-dash .dash-badge-info { background: rgba(8,145,178,.1); color: var(--dash-info); }
.socialeaz-dash .dash-badge-warning { background: rgba(217,119,6,.1); color: var(--dash-warning); }
.socialeaz-dash .dash-badge-danger { background: rgba(225,29,72,.1); color: var(--dash-danger); }
.socialeaz-dash .dash-badge-muted { background: rgba(139,141,156,.12); color: var(--dash-muted); }

.socialeaz-dash .dash-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
.socialeaz-dash .dash-calendar-head { color: var(--dash-muted); font-size: .65rem; font-weight: 600; padding-bottom: .4rem; }
.socialeaz-dash .dash-calendar-day {
    font-size: .68rem; color: var(--dash-text); border-radius: .4rem; position: relative;
    aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--dash-card-hover);
}
.socialeaz-dash .dash-calendar-day img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .55; }
.socialeaz-dash .dash-calendar-day span { position: relative; z-index: 1; }
.socialeaz-dash .dash-calendar-day.has-post span { color: #fff; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,.5); }
.socialeaz-dash .dash-calendar-day.has-post:not(.dash-calendar-day-image):not(.dash-calendar-day-video):not(.dash-calendar-day-file)::after { content: ''; position: absolute; bottom: 3px; left: 50%; transform: translateX(-50%); width: 4px; height: 4px; border-radius: 50%; background: var(--dash-primary); }
.socialeaz-dash .dash-calendar-media-icon { position: absolute; top: 2px; right: 2px; z-index: 1; font-size: .7rem; color: var(--dash-primary); }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) { background: #1e1e2d; }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) span { color: #fff; }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) .dash-calendar-media-icon { color: #fff; }
.socialeaz-dash .dash-calendar-day.is-today { background: linear-gradient(135deg, var(--dash-primary), var(--dash-primary-2)); color: #fff; font-weight: 700; }
.socialeaz-dash .dash-calendar-day.is-today span { color: #fff; }
.socialeaz-dash .dash-calendar-legend { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; font-size: .7rem; color: var(--dash-muted); }
.socialeaz-dash .dash-calendar-legend .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-right: .25rem; }
.socialeaz-dash .dot-primary { background: var(--dash-primary); }
.socialeaz-dash .dot-success { background: var(--dash-success); }
.socialeaz-dash .dot-info { background: var(--dash-info); }

.socialeaz-dash .dash-inbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
.socialeaz-dash .dash-inbox-tile { background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .6rem; padding: .75rem .5rem; text-align: center; text-decoration: none; }
.socialeaz-dash .dash-inbox-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; color: #fff; font-size: 14px; margin-bottom: .4rem; }
.socialeaz-dash .dash-inbox-icon.primary { background: var(--dash-primary); }
.socialeaz-dash .dash-inbox-icon.danger { background: var(--dash-danger); }
.socialeaz-dash .dash-inbox-icon.success { background: var(--dash-success); }
.socialeaz-dash .dash-inbox-value { color: var(--dash-heading); font-weight: 700; font-size: 1.05rem; }
.socialeaz-dash .dash-inbox-label { color: var(--dash-muted); font-size: .68rem; }

.socialeaz-dash .dash-quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
.socialeaz-dash .dash-quick-actions a {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem;
    background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .6rem;
    padding: .9rem .5rem; color: var(--dash-text); text-decoration: none; font-size: .75rem; text-align: center;
}
.socialeaz-dash .dash-quick-actions a:hover { border-color: var(--dash-primary); color: var(--dash-primary); }
.socialeaz-dash .dash-quick-actions a i { font-size: 1.15rem; color: var(--dash-primary); }

.socialeaz-dash .apexcharts-text { fill: var(--dash-muted); }
.socialeaz-dash .apexcharts-legend-text { color: var(--dash-muted) !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var fromInput = document.getElementById('dashboardFromInput');
        var toInput = document.getElementById('dashboardToInput');
        flatpickr('#dashboardDateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: [fromInput.value, toInput.value].filter(Boolean),
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    fromInput.value = selectedDates[0].toISOString().slice(0, 10);
                    toInput.value = selectedDates[1].toISOString().slice(0, 10);
                }
            }
        });

        var darkGrid = { borderColor: 'rgba(20,20,50,.08)' };
        var darkAxis = { labels: { style: { colors: '#8b8d9c' } } };

        var performanceChart = new ApexCharts(document.querySelector('#performanceChart'), {
            chart: { type: 'line', height: 300, toolbar: { show: false }, background: 'transparent' },
            series: [
                { name: 'Reach', data: @json($dailyReach) },
                { name: 'Engagements', data: @json($dailyEngagement) },
                { name: 'Clicks', data: @json($dailyClicks) },
            ],
            xaxis: Object.assign({ categories: @json($dailyLabels) }, darkAxis),
            yaxis: darkAxis,
            stroke: { curve: 'smooth', width: 2.5 },
            colors: ['#7c5cff', '#22d3ee', '#22c55e'],
            legend: { labels: { colors: '#8b8d9c' } },
            grid: darkGrid,
            tooltip: { theme: 'light' },
        });
        performanceChart.render();

        function sparkline(el, data, color) {
            if (!el) return;
            new ApexCharts(el, {
                chart: { type: 'line', height: 32, sparkline: { enabled: true } },
                series: [{ data: data }],
                stroke: { curve: 'smooth', width: 2 },
                colors: [color],
                tooltip: { enabled: false },
            }).render();
        }
        sparkline(document.querySelector('#reachSparkline'), @json($dailyReach), '#a855f7');
        sparkline(document.querySelector('#engagementSparkline'), @json($dailyEngagement), '#0891b2');
    });
</script>
@endpush
