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
        'pinterest' => ['icon' => 'bx-share-alt',  'class' => 'twitter',   'label' => 'Pinterest',  'tag' => 'Profile'],
        'whatsapp'  => ['icon' => 'bxl-whatsapp',  'class' => 'facebook',  'label' => 'WhatsApp',   'tag' => 'Business'],
        'threads'   => ['icon' => 'bx-at',         'class' => 'twitter',   'label' => 'Threads',    'tag' => 'Profile'],
    ];

    $engagementSum = $recentComments->count(); // placeholder avoided below
@endphp

<div class="socialeaz-dash">

    <!-- Header -->
    <div class="dash-header d-flex flex-wrap align-items-start justify-content-between gap-4 mb-6">
        <div>
            <h4 class="dash-title mb-1">Content Dashboard <span>👋</span></h4>
            <p class="dash-subtitle mb-0">Manage, analyze and grow your social media presence</p>
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
                <div class="dash-stat-foot">Across {{ $accountsByPlatform->count() }} platform{{ $accountsByPlatform->count() == 1 ? '' : 's' }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Total Followers</div>
                <div class="dash-stat-value">{{ number_format($totalFollowers) }}</div>
                <div class="dash-stat-foot">Across all platforms</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Engagement Rate</div>
                <div class="dash-stat-value">{{ $engagementRate === null ? '—' : $engagementRate.'%' }}</div>
                <div class="dash-stat-foot">{{ $engagementRate === null ? 'Not enough reach data yet' : '(likes + comments + shares) / reach' }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dash-card dash-stat">
                <div class="dash-stat-label">Total Reach</div>
                <div class="dash-stat-value">{{ number_format($totalReach) }}</div>
                <div class="dash-stat-foot">vs previous period</div>
            </div>
        </div>
    </div>

    <!-- Connected Accounts grid -->
    <div class="dash-card mb-6">
        <div class="dash-card-header">
            <h6 class="mb-0">Connected Accounts</h6>
            <a href="{{ route('admin.posts.create') }}" class="dash-link">Manage Accounts</a>
        </div>
        <div class="row g-3">
            @foreach($accountsOverview as $acct)
            @php $meta = $platformMeta[$acct['platform']] ?? ['icon' => 'bx-globe', 'class' => 'facebook', 'label' => ucfirst($acct['platform'] ?? 'Other'), 'tag' => 'Account']; @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <div class="dash-account-card">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                        <div>
                            <div class="dash-account-name">{{ $meta['label'] }}</div>
                            <div class="dash-account-tag">{{ $meta['tag'] }}</div>
                        </div>
                    </div>
                    <div class="dash-account-count">{{ number_format($acct['follower_count']) }}</div>
                    <div class="dash-account-sub">Followers</div>
                    <div class="dash-status-pill"><span class="dot"></span> Connected</div>
                </div>
            </div>
            @endforeach
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.posts.create') }}" class="dash-add-account-card">
                    <i class="bx bx-plus-circle"></i>
                    <span>Add Account</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-6">
        <!-- Post Performance -->
        <div class="col-lg-8">
            <div class="dash-card h-100">
                <ul class="dash-tabs" role="tablist">
                    <li><button type="button" class="active" data-bs-toggle="tab" data-bs-target="#tab-performance">Post Performance</button></li>
                    <li><button type="button" data-bs-toggle="tab" data-bs-target="#tab-growth">Engagement Trend</button></li>
                    <li><button type="button" data-bs-toggle="tab" data-bs-target="#tab-top">Top Content</button></li>
                </ul>
                <div class="tab-content mt-4">
                    <div class="tab-pane fade show active" id="tab-performance">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0">Post Performance Overview</h6>
                            <span class="dash-chip">Last 7 Days</span>
                        </div>
                        <div id="performanceChart"></div>
                        <div class="row g-3 mt-2">
                            <div class="col-6 col-md-3">
                                <div class="dash-mini-stat"><small>Total Posts</small><h6>{{ $totalPosts }}</h6></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="dash-mini-stat"><small>Total Reach</small><h6>{{ number_format($totalReach) }}</h6></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="dash-mini-stat"><small>Total Engagements</small><h6>{{ number_format($totalLikes + $totalComments + $totalShares) }}</h6></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="dash-mini-stat"><small>Total Clicks</small><h6>{{ number_format(array_sum($dailyClicks)) }}</h6></div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-growth">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0">Likes / Views / Impressions</h6>
                            <span class="dash-chip">Last 7 Months</span>
                        </div>
                        @if(array_sum($monthlyLikes) + array_sum($monthlyViews) + array_sum($monthlyImpressions) > 0)
                        <div id="engagementTrendChart"></div>
                        @else
                        <div class="dash-empty"><i class="bx bx-trending-up"></i><p>No likes, views, or impressions recorded for this period yet.</p></div>
                        @endif
                    </div>
                    <div class="tab-pane fade" id="tab-top">
                        <ul class="dash-list">
                            @forelse($topPosts as $post)
                            @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                            <li>
                                <div class="dash-list-thumb">
                                    @php $thumb = $post->media->first()->media_url ?? $post->media_url ?? null; @endphp
                                    @if($thumb)
                                    <img src="{{ $thumb }}" alt="">
                                    @else
                                    <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                                    @endif
                                </div>
                                <div class="dash-list-body">
                                    <p class="mb-0">{{ Str::limit($post->content ?: '(no caption)', 50) }}</p>
                                    <small>{{ number_format($post->reach) }} reach · {{ number_format($post->likes + $post->comments + $post->shares) }} engagements</small>
                                </div>
                            </li>
                            @empty
                            <li class="dash-empty-row">No posts yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column: Upcoming Posts + Calendar -->
        <div class="col-lg-4">
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Upcoming Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All</a>
                </div>
                <ul class="dash-list">
                    @forelse($upcomingPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <li>
                        <div class="dash-list-thumb">
                            @php $thumb = $post->media->first()->media_url ?? $post->media_url ?? null; @endphp
                            @if($thumb)
                            <img src="{{ $thumb }}" alt="">
                            @else
                            <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                            @endif
                        </div>
                        <div class="dash-list-body">
                            <p class="mb-0">{{ Str::limit($post->content ?: '(no caption)', 34) }}</p>
                            <small>{{ $post->schedule_at?->format('M j, Y g:i A') }} <span class="dash-badge-scheduled">Scheduled</span></small>
                        </div>
                    </li>
                    @empty
                    <li class="dash-empty-row">Nothing scheduled yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="dash-card">
                <div class="dash-card-header">
                    <h6 class="mb-0">Calendar</h6>
                    <span class="dash-chip">{{ $calendarMonth->format('F Y') }}</span>
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
                        <div class="dash-calendar-day {{ $day == now()->day ? 'is-today' : '' }} {{ isset($calendarPostDays[$day]) ? 'has-post' : '' }}">
                            {{ $day }}
                        </div>
                    @endfor
                </div>
                <div class="dash-calendar-legend">
                    <span><i class="dot dot-primary"></i> {{ $calendarPostsThisMonth }} Posts</span>
                    <span><i class="dot dot-success"></i> {{ $calendarCommentsThisMonth }} Comments</span>
                    <span><i class="dot dot-info"></i> {{ $calendarMessagesThisMonth }} Messages</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Posts -->
        <div class="col-md-6 col-lg-4">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <h6 class="mb-0">Recent Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All</a>
                </div>
                <ul class="dash-list">
                    @forelse($recentPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <li>
                        <div class="dash-list-thumb">
                            @php $thumb = $post->media->first()->media_url ?? $post->media_url ?? null; @endphp
                            @if($thumb)
                            <img src="{{ $thumb }}" alt="">
                            @else
                            <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                            @endif
                        </div>
                        <div class="dash-list-body">
                            <p class="mb-0">{{ Str::limit($post->content ?: '(no caption)', 40) }}</p>
                            <small>{{ number_format($post->reach) }} reach · {{ number_format($post->comments) }} comments</small>
                        </div>
                    </li>
                    @empty
                    <li class="dash-empty-row">No posts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Top Performing Posts -->
        <div class="col-md-6 col-lg-4">
            <div class="dash-card h-100">
                <div class="dash-card-header">
                    <h6 class="mb-0">Top Performing Posts</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">View All</a>
                </div>
                <ul class="dash-list">
                    @forelse($topPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <li>
                        <div class="dash-list-thumb">
                            @php $thumb = $post->media->first()->media_url ?? $post->media_url ?? null; @endphp
                            @if($thumb)
                            <img src="{{ $thumb }}" alt="">
                            @else
                            <span class="social-icon-mini {{ $meta['class'] }}"><i class="bx {{ $meta['icon'] }}"></i></span>
                            @endif
                        </div>
                        <div class="dash-list-body">
                            <p class="mb-0">{{ Str::limit($post->content ?: '(no caption)', 40) }}</p>
                            <small>{{ number_format($post->reach) }} reach · {{ number_format($post->likes) }} likes</small>
                        </div>
                        @if($loop->first && $post->reach > 0)
                        <span class="dash-badge-best">Best Reach</span>
                        @endif
                    </li>
                    @empty
                    <li class="dash-empty-row">No posts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Inbox Overview + Quick Actions -->
        <div class="col-lg-4">
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">Inbox Overview</h6>
                </div>
                <ul class="dash-inbox-list">
                    <li>
                        <a href="{{ route('admin.chats.dashboard') }}">
                            <span class="social-icon-mini facebook"><i class="bx bx-envelope"></i></span>
                            Messages
                        </a>
                        <span>{{ $totalMessages }}</span>
                    </li>
                    <li>
                        <a href="{{ route('admin.comments.dashboard') }}">
                            <span class="social-icon-mini instagram"><i class="bx bx-comment-detail"></i></span>
                            Comments
                        </a>
                        <span>{{ $totalCommentsAll }}</span>
                    </li>
                </ul>
            </div>

            <div class="dash-card">
                <div class="dash-card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="dash-quick-actions">
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-edit-alt"></i> Create Post</a>
                    <a href="{{ route('admin.posts.create') }}"><i class="bx bx-user-plus"></i> Add Account</a>
                    <a href="{{ route('admin.chats.dashboard') }}"><i class="bx bx-envelope"></i> Inbox</a>
                    <a href="{{ route('admin.comments.dashboard') }}"><i class="bx bx-bar-chart-alt-2"></i> Comments</a>
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
    border: 1px solid var(--dash-border); text-decoration: none;
}
.socialeaz-dash .dash-btn-ghost { background: var(--dash-card); color: var(--dash-text); }
.socialeaz-dash .dash-btn-ghost:hover { background: var(--dash-card-hover); color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-btn-primary { background: linear-gradient(135deg, var(--dash-primary), var(--dash-primary-2)); color: #fff; box-shadow: 0 4px 12px rgba(124,92,255,.28); }
.socialeaz-dash .dash-btn-primary:hover { opacity: .92; color: #fff; }

.socialeaz-dash .dash-card {
    background: var(--dash-card); border: 1px solid var(--dash-border);
    border-radius: .85rem; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(20,20,50,.04);
}
.socialeaz-dash .dash-card-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;
}
.socialeaz-dash .dash-card-header h6 { color: var(--dash-heading); font-weight: 600; }
.socialeaz-dash .dash-link { color: var(--dash-primary); font-size: .8125rem; text-decoration: none; font-weight: 500; }
.socialeaz-dash .dash-link:hover { text-decoration: underline; }

.socialeaz-dash .dash-stat-label { color: var(--dash-muted); font-size: .8125rem; margin-bottom: .5rem; }
.socialeaz-dash .dash-stat-value { color: var(--dash-heading); font-size: 1.6rem; font-weight: 700; line-height: 1; }
.socialeaz-dash .dash-stat-foot { color: var(--dash-muted); font-size: .75rem; margin-top: .6rem; }
.socialeaz-dash .dash-mini-icons { display: flex; gap: .25rem; }
.socialeaz-dash .dash-mini-icons .social-icon-mini { width: 22px; height: 22px; font-size: 11px; border-radius: 6px; }

.socialeaz-dash .dash-account-card {
    background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: 1rem;
    height: 100%;
}
.socialeaz-dash .dash-account-name { color: var(--dash-heading); font-weight: 600; font-size: .85rem; }
.socialeaz-dash .dash-account-tag { color: var(--dash-muted); font-size: .7rem; }
.socialeaz-dash .dash-account-count { color: var(--dash-heading); font-size: 1.25rem; font-weight: 700; }
.socialeaz-dash .dash-account-sub { color: var(--dash-muted); font-size: .7rem; margin-bottom: .6rem; }
.socialeaz-dash .dash-status-pill { display: inline-flex; align-items: center; gap: .35rem; color: var(--dash-success); font-size: .7rem; font-weight: 600; }
.socialeaz-dash .dash-status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--dash-success); display: inline-block; }
.socialeaz-dash .dash-add-account-card {
    height: 100%; min-height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 1.5px dashed var(--dash-border); border-radius: .7rem; color: var(--dash-muted); text-decoration: none; gap: .4rem;
}
.socialeaz-dash .dash-add-account-card:hover { color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-add-account-card i { font-size: 1.5rem; }

.socialeaz-dash .dash-tabs { display: flex; gap: .5rem; list-style: none; padding: 0; margin: 0; border-bottom: 1px solid var(--dash-border); }
.socialeaz-dash .dash-tabs button {
    background: none; border: none; color: var(--dash-muted); padding: .5rem .25rem; margin-right: 1rem;
    font-size: .85rem; font-weight: 500; border-bottom: 2px solid transparent;
}
.socialeaz-dash .dash-tabs button.active { color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-chip { background: var(--dash-card-hover); color: var(--dash-muted); font-size: .7rem; padding: .25rem .6rem; border-radius: 1rem; }
.socialeaz-dash .dash-mini-stat { background: var(--dash-card-hover); border-radius: .6rem; padding: .65rem .85rem; }
.socialeaz-dash .dash-mini-stat small { color: var(--dash-muted); display: block; font-size: .7rem; }
.socialeaz-dash .dash-mini-stat h6 { color: var(--dash-heading); margin: .15rem 0 0; }

.socialeaz-dash .dash-list { list-style: none; margin: 0; padding: 0; }
.socialeaz-dash .dash-list li { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--dash-border); position: relative; }
.socialeaz-dash .dash-list li:last-child { border-bottom: none; }
.socialeaz-dash .dash-list-thumb { width: 40px; height: 40px; border-radius: .5rem; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--dash-card-hover); }
.socialeaz-dash .dash-list-thumb img { width: 100%; height: 100%; object-fit: cover; }
.socialeaz-dash .dash-list-body p { color: var(--dash-text); font-size: .8125rem; }
.socialeaz-dash .dash-list-body small { color: var(--dash-muted); font-size: .7rem; }
.socialeaz-dash .dash-badge-scheduled { background: rgba(124,92,255,.1); color: var(--dash-primary); padding: .1rem .4rem; border-radius: .3rem; font-size: .65rem; margin-left: .3rem; }
.socialeaz-dash .dash-badge-best { position: absolute; right: 0; top: .6rem; background: rgba(22,163,74,.1); color: var(--dash-success); padding: .15rem .5rem; border-radius: .3rem; font-size: .65rem; font-weight: 600; }
.socialeaz-dash .dash-empty-row { color: var(--dash-muted); text-align: center; padding: 1.5rem 0 !important; border-bottom: none !important; display: block; }
.socialeaz-dash .dash-empty { text-align: center; color: var(--dash-muted); padding: 2.5rem 0; }
.socialeaz-dash .dash-empty i { font-size: 1.75rem; margin-bottom: .5rem; display: block; }

.socialeaz-dash .dash-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
.socialeaz-dash .dash-calendar-head { color: var(--dash-muted); font-size: .65rem; font-weight: 600; padding-bottom: .4rem; }
.socialeaz-dash .dash-calendar-day { font-size: .75rem; color: var(--dash-text); padding: .4rem 0; border-radius: .4rem; position: relative; }
.socialeaz-dash .dash-calendar-day.has-post::after { content: ''; position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%); width: 4px; height: 4px; border-radius: 50%; background: var(--dash-primary); }
.socialeaz-dash .dash-calendar-day.is-today { background: linear-gradient(135deg, var(--dash-primary), var(--dash-primary-2)); color: #fff; font-weight: 700; }
.socialeaz-dash .dash-calendar-legend { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; font-size: .7rem; color: var(--dash-muted); }
.socialeaz-dash .dash-calendar-legend .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-right: .25rem; }
.socialeaz-dash .dot-primary { background: var(--dash-primary); }
.socialeaz-dash .dot-success { background: var(--dash-success); }
.socialeaz-dash .dot-info { background: var(--dash-info); }

.socialeaz-dash .dash-inbox-list { list-style: none; margin: 0; padding: 0; }
.socialeaz-dash .dash-inbox-list li { display: flex; align-items: center; justify-content: space-between; padding: .55rem 0; border-bottom: 1px solid var(--dash-border); }
.socialeaz-dash .dash-inbox-list li:last-child { border-bottom: none; }
.socialeaz-dash .dash-inbox-list a { display: flex; align-items: center; gap: .6rem; color: var(--dash-text); text-decoration: none; font-size: .8125rem; }
.socialeaz-dash .dash-inbox-list .social-icon-mini { width: 30px; height: 30px; font-size: 14px; }
.socialeaz-dash .dash-inbox-list li > span:last-child { color: var(--dash-heading); font-weight: 600; font-size: .8125rem; }

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
            chart: { type: 'line', height: 260, toolbar: { show: false }, background: 'transparent' },
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

        var engagementTrendEl = document.querySelector('#engagementTrendChart');
        if (engagementTrendEl) {
            var engagementTrendChart = new ApexCharts(engagementTrendEl, {
                chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent' },
                series: [
                    { name: 'Likes', data: @json($monthlyLikes) },
                    { name: 'Views', data: @json($monthlyViews) },
                    { name: 'Impressions', data: @json($monthlyImpressions) },
                ],
                xaxis: Object.assign({ categories: @json($months) }, darkAxis),
                yaxis: darkAxis,
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                colors: ['#7c5cff', '#22d3ee', '#facc15'],
                legend: { labels: { colors: '#8b8d9c' } },
                grid: darkGrid,
                tooltip: { theme: 'light' },
            });
            engagementTrendChart.render();
        }

        // Bootstrap tabs aren't wired via data-bs-toggle="tab" targets alone when
        // buttons live outside a `.nav` element (our custom `.dash-tabs` markup) -
        // drive the Bootstrap Tab API directly instead.
        document.querySelectorAll('.dash-tabs button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.dash-tabs button').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var target = document.querySelector(btn.getAttribute('data-bs-target'));
                document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('show', 'active'); });
                target.classList.add('show', 'active');
            });
        });
    });
</script>
@endpush
