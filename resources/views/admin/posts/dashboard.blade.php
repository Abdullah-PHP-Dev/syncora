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

    // Same brand colors the posts.index "Create post" modal uses
    // (resources/js/data/mockPosts.js platformMeta) - kept in sync here
    // since this page has no access to that JS module.
    $platformBrandColors = [
        'facebook' => '#1877F2', 'instagram' => '#E1306C', 'x' => '#111827', 'twitter' => '#111827',
        'linkedin' => '#0A66C2', 'tiktok' => '#111827', 'youtube' => '#FF0000',
        'google' => '#4285F4', 'pinterest' => '#E60023', 'whatsapp' => '#25D366', 'threads' => '#000000',
    ];

    // dash_short() and dash_media_preview() live in app/Helpers/Helper.php
    // now (autoloaded project-wide, alongside adminSetting() etc.) instead
    // of being declared here - a view shouldn't be defining global
    // functions, and the new <x-post-media-thumb> component depends on
    // dash_media_preview() too.

    $statusMeta = [
        'published' => ['label' => 'Published', 'class' => 'success'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'info'],
        'pending'   => ['label' => 'Pending',   'class' => 'warning'],
        'PROCESSING'=> ['label' => 'Pending',   'class' => 'warning'],
        'failed'    => ['label' => 'Failed',    'class' => 'danger'],
        'draft'     => ['label' => 'Draft',     'class' => 'muted'],
    ];

    $isCurrentMonth = $calendarMonth->isSameMonth(now());
    $prevCalMonth = $calendarMonth->copy()->subMonthNoOverflow()->format('Y-m');
    $nextCalMonth = $calendarMonth->copy()->addMonthNoOverflow()->format('Y-m');
@endphp

<div class="socialeaz-dash">

    <!-- Header -->
    <div class="dash-header d-flex flex-wrap align-items-start justify-content-between gap-4 mb-6">
        <div>
            <h4 class="dash-title mb-1">{{ __('admin.dashboard_page.welcome_back', ['name' => explode(' ', trim(auth()->user()->name ?? 'there'))[0]]) }} <span>👋</span></h4>
            <p class="dash-subtitle mb-0">{{ __('admin.dashboard_page.welcome_subtitle') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('admin.posts.dashboard') }}" class="d-flex align-items-center gap-2">
                <input type="text" id="dashboardDateRange" name="date_range" class="dash-input" style="max-width:210px;" placeholder="{{ __('admin.dashboard_page.select_date_range') }}" autocomplete="off" value="{{ $dateFrom && $dateTo ? $dateFrom->format('M j').' - '.$dateTo->format('M j, Y') : '' }}" />
                <input type="hidden" name="from" id="dashboardFromInput" value="{{ $dateFrom?->format('Y-m-d') }}" />
                <input type="hidden" name="to" id="dashboardToInput" value="{{ $dateTo?->format('Y-m-d') }}" />
                <button type="submit" class="dash-btn dash-btn-ghost"><i class="bx bx-calendar"></i></button>
                @if($dateFrom && $dateTo)
                <a href="{{ route('admin.posts.dashboard') }}" class="dash-btn dash-btn-ghost"><i class="bx bx-x"></i></a>
                @endif
            </form>
            <a href="{{ route('admin.chats.dashboard') }}" class="dash-btn dash-btn-ghost dash-bell" title="{{ __('admin.dashboard_page.messages') }}">
                <i class="bx bx-bell"></i>
                @if($totalUnreadMessages > 0)
                <span class="dash-bell-badge">{{ $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages }}</span>
                @endif
            </a>
            <a href="{{ route('admin.posts.index') }}" class="dash-btn dash-btn-primary">
                <i class="bx bx-plus"></i> {{ __('admin.dashboard_page.create_post') }}
            </a>
        </div>
    </div>

    <!-- ================================================================
         Section order (top to bottom), deliberately sequenced by how a
         user actually works through a dashboard: (1) at-a-glance KPIs,
         (2) the actions those KPIs might prompt, (3) status of the
         accounts everything else depends on, (4) trend analysis,
         (5) the planning/production surface (calendar first, since it's
         the primary tool - then history), (6) secondary "what's next /
         what's waiting" widgets in the sidebar.
    ================================================================= -->

    <!-- 1. Overview KPIs -->
    <div class="row g-4 mb-6">
        <div class="col-6 col-lg-3">
            <x-metric-card :label="__('admin.dashboard_page.connected_accounts')" :value="$totalAccounts">
                <x-slot:valueExtra>
                    <div class="dash-mini-icons">
                        @foreach($accountsByPlatform->keys()->take(4) as $p)
                            @php $m = $platformMeta[$p] ?? null; @endphp
                            @if($m)
                            <x-platform-icon :icon="$m['icon']" :color="$platformBrandColors[$p] ?? '#7c5cff'" />
                            @endif
                        @endforeach
                    </div>
                </x-slot:valueExtra>
                <x-slot:foot>
                    {{ trans_choice('admin.dashboard_page.across_platforms', $accountsByPlatform->count(), ['count' => $accountsByPlatform->count()]) }}
                    @if($newAccountsThisWeek > 0)
                    <span class="dash-trend dash-trend-up">+{{ $newAccountsThisWeek }} {{ __('admin.dashboard_page.this_week') }}</span>
                    @endif
                </x-slot:foot>
            </x-metric-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card :label="__('admin.dashboard_page.total_followers')" :value="dash_short($totalFollowers)">
                <x-slot:foot>{{ __('admin.dashboard_page.across_all_platforms') }}</x-slot:foot>
            </x-metric-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card :label="__('admin.dashboard_page.engagement_rate')" :value="$engagementRate === null ? '—' : $engagementRate.'%'">
                <x-slot:foot>
                    @if($engagementChangePercent === null)
                        {{ $engagementRate === null ? __('admin.dashboard_page.not_enough_reach_data') : __('admin.dashboard_page.engagement_formula') }}
                    @else
                        <span class="dash-trend {{ $engagementChangePercent >= 0 ? 'dash-trend-up' : 'dash-trend-down' }}">
                            <i class="bx {{ $engagementChangePercent >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>
                            {{ abs($engagementChangePercent) }}%
                        </span> {{ __('admin.dashboard_page.vs_last_7_days') }}
                    @endif
                </x-slot:foot>
                <div id="engagementSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card :label="__('admin.dashboard_page.total_reach')" :value="dash_short($totalReach)">
                <x-slot:foot>
                    @if($reachChangePercent === null)
                        {{ __('admin.dashboard_page.vs_previous_period') }}
                    @else
                        <span class="dash-trend {{ $reachChangePercent >= 0 ? 'dash-trend-up' : 'dash-trend-down' }}">
                            <i class="bx {{ $reachChangePercent >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>
                            {{ abs($reachChangePercent) }}%
                        </span> {{ __('admin.dashboard_page.vs_last_7_days') }}
                    @endif
                </x-slot:foot>
                <div id="reachSparkline" class="dash-sparkline"></div>
            </x-metric-card>
        </div>
    </div>

    <!-- 3. Connected Accounts - moved up from below the performance chart:
         knowing what's connected (and what's broken) is context the reader
         needs before the analytics below mean anything. -->
    <div class="dash-card mb-6">
        <div class="dash-card-header">
            <h6 class="mb-0">{{ __('admin.dashboard_page.connected_accounts') }}</h6>
            <a href="{{ route('admin.posts.create') }}" class="dash-link">{{ __('admin.dashboard_page.manage_accounts') }}</a>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-4 col-xl-2">
                <button type="button" class="dash-add-account-card" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                    <i class="bx bx-plus-circle"></i>
                    <span>{{ __('admin.dashboard_page.add_account') }}</span>
                </button>
            </div>
            @forelse($accountsOverview as $acct)
            @php
                $meta = $platformMeta[$acct['platform']] ?? ['icon' => 'bx-globe', 'class' => 'facebook', 'label' => ucfirst($acct['platform'] ?? 'Other'), 'tag' => 'Account'];
                $color = $platformBrandColors[$acct['platform']] ?? '#7c5cff';
                $isYoutube = Str::contains($meta['label'], 'YouTube');
                // Second stat is whichever of likes/media/views this
                // platform actually reports first - not every account has
                // all four (eg. YouTube has no "likes" concept here).
                $secondStat = $acct['likes_count'] ? ['value' => $acct['likes_count'], 'label' => __('admin.dashboard_page.likes')]
                    : ($acct['media_count'] ? ['value' => $acct['media_count'], 'label' => __('admin.dashboard_page.posts')]
                    : ['value' => $acct['views_count'], 'label' => __('admin.dashboard_page.views')]);
            @endphp
            <div class="col-6 col-md-4 col-xl-2">
                <div class="dash-account-card">
                    <span class="dash-account-avatar-wrap">
                        @if($acct['image'])
                            <img class="dash-account-avatar" src="{{ $acct['image'] }}">
                        @else
                            <span class="dash-account-avatar dash-account-avatar-fallback" style="background:{{ $color }}1a;color:{{ $color }};">
                                <i class="bx {{ $meta['icon'] }}"></i>
                            </span>
                        @endif
                        <span class="dash-account-badge" style="background:{{ $color }};"><i class="bx {{ $meta['icon'] }}"></i></span>
                    </span>
                    <div class="dash-account-name" title="{{ $acct['name'] ?: $meta['label'] }}">{{ $acct['name'] ?: $meta['label'] }}</div>
                    <div class="dash-account-tag">{{ $meta['tag'] }}</div>
                    <div class="dash-account-stats">
                        <div>
                            <strong>{{ dash_short($acct['follower_count']) }}</strong>
                            <span>{{ $isYoutube ? __('admin.dashboard_page.subs') : __('admin.dashboard_page.followers') }}</span>
                        </div>
                        <div>
                            <strong>{{ dash_short($secondStat['value']) }}</strong>
                            <span>{{ $secondStat['label'] }}</span>
                        </div>
                    </div>
                    <div class="dash-status-pill"><span class="dot"></span> {{ __('admin.dashboard_page.connected') }}</div>
                </div>
            </div>
            @empty
            <div class="col-12 dash-empty-row">{{ __('admin.dashboard_page.no_accounts_connected') }}</div>
            @endforelse
            
        </div>
    </div>

    <!-- 4. Performance analytics -->
    <div class="row g-4 mb-6">
        <!-- Post Performance -->
        <div class="col-lg-8">
            <div class="dash-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.performance_overview') }}</h6>
                    <span class="dash-chip">{{ __('admin.dashboard_page.last_7_days') }}</span>
                </div>
                <div id="performanceChart"></div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="col-lg-4">
            <div class="dash-card h-100">
                <h6 class="mb-3">{{ __('admin.dashboard_page.performance_summary') }}</h6>
                <ul class="dash-summary-list">
                    <li>
                        <span class="dash-summary-icon primary"><i class="bx bx-file"></i></span>
                        <span class="dash-summary-label">{{ __('admin.dashboard_page.total_posts') }}</span>
                        <span class="dash-summary-value">{{ $totalPosts }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon info"><i class="bx bx-show"></i></span>
                        <span class="dash-summary-label">{{ __('admin.dashboard_page.total_reach') }}</span>
                        <span class="dash-summary-value">{{ dash_short($totalReach) }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon warning"><i class="bx bx-bulb"></i></span>
                        <span class="dash-summary-label">{{ __('admin.dashboard_page.total_engagements') }}</span>
                        <span class="dash-summary-value">{{ dash_short($totalLikes + $totalComments + $totalShares) }}</span>
                    </li>
                    <li>
                        <span class="dash-summary-icon success"><i class="bx bx-mouse"></i></span>
                        <span class="dash-summary-label">{{ __('admin.dashboard_page.total_clicks') }}</span>
                        <span class="dash-summary-value">{{ dash_short(array_sum($dailyClicks)) }}</span>
                    </li>
                </ul>
                <a href="{{ route('admin.posts.index') }}" class="dash-link d-inline-flex align-items-center gap-1">
                    {{ __('admin.dashboard_page.view_detailed_report') }} <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 5/6. Planning + production (main column) and secondary "what's
         next" widgets (sidebar) -->
    <div class="row g-4">
        <!-- Main column -->
        <div class="col-lg-8">
            <!-- Content Calendar - promoted from the narrow sidebar to the
                 full main column: it's the primary planning surface, and a
                 full month grid needs the room to be legible/usable rather
                 than being squeezed into a 4-column-wide card. -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.content_calendar') }}</h6>
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
                        <div class="dash-calendar-day dash-calendar-pad"></div>
                    @endfor
                    @for($day=1;$day<=$calendarMonth->daysInMonth;$day++)
                        @php
                            $dayPosts = $calendarMonthPosts[$day] ?? null;
                            $dayPreview = dash_media_preview($dayPosts?->first()?->media->first());
                            $isToday = $isCurrentMonth && $day == now()->day;
                            $cellDate = $calendarMonth->copy()->day($day)->format('Y-m-d');
                            // A past day with no posts has nothing to create
                            // (you can't schedule/publish into the past) -
                            // only disables the "create" affordance, not the
                            // cell itself: a past day that DOES have posts
                            // still opens the view/list popup as normal.
                            $isPastEmpty = !$dayPosts && $cellDate < now()->format('Y-m-d');
                            $dayPostsSummary = $dayPosts ? $dayPosts->map(fn ($p) => [
                                'id' => $p->id,
                                'platform' => $p->platform,
                                'platforms' => $p->group_platforms ?? [['platform' => $p->platform, 'status' => $p->status, 'post_id' => $p->id]],
                                'status' => $p->status,
                                'content' => \Illuminate\Support\Str::limit($p->content, 60),
                                'time' => ($p->schedule_mode && $p->schedule_at ? $p->schedule_at : $p->created_at)->format('g:i A'),
                                'account_name' => $p->socialAccount->name ?? $p->socialAccount->username ?? null,
                            ])->values() : [];
                        @endphp
                        <div
                                class="dash-calendar-day {{ $isToday ? 'is-today' : '' }} {{ $dayPosts ? 'has-post' : '' }} {{ $dayPreview ? 'dash-calendar-day-'.$dayPreview['kind'] : '' }} {{ $isPastEmpty ? 'dash-calendar-day-disabled' : '' }}"
                                title="{{ $dayPosts ? $dayPosts->count().' post(s)' : ($isPastEmpty ? 'You can\'t create a post in the past' : 'Create a post for '.$cellDate) }}"
                                data-calendar-date="{{ $cellDate }}"
                                data-calendar-posts="{{ json_encode($dayPostsSummary) }}"
                                data-calendar-past-empty="{{ $isPastEmpty ? '1' : '0' }}"
                        >
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
                            @if($dayPosts && $dayPosts->count() > 1)
                                <span class="dash-calendar-day-count">{{ $dayPosts->count() }}</span>
                            @endif
                        </div>
                    @endfor
                </div>
                <div class="dash-calendar-legend">
                    <span><i class="dot dot-primary"></i> {{ $calendarPostsThisMonth }} {{ __('admin.dashboard_page.posts') }}</span>
                    <span><i class="dot dot-success"></i> {{ $calendarCommentsThisMonth }} {{ __('admin.dashboard_page.comments') }}</span>
                    <span><i class="dot dot-info"></i> {{ $calendarMessagesThisMonth }} {{ __('admin.dashboard_page.messages') }}</span>
                </div>
            </div>

            <!-- Recent Posts table -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.recent_posts') }}</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">{{ __('admin.dashboard_page.view_all_posts') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.dashboard_page.post') }}</th>
                                <th>{{ __('admin.dashboard_page.platform') }}</th>
                                <th>{{ __('admin.dashboard_page.reach') }}</th>
                                <th>{{ __('admin.dashboard_page.engagement') }}</th>
                                <th>{{ __('admin.dashboard_page.date') }}</th>
                                <th>{{ __('admin.dashboard_page.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPosts as $post)
                            @php
                                $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook', 'label' => ucfirst($post->platform)];
                                $sm = $statusMeta[$post->status] ?? ['label' => ucfirst($post->status), 'class' => 'muted'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <x-post-media-thumb
                                            :media="$post->media->first()"
                                            :fallback-icon="$meta['icon']"
                                            :fallback-color="$platformBrandColors[$post->platform] ?? '#7c5cff'" />
                                        <span class="dash-table-title">{{ Str::limit($post->content ?: __('admin.dashboard_page.no_caption'), 42) }}</span>
                                    </div>
                                </td>
                                <td><x-platform-icon :icon="$meta['icon']" :color="$platformBrandColors[$post->platform] ?? '#7c5cff'" size="xs" /></td>
                                <td>{{ dash_short($post->reach) }}</td>
                                <td>{{ dash_short($post->likes + $post->comments + $post->shares) }}</td>
                                <td>{{ $post->created_at->format('M j, Y') }}</td>
                                <td><span class="dash-badge dash-badge-{{ $sm['class'] }}">{{ $sm['label'] }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="dash-empty-row">{{ __('admin.dashboard_page.no_posts_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-4">
            <!-- Upcoming Posts -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.upcoming_posts') }}</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">{{ __('admin.dashboard_page.view_all') }}</a>
                </div>
                <ul class="dash-list">
                    @forelse($upcomingPosts as $post)
                    @php
                        $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook'];
                        $brandColor = $platformBrandColors[$post->platform] ?? '#5D87FF';
                        $account = $post->socialAccount;
                        $accountName = $account->name ?: ($account->username ?? ucfirst($post->platform));
                    @endphp
                    <li>
                        <span class="dash-list-avatar-wrap">
                            @if($account && $account->avatar_url)
                                <img src="{{ $account->avatar_url }}" class="dash-list-avatar" alt="{{ $accountName }}">
                            @else
                                <span class="dash-list-avatar dash-list-avatar-fallback" style="background: {{ $brandColor }}1a; color: {{ $brandColor }};">
                                    <i class="bx {{ $meta['icon'] }}"></i>
                                </span>
                            @endif
                            <span class="dash-list-badge" style="background: {{ $brandColor }};">
                                <i class="bx {{ $meta['icon'] }}"></i>
                            </span>
                        </span>
                        <div class="dash-list-body">
                            <p class="mb-0">{{ Str::limit($post->content ?: __('admin.dashboard_page.no_caption'), 34) }}</p>
                            <small>{{ $accountName }} &middot; {{ $meta['label'] ?? ucfirst($post->platform) }}</small>
                        </div>
                        <div class="dash-list-when">
                            <small>{{ $post->schedule_at?->format('M j, Y') }}</small>
                            <small>{{ $post->schedule_at?->format('g:i A') }}</small>
                        </div>
                    </li>
                    @empty
                    <li class="dash-empty-row">{{ __('admin.dashboard_page.nothing_scheduled') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Top Performing Posts - moved into the sidebar next to
                 Inbox Overview; column classes changed from the
                 viewport-relative col-md-6/col-xl-3 (sized for the
                 wide main column) to a plain col-6 2-up grid that fits
                 this narrower sidebar column correctly regardless of
                 viewport width. -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.top_performing_posts') }}</h6>
                    <a href="{{ route('admin.posts.index') }}" class="dash-link">{{ __('admin.dashboard_page.view_all') }}</a>
                </div>
                <div class="row g-3">
                    @forelse($topPosts as $post)
                    @php $meta = $platformMeta[$post->platform] ?? ['icon' => 'bx-globe', 'class' => 'facebook']; @endphp
                    <div class="col-6">
                        <div class="dash-top-post">
                            @if($loop->first && $post->reach > 0)
                            <span class="dash-badge-best">{{ __('admin.dashboard_page.best_reach') }}</span>
                            @endif
                            <x-post-media-thumb
                                :media="$post->media->first()"
                                :fallback-icon="$meta['icon']"
                                :fallback-color="$platformBrandColors[$post->platform] ?? '#7c5cff'"
                                size="lg" />
                            <p class="mb-0 mt-2">{{ Str::limit($post->content ?: __('admin.dashboard_page.no_caption'), 40) }}</p>
                            <small>{{ __('admin.dashboard_page.reach_likes', ['reach' => dash_short($post->reach), 'likes' => dash_short($post->likes)]) }}</small>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 dash-empty-row">{{ __('admin.dashboard_page.no_posts_yet') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- Inbox Overview -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6 class="mb-0">{{ __('admin.dashboard_page.inbox_overview') }}</h6>
                    <a href="{{ route('admin.chats.dashboard') }}" class="dash-link">{{ __('admin.dashboard_page.view_all') }}</a>
                </div>
                <div class="dash-inbox-grid">
                    <a href="{{ route('admin.chats.dashboard') }}" class="dash-inbox-tile">
                        <span class="dash-inbox-icon primary"><i class="bx bx-envelope"></i></span>
                        <div class="dash-inbox-value">{{ $totalMessages }}</div>
                        <div class="dash-inbox-label">{{ __('admin.dashboard_page.messages') }}</div>
                    </a>
                    <a href="{{ route('admin.comments.dashboard') }}" class="dash-inbox-tile">
                        <span class="dash-inbox-icon danger"><i class="bx bx-comment-detail"></i></span>
                        <div class="dash-inbox-value">{{ $totalCommentsAll }}</div>
                        <div class="dash-inbox-label">{{ __('admin.dashboard_page.comments') }}</div>
                    </a>
                    <div class="dash-inbox-tile" title="{{ __('admin.dashboard_page.mention_tracking_not_wired') }}">
                        <span class="dash-inbox-icon success"><i class="bx bx-at"></i></span>
                        <div class="dash-inbox-value">0</div>
                        <div class="dash-inbox-label">{{ __('admin.dashboard_page.mentions') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================
     QUICK POST MODAL - opened from an empty calendar day; posts
     through the same admin.posts.quick endpoint the dashboard's own
     composer uses, just pre-scoped to the clicked date.
========================================================= --}}
<div class="modal fade" id="calendarQuickPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content quick-create-modal">
            <form id="calendarQuickPostForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('admin.dashboard_page.create_post_modal_title') }} <span class="text-muted fw-normal fs-6" id="calendarQuickPostDateLabel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="calendarQuickPostError"></div>

                    <div class="composer-user-row">
                        <div class="composer-avatar">{{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}</div>
                        <div>
                            <strong>{{ Auth::user()->name ?? 'Admin' }}</strong>
                            <div class="composer-audience"><i class="bx bx-group"></i> {{ __('admin.dashboard_page.friends') }}</div>
                        </div>
                    </div>

                    <textarea name="content" class="quick-textarea" rows="4" placeholder="{{ __('admin.dashboard_page.whats_on_your_mind', ['name' => explode(' ', Auth::user()->name ?? 'Admin')[0]]) }}"></textarea>

                    <div class="quick-media-preview d-none" id="calendarQuickMediaPreview">
                        <img class="d-none" id="calendarQuickMediaImg">
                        <video class="d-none" id="calendarQuickMediaVideo" controls></video>
                        <button type="button" class="remove-media-btn" id="calendarQuickMediaRemove"><i class="bx bx-x"></i></button>
                    </div>

                    <div class="quick-platform-label">{{ __('admin.dashboard_page.post_to') }}</div>
                    <div class="quick-platform-select" id="calendarQuickPostPlatforms">
                        @php $seenPlatforms = []; @endphp
                        @foreach($postingAccounts as $account)
                            @continue(in_array($account->platform, $seenPlatforms))
                            @php
                                $seenPlatforms[] = $account->platform;
                                $meta = $platformMeta[$account->platform] ?? ['icon' => 'bx-globe', 'label' => ucfirst($account->platform)];
                                $color = $platformBrandColors[$account->platform] ?? '#7c5cff';
                            @endphp
                            {{-- Value stays the platform key, not this specific account
                                 id - quickStore() posts to every posting-permitted
                                 account on that platform, so a second Facebook Page
                                 would be silently included too; this only controls
                                 which platform(s) get selected. --}}
                            <div class="quick-account-chip" data-platform="{{ $account->platform }}">
                                <span class="quick-account-avatar-wrap">
                                    @if($account->avatar_url)
                                        <img class="quick-account-avatar" src="{{ $account->avatar_url }}">
                                    @else
                                        <span class="quick-account-avatar quick-account-avatar-fallback" style="background:{{ $color }}1a;color:{{ $color }};">
                                            <i class="bx {{ $meta['icon'] }}"></i>
                                        </span>
                                    @endif
                                    <span class="quick-account-badge" style="background:{{ $color }};">
                                        <i class="bx {{ $meta['icon'] }}"></i>
                                    </span>
                                </span>
                                <span class="quick-account-name">{{ $account->name ?: ($account->username ?: $meta['label']) }}</span>
                            </div>
                        @endforeach
                        @if(empty($seenPlatforms))
                            <p class="text-muted small mb-0">{{ __('admin.dashboard_page.no_connected_posting_accounts') }} <a href="{{ route('admin.posts.create') }}">{{ __('admin.dashboard_page.connect_one') }}</a> {{ __('admin.dashboard_page.first') }}.</p>
                        @endif
                    </div>

                    <div class="quick-schedule-row">
                        <label class="quick-checkbox">
                            <input type="checkbox" name="schedule_mode" value="1" id="calendarQuickPostScheduleToggle" checked>
                            {{ __('admin.dashboard_page.schedule_for_later') }}
                        </label>
                        <input type="datetime-local" name="schedule_at" id="calendarQuickPostScheduleAt" class="modern-select">
                    </div>

                    <div class="add-to-post-row">
                        <span>{{ __('admin.dashboard_page.add_to_your_post') }}</span>
                        <div class="add-to-post-icons">
                            <label class="media-upload-btn" title="Photo/Video">
                                <i class="bx bx-image" style="color:#45BD62"></i>
                                <input type="file" name="media" id="calendarQuickPostMediaInput" class="d-none" accept="image/*,video/*">
                            </label>
                            <i class="bx bx-user-plus" style="color:#1877F2" title="Tag people"></i>
                            <i class="bx bx-smile" style="color:#F7B928" title="Feeling/activity"></i>
                            <i class="bx bx-map" style="color:#F5533D" title="Location"></i>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100" id="calendarQuickPostSubmit">
                        <span id="calendarQuickPostSubmitLabel">{{ __('admin.dashboard_page.post_button') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- =========================================================
     VIEW POST MODAL - opened from a calendar day that already has
     post(s); content is filled in by JS from admin.posts.quick-view.
========================================================= --}}
<div class="modal fade cal-modal" id="calendarViewPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cal-modal-content">
            <div class="modal-header cal-modal-header">
                <div>
                    <span class="cal-modal-eyebrow" id="calendarViewPostPlatformIcon"></span>
                    <h5 class="modal-title cal-modal-title" id="calendarViewPostAccountName"></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body cal-modal-body" id="calendarViewPostBody">
                <div class="text-center text-muted py-4">
                    <i class="bx bx-loader-alt bx-spin fs-3"></i>
                </div>
            </div>
            <div class="modal-footer cal-modal-footer">
                <button type="button" class="cal-btn cal-btn-ghost" data-bs-dismiss="modal">{{ __('admin.dashboard_page.close') }}</button>
                <a href="#" class="cal-btn cal-btn-primary" id="calendarViewPostOpenLink">
                    <i class="bx bx-link-external"></i> {{ __('admin.dashboard_page.open_full_post') }}
                </a>
            </div>
        </div>
    </div>
</div>

{{-- When a calendar day has more than one post, this lists them so the
     admin can pick which one to open in the view modal above. --}}
<div class="modal fade cal-modal" id="calendarDayPostsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cal-modal-content">
            <div class="modal-header cal-modal-header">
                <div>
                    <span class="cal-modal-eyebrow" id="calendarDayPostsCount"></span>
                    <h5 class="modal-title cal-modal-title" id="calendarDayPostsDateLabel"></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body cal-modal-body">
                <div class="cal-day-posts-list" id="calendarDayPostsList"></div>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================
     ADD ACCOUNT MODAL - opened from the "Add Account" tile on the
     Connected Accounts row. Renders the same shared social-connect-
     modal Blade component the Ads dashboard uses (see
     resources/views/components/social-connect-modal.blade.php) -
     posting only supplies its own platform list and OAuth redirect
     routes (the same ones posts/create.blade.php already uses); the
     markup/styling lives in exactly one place. WhatsApp is the one
     exception: its connect flow is an embedded-signup JS widget that
     only exists on the Create Post page, so it links there instead of
     authorizing directly.
========================================================= --}}
@php
    $postingConnectPlatforms = [
        ['key' => 'facebook',  'class' => 'facebook',  'icon' => 'bxl-facebook',  'label' => 'Facebook',  'url' => route('admin.social-accounts.redirect', ['platform' => 'facebook'])],
        ['key' => 'instagram', 'class' => 'instagram', 'icon' => 'bxl-instagram', 'label' => 'Instagram', 'url' => route('admin.post-accounts.instagram.redirect')],
        ['key' => 'threads',   'class' => 'threads',   'icon' => 'bx-at',         'label' => 'Threads',   'url' => route('admin.post-accounts.threads.redirect')],
        ['key' => 'pinterest', 'class' => 'pinterest', 'icon' => 'bx-share-alt',  'label' => 'Pinterest', 'url' => route('admin.post-accounts.pinterest.redirect')],
        ['key' => 'x',         'class' => 'twitter',   'icon' => 'bxl-twitter',   'label' => 'X',         'url' => route('admin.post-accounts.x.redirect')],
        ['key' => 'linkedin',  'class' => 'linkedin',  'icon' => 'bxl-linkedin',  'label' => 'LinkedIn',  'url' => route('admin.social-accounts.redirect', ['platform' => 'linkedin'])],
        ['key' => 'tiktok',    'class' => 'tiktok',    'icon' => 'bxl-tiktok',    'label' => 'TikTok',    'url' => route('admin.social-accounts.redirect', ['platform' => 'tiktok'])],
        ['key' => 'google',    'class' => 'google',    'icon' => 'bxl-google',    'label' => 'Google / YouTube', 'url' => route('admin.social-accounts.redirect', ['platform' => 'google'])],
        ['key' => 'whatsapp',  'class' => 'whatsapp',  'icon' => 'bxl-whatsapp',  'label' => 'WhatsApp',  'url' => route('admin.posts.create')],
    ];
@endphp
<x-social-connect-modal id="addAccountModal" :platforms="$postingConnectPlatforms" />
@endsection

@push('styles')
<style>
/* Status colors, declared once at :root rather than only inside
   .socialeaz-dash - .dash-badge-* (Blade-rendered, inside the wrapper)
   and .cal-status-badge.* (built by vanilla JS for the calendar modals,
   which deliberately render outside .socialeaz-dash - see the big
   comment further down) used to each hardcode their own copy of these
   same five rgba/hex pairs. :root custom properties are visible
   regardless of DOM nesting, so both can reference the same values now. */
:root {
    --status-success-bg: rgba(22,163,74,.1);
    --status-success-color: #16a34a;
    --status-info-bg: rgba(8,145,178,.1);
    --status-info-color: #0891b2;
    --status-warning-bg: rgba(217,119,6,.1);
    --status-warning-color: #d97706;
    --status-danger-bg: rgba(225,29,72,.1);
    --status-danger-color: #e11d48;
    --status-muted-bg: rgba(139,141,156,.12);
    --status-muted-color: #8b8d9c;
}

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

/* Brand colors used to be duplicated here as one .social-icon-mini.{platform}
   rule per platform, scoped under .socialeaz-dash - and had quietly drifted
   out of sync with $platformBrandColors below (Twitter/X, Google, and
   Instagram no longer matched), so the same platform icon rendered a
   different color depending on where on the page it was. The
   platform-icon Blade component now takes the color as a prop straight
   from $platformBrandColors instead, so there is exactly one place
   these values live. */
.socialeaz-dash .social-icon-xs { width: 26px !important; height: 26px !important; font-size: 12px !important; border-radius: 7px !important; }

.socialeaz-dash .dash-account-card {
    background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: 1rem;
    height: 100%; text-align: center;
}
.socialeaz-dash .dash-account-avatar-wrap { position: relative; display: inline-block; margin: 0 auto .6rem; width: 48px; height: 48px; }
.socialeaz-dash .dash-account-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; display: block; }
.socialeaz-dash .dash-account-avatar-fallback { display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
.socialeaz-dash .dash-account-badge {
    position: absolute; bottom: -2px; right: -2px; width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff;
    border: 2px solid var(--dash-card-hover);
}
.socialeaz-dash .dash-account-name {
    color: var(--dash-heading); font-weight: 600; font-size: .85rem;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.socialeaz-dash .dash-account-tag { color: var(--dash-muted); font-size: .7rem; margin-bottom: .6rem; }
.socialeaz-dash .dash-account-stats {
    display: flex; justify-content: center; gap: .9rem; margin-bottom: .6rem;
    padding-bottom: .6rem; border-bottom: 1px solid var(--dash-border);
}
.socialeaz-dash .dash-account-stats > div { display: flex; flex-direction: column; }
.socialeaz-dash .dash-account-stats strong { color: var(--dash-heading); font-size: .85rem; font-weight: 700; }
.socialeaz-dash .dash-account-stats span { color: var(--dash-muted); font-size: .65rem; }
.socialeaz-dash .dash-status-pill { display: inline-flex; align-items: center; gap: .35rem; color: var(--dash-success); font-size: .7rem; font-weight: 600; }
.socialeaz-dash .dash-status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--dash-success); display: inline-block; }
.socialeaz-dash .dash-add-account-card {
    width: 100%; height: 100%; min-height: 140px; display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 1.5px dashed var(--dash-border); border-radius: .7rem; background: transparent;
    color: var(--dash-muted); text-decoration: none; gap: .4rem; cursor: pointer; font: inherit;
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
.socialeaz-dash .dash-list-avatar-wrap { position: relative; width: 36px; height: 36px; flex-shrink: 0; }
.socialeaz-dash .dash-list-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; display: block; }
.socialeaz-dash .dash-list-avatar-fallback { display: flex; align-items: center; justify-content: center; font-size: 15px; }
.socialeaz-dash .dash-list-badge {
    position: absolute; bottom: -2px; right: -2px; width: 16px; height: 16px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 9px; color: #fff;
    border: 2px solid var(--dash-card);
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
.socialeaz-dash .dash-badge-success { background: var(--status-success-bg); color: var(--status-success-color); }
.socialeaz-dash .dash-badge-info { background: var(--status-info-bg); color: var(--status-info-color); }
.socialeaz-dash .dash-badge-warning { background: var(--status-warning-bg); color: var(--status-warning-color); }
.socialeaz-dash .dash-badge-danger { background: var(--status-danger-bg); color: var(--status-danger-color); }
.socialeaz-dash .dash-badge-muted { background: var(--status-muted-bg); color: var(--status-muted-color); }

.socialeaz-dash .dash-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; text-align: center; }
.socialeaz-dash .dash-calendar-head { color: var(--dash-muted); font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; padding-bottom: .5rem; }
.socialeaz-dash .dash-calendar-day {
    font-size: .78rem; color: var(--dash-text); border-radius: .6rem; position: relative;
    aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; overflow: hidden;
    background: var(--dash-card-hover); border: 1px solid transparent; cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
.socialeaz-dash .dash-calendar-day:hover {
    transform: translateY(-2px); border-color: var(--dash-primary); box-shadow: 0 6px 16px rgba(124,92,255,.18); z-index: 2;
}
.socialeaz-dash .dash-calendar-day:empty,
.socialeaz-dash .dash-calendar-day.dash-calendar-pad { cursor: default; background: transparent; box-shadow: none; }
.socialeaz-dash .dash-calendar-day:empty:hover,
.socialeaz-dash .dash-calendar-day.dash-calendar-pad:hover { transform: none; border-color: transparent; box-shadow: none; }
/* A past day with nothing scheduled on it can't have a post created for
   it (no such thing as scheduling into the past) - dimmed and inert,
   same treatment as the leading blank padding cells. */
.socialeaz-dash .dash-calendar-day.dash-calendar-day-disabled {
    cursor: default; opacity: .45; color: var(--dash-muted);
}
.socialeaz-dash .dash-calendar-day.dash-calendar-day-disabled:hover {
    transform: none; border-color: transparent; box-shadow: none;
}
.socialeaz-dash .dash-calendar-day img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .5; }
.socialeaz-dash .dash-calendar-day span { position: relative; z-index: 1; }
.socialeaz-dash .dash-calendar-day.has-post span { color: #fff; font-weight: 700; text-shadow: 0 1px 3px rgba(0,0,0,.5); }
.socialeaz-dash .dash-calendar-day.has-post { box-shadow: inset 0 0 0 1px rgba(20,20,50,.06); }
.socialeaz-dash .dash-calendar-day-count {
    position: absolute; bottom: 3px; right: 3px; z-index: 1; min-width: 14px; height: 14px; padding: 0 3px;
    border-radius: 7px; background: var(--dash-primary); color: #fff; font-size: .58rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; line-height: 1;
}
.socialeaz-dash .dash-calendar-day.has-post:not(.dash-calendar-day-image):not(.dash-calendar-day-video):not(.dash-calendar-day-file) {
    background: rgba(124,92,255,.1);
}
.socialeaz-dash .dash-calendar-day.has-post:not(.dash-calendar-day-image):not(.dash-calendar-day-video):not(.dash-calendar-day-file) span {
    color: var(--dash-heading); text-shadow: none;
}
.socialeaz-dash .dash-calendar-day.has-post:not(.dash-calendar-day-image):not(.dash-calendar-day-video):not(.dash-calendar-day-file) .dash-calendar-day-count {
    background: var(--dash-primary); color: #fff; text-shadow: none;
}
.socialeaz-dash .dash-calendar-media-icon { position: absolute; top: 2px; right: 2px; z-index: 1; font-size: .7rem; color: var(--dash-primary); }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) { background: #1e1e2d; }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) span { color: #fff; }
.socialeaz-dash .dash-calendar-day-video:not(:has(img)) .dash-calendar-media-icon { color: #fff; }
.socialeaz-dash .dash-calendar-day.is-today { box-shadow: inset 0 0 0 2px var(--dash-primary); font-weight: 700; }
.socialeaz-dash .dash-calendar-day.is-today:not(.has-post) span { color: var(--dash-primary); }
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

.socialeaz-dash .dash-action-tile {
    display: flex; align-items: center; gap: .85rem; height: 100%;
    text-decoration: none; color: var(--dash-text); transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.socialeaz-dash .dash-action-tile:hover {
    transform: translateY(-2px); border-color: var(--dash-primary); color: var(--dash-text);
    box-shadow: 0 8px 20px rgba(20,20,50,.06); text-decoration: none;
}
.socialeaz-dash .dash-action-icon {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    width: 44px; height: 44px; border-radius: 50%; font-size: 19px; color: #fff;
}
.socialeaz-dash .dash-action-icon.primary { background: var(--dash-primary); }
.socialeaz-dash .dash-action-icon.info { background: var(--dash-info); }
.socialeaz-dash .dash-action-icon.warning { background: var(--dash-warning); }
.socialeaz-dash .dash-action-icon.success { background: var(--dash-success); }
.socialeaz-dash .dash-action-label { font-weight: 600; font-size: .875rem; color: var(--dash-heading); }

.socialeaz-dash .apexcharts-text { fill: var(--dash-muted); }
.socialeaz-dash .apexcharts-legend-text { color: var(--dash-muted) !important; }

/* =========================================================
   CALENDAR MODALS - deliberately NOT scoped under .socialeaz-dash.
   Bootstrap modals in this layout render outside that wrapper div,
   so every .socialeaz-dash .dash-* rule above never reached them -
   that's why the first version of these rendered as bare, unstyled
   Bootstrap defaults. Own class names, own rules, same look.
========================================================= --}}
.cal-modal-content {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 24px 64px rgba(20,20,50,.22);
}
.cal-modal-header {
    border-bottom: 1px solid rgba(20,20,40,.08);
    padding: 1.25rem 1.5rem;
    align-items: flex-start;
}
.cal-modal-eyebrow {
    display: block;
    color: #7c5cff;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: .2rem;
}
.cal-modal-title {
    color: #1e1e2d;
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0;
}
.cal-modal-body { padding: 1.5rem; }
.cal-modal-footer {
    border-top: 1px solid rgba(20,20,40,.08);
    padding: 1rem 1.5rem;
}

.cal-form-label {
    display: block;
    color: #1e1e2d;
    font-weight: 600;
    font-size: .8rem;
    margin-bottom: .5rem;
}
.cal-form-control {
    display: block;
    width: 100%;
    border: 1.5px solid rgba(20,20,40,.1);
    border-radius: .6rem;
    padding: .65rem .9rem;
    font-size: .875rem;
    color: #1e1e2d;
    background: #fff;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.cal-form-control:focus {
    outline: none;
    border-color: #7c5cff;
    box-shadow: 0 0 0 .2rem rgba(124,92,255,.14);
}
.cal-form-control::placeholder { color: #8b8d9c; }
.cal-hint { color: #8b8d9c; font-size: .78rem; margin: .5rem 0 0; }

.cal-platform-picker { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
.cal-platform-chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .5rem 1rem;
    border-radius: 2rem;
    border: 1.5px solid rgba(20,20,40,.1);
    background: #f7f7fc;
    color: #4b4d5c;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s ease;
}
.cal-platform-chip:hover { border-color: #7c5cff; color: #7c5cff; }
.cal-platform-chip input { display: none; }
.cal-platform-chip:has(input:checked) {
    background: rgba(124,92,255,.1);
    border-color: #7c5cff;
    color: #7c5cff;
}
.cal-platform-chip i { font-size: 1rem; }

.cal-schedule-row { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; }
.cal-schedule-row .form-check { display: flex; align-items: center; gap: .5rem; }
.cal-schedule-row .form-check-input { width: 2.4em; height: 1.35em; margin: 0; cursor: pointer; }
.cal-schedule-row .form-check-input:checked { background-color: #7c5cff; border-color: #7c5cff; }
.cal-schedule-row .form-check-label { color: #1e1e2d; cursor: pointer; }
.cal-schedule-row .cal-form-control { flex: 1; min-width: 200px; }

.cal-btn {
    display: inline-flex; align-items: center; gap: .375rem;
    border-radius: .6rem;
    padding: .55rem 1.15rem;
    font-size: .84rem;
    font-weight: 600;
    border: 1px solid rgba(20,20,40,.08);
    text-decoration: none;
    cursor: pointer;
}
.cal-btn-ghost { background: #f7f7fc; color: #4b4d5c; }
.cal-btn-ghost:hover { background: #eeeef7; color: #7c5cff; border-color: #7c5cff; }
.cal-btn-primary {
    background: linear-gradient(135deg, #7c5cff, #a855f7);
    color: #fff; border: none;
    box-shadow: 0 4px 12px rgba(124,92,255,.3);
}
.cal-btn-primary:hover { opacity: .92; color: #fff; }
.cal-btn-primary:disabled { opacity: .6; cursor: not-allowed; }

.cal-day-posts-list { display: flex; flex-direction: column; gap: .6rem; }
.cal-day-post-item {
    display: flex; align-items: center; gap: .85rem;
    width: 100%; text-align: left;
    padding: .8rem .9rem;
    border: 1.5px solid rgba(20,20,40,.07);
    border-radius: .85rem;
    background: #fff;
    color: #1e1e2d;
    font-size: .85rem;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.cal-day-post-item:hover {
    border-color: rgba(124,92,255,.35);
    box-shadow: 0 6px 18px rgba(20,20,50,.08);
    transform: translateY(-1px);
}
.cal-day-post-icons { display: flex; flex-shrink: 0; }
.cal-day-post-icon {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: .7rem;
    font-size: 1.05rem; flex-shrink: 0;
}
.cal-day-post-icon-stacked {
    width: 36px; height: 36px; border: 2px solid #fff;
    margin-left: -12px;
}
.cal-day-post-icon-stacked:first-child { margin-left: 0; }
.cal-day-post-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem; }
.cal-day-post-content {
    font-weight: 600; color: #1e1e2d;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cal-day-post-subtext {
    font-size: .74rem; color: #8b8d9c;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cal-day-post-arrow { color: #c4c7d4; font-size: 1.1rem; flex-shrink: 0; }
.cal-day-post-item:hover .cal-day-post-arrow { color: #7c5cff; }

/* View Post modal - media, content, and one detail card per platform in
   the group (account identity + stats + status), so a post fanned out to
   several platforms shows every one of them, not just whichever was
   clicked in the calendar. */
.cal-view-media-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .5rem; margin-bottom: 1rem; }
.cal-view-media { width: 100%; max-height: 220px; object-fit: cover; border-radius: .65rem; }
.cal-view-content { white-space: pre-wrap; color: #1e1e2d; font-size: .9rem; line-height: 1.55; margin-bottom: 1.25rem; }

.cal-platform-detail-list { display: flex; flex-direction: column; gap: .75rem; }
.cal-platform-detail { border: 1.5px solid rgba(20,20,40,.07); border-radius: .85rem; padding: .9rem 1rem; }
.cal-platform-detail-header { display: flex; align-items: center; gap: .65rem; }
.cal-platform-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.cal-platform-avatar-fallback { display: flex; align-items: center; justify-content: center; font-size: 1rem; }
.cal-platform-detail-identity { flex: 1; min-width: 0; }
.cal-platform-detail-name { font-weight: 600; color: #1e1e2d; font-size: .88rem; display: flex; align-items: center; gap: .35rem; }
.cal-platform-detail-handle { font-size: .74rem; color: #8b8d9c; }
.cal-platform-stats { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: .75rem; font-size: .78rem; color: #8b8d9c; }
.cal-platform-stats span { display: inline-flex; align-items: center; gap: .3rem; }
.cal-platform-live-link { display: inline-flex; align-items: center; gap: .3rem; margin-top: .6rem; font-size: .78rem; color: #7c5cff; text-decoration: none; font-weight: 600; }
.cal-platform-live-link:hover { text-decoration: underline; }

.cal-status-badge {
    display: inline-block; padding: .25rem .65rem; border-radius: .4rem;
    font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
}
.cal-status-badge.success { background: var(--status-success-bg); color: var(--status-success-color); }
.cal-status-badge.info { background: var(--status-info-bg); color: var(--status-info-color); }
.cal-status-badge.warning { background: var(--status-warning-bg); color: var(--status-warning-color); }
.cal-status-badge.danger { background: var(--status-danger-bg); color: var(--status-danger-color); }
.cal-status-badge.muted { background: var(--status-muted-bg); color: var(--status-muted-color); }

/* =========================================================
   "Create post" modal - copied from the same design already used
   and working on posts.index (resources/js/components/posts/
   PostsDashboard.vue's #quickCreateModal), so the calendar's
   composer matches it exactly rather than inventing a new look.
   That component's CSS is Vue `scoped` (auto-namespaced at build
   time), so it's reproduced here verbatim as plain global CSS
   instead, since this page has no Vue/Vite component of its own.
========================================================= */
.quick-create-modal { border-radius: 18px; overflow: hidden; border: none; }
.composer-user-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.composer-avatar {
    width: 44px; height: 44px; border-radius: 50%; background: #5D87FF; color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;
}
.composer-audience { font-size: 13px; color: #7C8FAC; }
.quick-textarea { width: 100%; border: none; outline: none; resize: none; font-size: 18px; color: #2A3547; }
.quick-media-preview { position: relative; margin-top: 10px; border-radius: 12px; overflow: hidden; max-height: 260px; }
.quick-media-preview img, .quick-media-preview video { width: 100%; max-height: 260px; object-fit: cover; }
.remove-media-btn {
    position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; border-radius: 50%;
    border: none; background: rgba(0,0,0,.6); color: #fff; display: flex; align-items: center; justify-content: center;
}
.quick-platform-label { margin-top: 18px; margin-bottom: 10px; font-weight: 600; color: #2A3547; font-size: 14px; }
.quick-platform-select { display: flex; flex-wrap: wrap; gap: 10px; }
/* Account chips (avatar + small platform badge + real account name),
   not bare platform icons - picking "Facebook" should look like picking
   the actual connected Page, not an abstract network logo. */
.quick-account-chip {
    display: flex; align-items: center; gap: 8px; padding: 6px 14px 6px 6px; border-radius: 30px;
    border: 1px solid #E5E7EB; cursor: pointer; font-size: 13px; font-weight: 600; color: #2A3547; transition: .2s;
}
.quick-account-chip.active { background: #5D87FF; border-color: #5D87FF; color: #fff; }
.quick-account-avatar-wrap { position: relative; flex-shrink: 0; width: 30px; height: 30px; }
.quick-account-avatar {
    width: 30px; height: 30px; border-radius: 50%; object-fit: cover; display: block;
}
.quick-account-avatar-fallback { display: flex; align-items: center; justify-content: center; font-size: 14px; }
.quick-account-badge {
    position: absolute; bottom: -2px; right: -2px; width: 15px; height: 15px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 8px; color: #fff;
    border: 1.5px solid #fff;
}
.quick-account-name { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.quick-schedule-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; flex-wrap: wrap; }
.quick-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #2A3547; margin: 0; }
.add-to-post-row {
    margin-top: 18px; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 18px;
    display: flex; align-items: center; justify-content: space-between; font-weight: 600; font-size: 14px; color: #2A3547;
}
.add-to-post-icons { display: flex; gap: 16px; font-size: 20px; align-items: center; }
.media-upload-btn { cursor: pointer; display: flex; margin: 0; }
#calendarQuickPostModal .modern-select {
    border: 1px solid #E5E7EB; border-radius: 8px; padding: 8px 12px; font-size: 13px; color: #2A3547;
}
#calendarQuickPostModal .modal-footer .btn-primary {
    background: #5D87FF; border-color: #5D87FF; border-radius: 10px; padding: 12px; font-weight: 600;
}
#calendarQuickPostModal .modal-footer .btn-primary:disabled { opacity: .6; }
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

<script>
    // ------------------------------------------------------------------
    // CONTENT CALENDAR - click an empty day to quick-post for that date,
    // click a day with post(s) to preview them.
    // ------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var platformMeta = @json($platformMeta);
        var statusMeta = @json($statusMeta);
        var platformBrandColors = @json($platformBrandColors);

        var quickPostModalEl = document.getElementById('calendarQuickPostModal');
        var viewPostModalEl = document.getElementById('calendarViewPostModal');
        var dayPostsModalEl = document.getElementById('calendarDayPostsModal');
        if (!quickPostModalEl || typeof bootstrap === 'undefined') return;

        var quickPostModal = new bootstrap.Modal(quickPostModalEl);
        var viewPostModal = new bootstrap.Modal(viewPostModalEl);
        var dayPostsModal = new bootstrap.Modal(dayPostsModalEl);

        var dateLabelFormatter = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        document.querySelectorAll('.dash-calendar-day[data-calendar-date]').forEach(function (cell) {
            cell.addEventListener('click', function () {
                var date = cell.getAttribute('data-calendar-date');
                var posts = JSON.parse(cell.getAttribute('data-calendar-posts') || '[]');

                if (posts.length === 0) {
                    // Functional guard, independent of the disabled CSS
                    // class - a past day with nothing on it can't have a
                    // post created for it (no such thing as scheduling
                    // into the past), so this simply does nothing rather
                    // than opening the composer pre-filled with a date
                    // that would fail the "after now" validation anyway.
                    if (cell.getAttribute('data-calendar-past-empty') === '1') {
                        return;
                    }
                    openQuickPostModal(date);
                } else if (posts.length === 1) {
                    openViewPostModal(posts[0].id);
                } else {
                    openDayPostsModal(date, posts);
                }
            });
        });

        var quickPostSelectedPlatforms = [];
        var quickPostMediaFile = null;

        function openQuickPostModal(dateStr) {
            var date = new Date(dateStr + 'T00:00:00');
            document.getElementById('calendarQuickPostDateLabel').textContent = '· ' + dateLabelFormatter.format(date);
            document.getElementById('calendarQuickPostError').classList.add('d-none');

            var form = document.getElementById('calendarQuickPostForm');
            form.reset();
            quickPostSelectedPlatforms = [];
            quickPostMediaFile = null;
            document.querySelectorAll('#calendarQuickPostPlatforms .quick-account-chip').forEach(function (chip) {
                chip.classList.remove('active');
            });
            resetQuickMediaPreview();

            var now = new Date();
            var isToday = dateStr === now.toISOString().slice(0, 10);
            var hh = isToday ? String(Math.min(23, now.getHours() + 1)).padStart(2, '0') : '09';
            document.getElementById('calendarQuickPostScheduleToggle').checked = true;
            document.getElementById('calendarQuickPostScheduleAt').disabled = false;
            document.getElementById('calendarQuickPostScheduleAt').value = dateStr + 'T' + hh + ':00';
            updateQuickPostSubmitLabel();

            quickPostModal.show();
        }

        document.querySelectorAll('#calendarQuickPostPlatforms .quick-account-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var platform = chip.getAttribute('data-platform');
                var idx = quickPostSelectedPlatforms.indexOf(platform);
                if (idx === -1) {
                    quickPostSelectedPlatforms.push(platform);
                    chip.classList.add('active');
                } else {
                    quickPostSelectedPlatforms.splice(idx, 1);
                    chip.classList.remove('active');
                }
            });
        });

        function resetQuickMediaPreview() {
            var input = document.getElementById('calendarQuickPostMediaInput');
            if (input) input.value = '';
            document.getElementById('calendarQuickMediaPreview').classList.add('d-none');
            document.getElementById('calendarQuickMediaImg').classList.add('d-none');
            document.getElementById('calendarQuickMediaVideo').classList.add('d-none');
        }

        var quickMediaInputEl = document.getElementById('calendarQuickPostMediaInput');
        if (quickMediaInputEl) {
            quickMediaInputEl.addEventListener('change', function (e) {
                var file = e.target.files[0];
                if (!file) return;
                quickPostMediaFile = file;
                var url = URL.createObjectURL(file);
                var isVideo = file.type.startsWith('video');
                var img = document.getElementById('calendarQuickMediaImg');
                var video = document.getElementById('calendarQuickMediaVideo');
                img.classList.toggle('d-none', isVideo);
                video.classList.toggle('d-none', !isVideo);
                if (isVideo) { video.src = url; } else { img.src = url; }
                document.getElementById('calendarQuickMediaPreview').classList.remove('d-none');
            });
        }

        var quickMediaRemoveBtn = document.getElementById('calendarQuickMediaRemove');
        if (quickMediaRemoveBtn) {
            quickMediaRemoveBtn.addEventListener('click', function () {
                quickPostMediaFile = null;
                resetQuickMediaPreview();
            });
        }

        function updateQuickPostSubmitLabel() {
            var scheduling = document.getElementById('calendarQuickPostScheduleToggle').checked;
            document.getElementById('calendarQuickPostSubmitLabel').textContent = scheduling ? 'Schedule Post' : 'Post';
        }

        var scheduleToggleEl = document.getElementById('calendarQuickPostScheduleToggle');
        if (scheduleToggleEl) {
            scheduleToggleEl.addEventListener('change', function () {
                document.getElementById('calendarQuickPostScheduleAt').disabled = !this.checked;
                updateQuickPostSubmitLabel();
            });
        }

        var quickPostForm = document.getElementById('calendarQuickPostForm');
        if (quickPostForm) {
            quickPostForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var form = e.target;
                var errorEl = document.getElementById('calendarQuickPostError');
                errorEl.classList.add('d-none');

                var content = form.querySelector('textarea[name="content"]').value.trim();
                if (quickPostSelectedPlatforms.length === 0) {
                    errorEl.textContent = 'Select at least one platform to post to.';
                    errorEl.classList.remove('d-none');
                    return;
                }
                if (content === '' && !quickPostMediaFile) {
                    errorEl.textContent = 'Write something or add a photo/video before posting.';
                    errorEl.classList.remove('d-none');
                    return;
                }

                var submitBtn = document.getElementById('calendarQuickPostSubmit');
                submitBtn.disabled = true;
                document.getElementById('calendarQuickPostSubmitLabel').textContent = 'Posting...';

                var formData = new FormData();
                formData.append('content', content);
                quickPostSelectedPlatforms.forEach(function (p) { formData.append('platforms[]', p); });
                if (quickPostMediaFile) formData.append('media', quickPostMediaFile);
                if (document.getElementById('calendarQuickPostScheduleToggle').checked) {
                    formData.append('schedule_mode', '1');
                    formData.append('schedule_at', document.getElementById('calendarQuickPostScheduleAt').value);
                }

                fetch(@json(route('admin.posts.quick')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                    body: formData,
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        submitBtn.disabled = false;
                        if (!data.success) {
                            var msg = (data.errors && data.errors[0] && data.errors[0].message) || 'Failed to create the post.';
                            errorEl.textContent = msg;
                            errorEl.classList.remove('d-none');
                            updateQuickPostSubmitLabel();
                            return;
                        }
                        quickPostModal.hide();
                        window.location.reload();
                    })
                    .catch(function () {
                        submitBtn.disabled = false;
                        updateQuickPostSubmitLabel();
                        errorEl.textContent = 'Something went wrong. Please try again.';
                        errorEl.classList.remove('d-none');
                    });
            });
        }

        function openViewPostModal(postId) {
            var bodyEl = document.getElementById('calendarViewPostBody');
            bodyEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bx bx-loader-alt bx-spin fs-3"></i></div>';
            viewPostModal.show();

            fetch(@json(url('admin/posts')) + '/' + postId + '/quick-view', { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    if (!result.success) {
                        bodyEl.innerHTML = '<p class="text-danger mb-0">Could not load this post.</p>';
                        return;
                    }
                    renderViewPost(result.post);
                })
                .catch(function () {
                    bodyEl.innerHTML = '<p class="text-danger mb-0">Could not load this post.</p>';
                });
        }

        function renderViewPost(post) {
            var platforms = post.platforms && post.platforms.length ? post.platforms : [];

            var iconsHtml = platforms.map(function (pl) {
                var meta = platformMeta[pl.platform] || { icon: 'bx-globe' };
                var color = platformBrandColors[pl.platform] || '#7c5cff';
                return '<i class="bx ' + meta.icon + '" style="color:' + color + '"></i>';
            }).join(' ');

            document.getElementById('calendarViewPostPlatformIcon').innerHTML = iconsHtml;
            document.getElementById('calendarViewPostAccountName').textContent = platforms.length > 1
                ? platforms.length + ' platforms'
                : (platforms[0] ? platforms[0].account_name : 'Post');
            document.getElementById('calendarViewPostOpenLink').href = post.edit_url;

            var whenLabel = post.schedule_mode && post.schedule_at
                ? 'Scheduled for ' + dateLabelFormatter.format(new Date(post.schedule_at))
                : (post.published_at
                    ? 'Published ' + dateLabelFormatter.format(new Date(post.published_at))
                    : 'Created ' + dateLabelFormatter.format(new Date(post.created_at)));

            var mediaHtml = (post.media || []).map(function (m) {
                if (m.type === 'image' || m.type === 'gif') return '<img src="' + m.url + '" class="cal-view-media">';
                if (m.type === 'video') return '<video src="' + m.url + '" controls class="cal-view-media"></video>';
                return '';
            }).join('');
            var mediaWrapHtml = mediaHtml ? '<div class="cal-view-media-grid">' + mediaHtml + '</div>' : '';

            var platformCardsHtml = platforms.map(function (pl) {
                var meta = platformMeta[pl.platform] || { icon: 'bx-globe', label: pl.platform };
                var color = platformBrandColors[pl.platform] || '#7c5cff';
                var status = statusMeta[pl.status] || { label: pl.status, class: 'muted' };

                var avatarHtml = pl.account_avatar
                    ? '<img class="cal-platform-avatar" src="' + pl.account_avatar + '">'
                    : '<span class="cal-platform-avatar cal-platform-avatar-fallback" style="background:' + color + '1a;color:' + color + ';"><i class="bx ' + meta.icon + '"></i></span>';

                var errorHtml = pl.status === 'failed' && pl.error_message
                    ? '<div class="alert alert-danger small mt-2 mb-0 py-2">' + escapeHtml(pl.error_message) + '</div>'
                    : '';

                var linkHtml = pl.post_url
                    ? '<a href="' + pl.post_url + '" target="_blank" class="cal-platform-live-link"><i class="bx bx-link-external"></i> View live</a>'
                    : '';

                return (
                    '<div class="cal-platform-detail">' +
                        '<div class="cal-platform-detail-header">' +
                            avatarHtml +
                            '<div class="cal-platform-detail-identity">' +
                                '<div class="cal-platform-detail-name">' +
                                    escapeHtml(pl.account_name) +
                                    ' <i class="bx ' + meta.icon + '" style="color:' + color + '" title="' + meta.label + '"></i>' +
                                '</div>' +
                                (pl.account_username ? '<div class="cal-platform-detail-handle">@' + escapeHtml(pl.account_username) + '</div>' : '') +
                            '</div>' +
                            '<span class="cal-status-badge ' + status.class + '">' + status.label + '</span>' +
                        '</div>' +
                        '<div class="cal-platform-stats">' +
                            '<span><i class="bx bx-like"></i>' + pl.stats.likes + '</span>' +
                            '<span><i class="bx bx-comment"></i>' + pl.stats.comments + '</span>' +
                            '<span><i class="bx bx-share"></i>' + pl.stats.shares + '</span>' +
                            '<span><i class="bx bx-show"></i>' + pl.stats.views + '</span>' +
                            '<span><i class="bx bx-trending-up"></i>' + pl.stats.impressions + '</span>' +
                        '</div>' +
                        linkHtml +
                        errorHtml +
                    '</div>'
                );
            }).join('');

            document.getElementById('calendarViewPostBody').innerHTML =
                '<p class="text-muted small mb-2">' + whenLabel + '</p>' +
                mediaWrapHtml +
                '<p class="cal-view-content">' + escapeHtml(post.content || '') + '</p>' +
                '<div class="cal-platform-detail-list">' + platformCardsHtml + '</div>';
        }

        function openDayPostsModal(dateStr, posts) {
            var date = new Date(dateStr + 'T00:00:00');
            document.getElementById('calendarDayPostsDateLabel').textContent = dateLabelFormatter.format(date);
            document.getElementById('calendarDayPostsCount').textContent = posts.length + (posts.length === 1 ? ' post' : ' posts');
            var list = document.getElementById('calendarDayPostsList');
            list.innerHTML = '';
            posts.forEach(function (p) {
                var groupPlatforms = (p.platforms && p.platforms.length) ? p.platforms : [{ platform: p.platform, status: p.status }];
                var meta = platformMeta[p.platform] || { icon: 'bx-globe', label: p.platform };
                var status = statusMeta[p.status] || { label: p.status, class: 'muted' };
                var color = platformBrandColors[p.platform] || '#7c5cff';

                // One post fanned out to several platforms (quickStore()
                // grouping via group_id) shows a stacked icon per platform
                // instead of just the representative one, and its subtext
                // lists every platform name rather than only the first.
                var iconsHtml = groupPlatforms.length > 1
                    ? groupPlatforms.map(function (gp) {
                        var gpMeta = platformMeta[gp.platform] || { icon: 'bx-globe' };
                        var gpColor = platformBrandColors[gp.platform] || '#7c5cff';
                        return '<span class="cal-day-post-icon cal-day-post-icon-stacked" style="background:' + gpColor + '1a;">' +
                            '<i class="bx ' + gpMeta.icon + '" style="color:' + gpColor + '"></i>' +
                        '</span>';
                    }).join('')
                    : '<span class="cal-day-post-icon" style="background:' + color + '1a;">' +
                        '<i class="bx ' + meta.icon + '" style="color:' + color + '"></i>' +
                    '</span>';

                var platformLabels = groupPlatforms.map(function (gp) {
                    return (platformMeta[gp.platform] || { label: gp.platform }).label;
                }).join(' + ');

                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'cal-day-post-item';
                item.innerHTML =
                    '<span class="cal-day-post-icons">' + iconsHtml + '</span>' +
                    '<span class="cal-day-post-main">' +
                        '<span class="cal-day-post-content">' + escapeHtml(p.content || '(no text)') + '</span>' +
                        '<span class="cal-day-post-subtext">' +
                            (p.account_name ? escapeHtml(p.account_name) + ' · ' : '') + platformLabels +
                            (p.time ? ' · ' + p.time : '') +
                        '</span>' +
                    '</span>' +
                    '<span class="cal-status-badge ' + status.class + '">' + status.label + '</span>' +
                    '<i class="bx bx-chevron-right cal-day-post-arrow"></i>';
                item.addEventListener('click', function () {
                    dayPostsModal.hide();
                    openViewPostModal(p.id);
                });
                list.appendChild(item);
            });
            dayPostsModal.show();
        }
    });
</script>
@endpush
