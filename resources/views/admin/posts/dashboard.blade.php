@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    $platformMeta = [
        'facebook'  => ['icon' => 'bxl-facebook',  'class' => 'facebook',  'label' => 'Facebook',  'tag' => 'Page'],
        'instagram' => ['icon' => 'bxl-instagram', 'class' => 'instagram', 'label' => 'Instagram', 'tag' => 'Business'],
        'tiktok'    => ['icon' => 'bxl-tiktok',    'class' => 'tiktok',    'label' => 'TikTok',     'tag' => 'Business'],
        'x'         => ['icon' => 'bxl-x-logo',    'class' => 'twitter',   'label' => 'X',          'tag' => 'Profile'],
        'twitter'   => ['icon' => 'bxl-x-logo',    'class' => 'twitter',   'label' => 'X',          'tag' => 'Profile'],
        'linkedin'  => ['icon' => 'bxl-linkedin',  'class' => 'linkedin',  'label' => 'LinkedIn',   'tag' => 'Page'],
        'youtube'   => ['icon' => 'bxl-youtube',   'class' => 'youtube',   'label' => 'YouTube',    'tag' => 'Channel'],
        'google'    => ['icon' => 'bxl-google',    'class' => 'google',    'label' => 'Google',     'tag' => 'Business'],
        'pinterest' => ['icon' => 'bx-share-alt',  'class' => 'pinterest', 'label' => 'Pinterest',  'tag' => 'Profile'],
        'whatsapp'  => ['icon' => 'bxl-whatsapp',  'class' => 'whatsapp',  'label' => 'WhatsApp',   'tag' => 'Business'],
        'threads'   => ['icon' => 'bx-at',         'class' => 'threads',  'label' => 'Threads',    'tag' => 'Profile'],
    ];

    // Official brand palettes - the same values as brandOf() in
    // resources/js/data/mockPosts.js (posts listing/preview), kept in sync
    // here since this page has no access to that JS module. `color` is the
    // solid brand colour (text/tints, readable on white); `fill` the badge
    // background (a gradient where the brand's app icon uses one); `ink`
    // the glyph colour on the fill; `glow` an optional glyph filter.
    $platformBrand = [
        'facebook'  => ['color' => '#0866FF', 'fill' => 'linear-gradient(180deg, #18ACFE 0%, #0163E0 100%)', 'ink' => '#FFFFFF'],
        'instagram' => ['color' => '#E1306C', 'fill' => 'radial-gradient(circle at 30% 107%, #FDF497 0%, #FDF497 5%, #FD5949 45%, #D6249F 60%, #285AEB 90%)', 'ink' => '#FFFFFF'],
        'x'         => ['color' => '#000000', 'fill' => '#000000', 'ink' => '#FFFFFF'],
        'linkedin'  => ['color' => '#0A66C2', 'fill' => 'linear-gradient(180deg, #0A66C2 0%, #004182 100%)', 'ink' => '#FFFFFF'],
        'tiktok'    => ['color' => '#000000', 'fill' => '#000000', 'ink' => '#FFFFFF', 'glow' => 'drop-shadow(-1px -1px 0 #25F4EE) drop-shadow(1px 1px 0 #FE2C55)'],
        'youtube'   => ['color' => '#FF0000', 'fill' => 'linear-gradient(180deg, #FF3D3D 0%, #E60000 100%)', 'ink' => '#FFFFFF'],
        'threads'   => ['color' => '#000000', 'fill' => '#000000', 'ink' => '#FFFFFF'],
        'pinterest' => ['color' => '#E60023', 'fill' => 'linear-gradient(180deg, #F0002A 0%, #BD001C 100%)', 'ink' => '#FFFFFF'],
        'whatsapp'  => ['color' => '#25D366', 'fill' => 'linear-gradient(180deg, #5FFC7B 0%, #28D146 100%)', 'ink' => '#FFFFFF'],
        'snapchat'  => ['color' => '#E8C800', 'fill' => '#FFFC00', 'ink' => '#000000'],
        'google'    => ['color' => '#4285F4', 'fill' => '#4285F4', 'ink' => '#FFFFFF'],
    ];
    $platformBrand['twitter'] = $platformBrand['x'];
    $platformBrand = array_map(fn ($b) => $b + ['glow' => 'none'], $platformBrand);
    $platformBrandColors = array_map(fn ($b) => $b['color'], $platformBrand);
    $brandFallback = ['color' => '#7c5cff', 'fill' => '#7c5cff', 'ink' => '#FFFFFF', 'glow' => 'none'];
    // CSS custom properties for a platform-themed element (--pf solid,
    // --pf-fill background, --pf-ink glyph, --pf-glow glyph filter).
    $pfVars = function ($platform) use ($platformBrand, $brandFallback) {
        $b = $platformBrand[$platform] ?? $brandFallback;
        return "--pf: {$b['color']}; --pf-fill: {$b['fill']}; --pf-ink: {$b['ink']}; --pf-glow: {$b['glow']};";
    };
    // Inline style for a solid brand badge (icon on the platform's fill).
    $pfBadge = function ($platform) use ($platformBrand, $brandFallback) {
        $b = $platformBrand[$platform] ?? $brandFallback;
        return "background: {$b['fill']}; color: {$b['ink']}; --pf-glow: {$b['glow']};";
    };

    // dash_short() and dash_media_preview() live in app/Helpers/Helper.php
    // now (autoloaded project-wide, alongside adminSetting() etc.) instead
    // of being declared here - a view shouldn't be defining global
    // functions, and the new <x-post-media-thumb> component depends on
    // dash_media_preview() too.

    $statusMeta = [
        'published' => ['label' => 'Published', 'class' => 'success'],
        'completed' => ['label' => 'Published', 'class' => 'success'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'info'],
        'pending'   => ['label' => 'Pending',   'class' => 'warning'],
        'PROCESSING'=> ['label' => 'Pending',   'class' => 'warning'],
        'failed'    => ['label' => 'Failed',    'class' => 'danger'],
        'draft'     => ['label' => 'Draft',     'class' => 'muted'],
    ];

    // Strings for the JS-rendered post view modal.
    $cvmText = collect([
        'load_failed', 'you', 'reply', 'likes', 'comments', 'shares', 'views', 'impressions', 'reach', 'view_on',
        'no_comments', 'no_comments_hint', 'cancel', 'write_comment', 'send', 'platforms_count', 'post',
        'scheduled_for', 'published_on', 'created_on', 'text_only', 'no_caption', 'replying_to', 'send_failed',
    ])->mapWithKeys(fn ($key) => [$key => __('admin.dashboard_page.cvm_'.$key)]);

    $isCurrentMonth = $calendarMonth->isSameMonth(now());
    $prevCalMonth = $calendarMonth->copy()->subMonthNoOverflow()->format('Y-m');
    $nextCalMonth = $calendarMonth->copy()->addMonthNoOverflow()->format('Y-m');
@endphp

<div class="socialeaz-dash pd">

    <!-- Header -->
    <div class="dash-header d-flex flex-wrap align-items-start justify-content-between gap-4 mb-6">
        <div>
            <h4 class="dash-title mb-1">{{ __('admin.dashboard_page.welcome_back', ['name' => explode(' ', trim(auth()->user()->name ?? 'there'))[0]]) }} <span>👋</span></h4>
            <p class="dash-subtitle mb-0">{{ __('admin.dashboard_page.welcome_subtitle') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('admin.posts.dashboard') }}" class="pd-range">
                <i class="bx bx-calendar pd-range-icon"></i>
                <input type="text" id="dashboardDateRange" name="date_range" class="dash-input" style="max-width:210px;" placeholder="{{ __('admin.dashboard_page.select_date_range') }}" autocomplete="off" value="{{ $dateFrom && $dateTo ? $dateFrom->format('M j').' - '.$dateTo->format('M j, Y') : '' }}" />
                <input type="hidden" name="from" id="dashboardFromInput" value="{{ $dateFrom?->format('Y-m-d') }}" />
                <input type="hidden" name="to" id="dashboardToInput" value="{{ $dateTo?->format('Y-m-d') }}" />
                <button type="submit" class="pd-range-btn" title="{{ __('admin.dashboard_page.select_date_range') }}"><i class="bx bx-right-arrow-alt"></i></button>
                @if($dateFrom && $dateTo)
                <a href="{{ route('admin.posts.dashboard') }}" class="pd-range-btn" title="Clear"><i class="bx bx-x"></i></a>
                @endif
            </form>
            <a href="{{ route('admin.chats.dashboard') }}" class="dash-btn dash-btn-ghost dash-bell" title="{{ __('admin.dashboard_page.messages') }}">
                <i class="bx bx-bell"></i>
                @if($totalUnreadMessages > 0)
                <span class="dash-bell-badge">{{ $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages }}</span>
                @endif
            </a>
            <a href="{{ route('admin.posts.index') }}" class="dash-btn dash-btn-primary">
                <i class="bx bx-plus"></i> {{ __('admin.dashboard_page.view_all') }}
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
            <x-metric-card class="h-100" icon="bx-link-alt" tone="primary" :label="__('admin.dashboard_page.connected_accounts')" :value="$totalAccounts">
                <x-slot:valueExtra>
                    <div class="dash-mini-icons">
                        @foreach($accountsByPlatform->keys()->take(4) as $p)
                            @php $m = $platformMeta[$p] ?? null; @endphp
                            @if($m)
                            <x-platform-icon :icon="$m['icon']" :color="$platformBrandColors[$p] ?? '#7c5cff'" :fill="$platformBrand[$p]['fill'] ?? null" :ink="$platformBrand[$p]['ink'] ?? '#fff'" :glow="$platformBrand[$p]['glow'] ?? 'none'" />
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
            <x-metric-card class="h-100" icon="bx-group" tone="info" :label="__('admin.dashboard_page.total_followers')" :value="dash_short($totalFollowers)">
                <x-slot:foot>{{ __('admin.dashboard_page.across_all_platforms') }}</x-slot:foot>
            </x-metric-card>
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card class="h-100" icon="bx-heart" tone="warning" :label="__('admin.dashboard_page.engagement_rate')" :value="$engagementRate === null ? '—' : $engagementRate.'%'">
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
            <x-metric-card class="h-100" icon="bx-show" tone="success" :label="__('admin.dashboard_page.total_reach')" :value="dash_short($totalReach)">
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
                    <span class="pd-add-icon"><i class="bx bx-plus"></i></span>
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
                        <span class="dash-account-badge" style="{{ $pfBadge($acct['platform']) }}"><i class="bx {{ $meta['icon'] }}"></i></span>
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
            <div class="col-12 col-md-8 col-xl-10 pd-empty pd-empty-inline"><span class="pd-empty-icon"><i class="bx bx-link-alt"></i></span><span>{{ __('admin.dashboard_page.no_accounts_connected') }}</span></div>
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

    <!-- 5. Content Calendar - full width: a Monday-start month grid with
         per-day post cards, plus a Scheduled/Published panel and a
         selected-post detail card beside it. -->
    @php
        $calStatusColors = ['published' => '#16a34a', 'scheduled' => '#3b82f6', 'draft' => '#94a3b8', 'failed' => '#ef4444'];
        $calFilterPlatforms = $accountsByPlatform->keys()
            ->merge($calendarEntries->pluck('platforms')->flatten(1)->pluck('platform'))
            ->map(fn ($p) => $p === 'twitter' ? 'x' : $p)
            ->unique()
            ->filter(fn ($p) => isset($platformMeta[$p]))
            ->values();
        $calTodayKey = now()->format('Y-m-d');
        $calStatusLabels = collect($calStatusColors)->map(fn ($color, $status) => __('admin.dashboard_page.cal_status_'.$status));
        $calStatCards = [
            ['key' => 'total',      'icon' => 'bx-calendar',       'tone' => 'primary', 'label' => __('admin.dashboard_page.cal_total_posts')],
            ['key' => 'published',  'icon' => 'bx-calendar-check', 'tone' => 'success', 'label' => __('admin.dashboard_page.cal_published')],
            ['key' => 'scheduled',  'icon' => 'bx-calendar-event', 'tone' => 'warning', 'label' => __('admin.dashboard_page.cal_scheduled')],
            ['key' => 'engagement', 'icon' => 'bx-group',          'tone' => 'info',    'label' => __('admin.dashboard_page.cal_engagements')],
        ];
        // Renders one post card; shared by the grid cells and the side panel.
        $calPlatformIcons = function (array $entry, int $max) use ($platformMeta, $pfVars) {
            $platforms = collect($entry['platforms'])->pluck('platform')->unique()->values();
            $html = '';
            foreach ($platforms->take($max) as $p) {
                $m = $platformMeta[$p] ?? ['icon' => 'bx-globe', 'label' => ucfirst($p)];
                $html .= '<span class="cal-pf" style="'.$pfVars($p).'" title="'.e($m['label']).'"><i class="bx '.$m['icon'].'"></i></span>';
            }
            if ($platforms->count() > $max) {
                $html .= '<span class="cal-pf cal-pf-more">+'.($platforms->count() - $max).'</span>';
            }
            return $html;
        };
    @endphp
    <div class="row g-4 mb-6 cal-section">
        <div class="col-xl-9">
            <div class="dash-card cal-card">
                <!-- Header: title + month switcher -->
                <div class="cal-head">
                    <div>
                        <h5 class="cal-title">{{ __('admin.dashboard_page.content_calendar') }}</h5>
                        <p class="cal-subtitle">{{ __('admin.dashboard_page.cal_subtitle') }}</p>
                    </div>
                    <div class="cal-month-switch">
                        <a href="{{ route('admin.posts.dashboard', array_merge(request()->except('cal'), ['cal' => $prevCalMonth])) }}" class="cal-icon-btn" aria-label="Previous month"><i class="bx bx-chevron-left"></i></a>
                        <span class="cal-month-label">{{ $calendarMonth->translatedFormat('F Y') }}</span>
                        <a href="{{ route('admin.posts.dashboard', array_merge(request()->except('cal'), ['cal' => $nextCalMonth])) }}" class="cal-icon-btn" aria-label="Next month"><i class="bx bx-chevron-right"></i></a>
                        <a href="{{ route('admin.posts.dashboard', request()->except('cal')) }}" class="cal-icon-btn cal-today-btn" title="{{ __('admin.dashboard_page.cal_today') }}"><i class="bx bx-calendar"></i></a>
                    </div>
                </div>

                <!-- Platform filter + view switch -->
                <div class="cal-toolbar">
                    <div class="cal-filters" id="calPlatformFilters">
                        <button type="button" class="cal-filter is-active" data-cal-platform="all">{{ __('admin.dashboard_page.cal_all') }}</button>
                        @foreach($calFilterPlatforms as $p)
                            @php $m = $platformMeta[$p]; @endphp
                            <button type="button" class="cal-filter cal-filter-icon" data-cal-platform="{{ $p }}" title="{{ $m['label'] }}" style="{{ $pfVars($p) }}">
                                <i class="bx {{ $m['icon'] }}"></i>
                            </button>
                        @endforeach
                        <button type="button" class="cal-filter cal-filter-icon cal-filter-add" data-bs-toggle="modal" data-bs-target="#addAccountModal" title="{{ __('admin.dashboard_page.cal_add_platform') }}"><i class="bx bx-plus"></i></button>
                    </div>
                    <div class="cal-views" id="calViewSwitch">
                        <button type="button" class="is-active" data-cal-view="month">{{ __('admin.dashboard_page.cal_month') }}</button>
                        <button type="button" data-cal-view="week">{{ __('admin.dashboard_page.cal_week') }}</button>
                        <button type="button" data-cal-view="day">{{ __('admin.dashboard_page.cal_day') }}</button>
                    </div>
                </div>

                <!-- Month stats -->
                <div class="row g-3 cal-stats">
                    @foreach($calStatCards as $card)
                        @php $stat = $calendarStats[$card['key']]; @endphp
                        <div class="col-6 col-lg-3">
                            <div class="cal-stat">
                                <div class="cal-stat-top">
                                    <span class="cal-stat-icon {{ $card['tone'] }}"><i class="bx {{ $card['icon'] }}"></i></span>
                                    <span class="cal-stat-label">{{ $card['label'] }}</span>
                                </div>
                                <div class="cal-stat-value">
                                    {{ $card['key'] === 'engagement' ? dash_short($stat['value']) : $stat['value'] }}
                                    @if($stat['change'] !== null)
                                        <span class="cal-stat-change {{ $stat['change'] > 0 ? 'up' : ($stat['change'] < 0 ? 'down' : 'flat') }}">
                                            <i class="bx {{ $stat['change'] < 0 ? 'bx-down-arrow-alt' : ($stat['change'] > 0 ? 'bx-up-arrow-alt' : 'bx-minus') }}"></i>{{ abs($stat['change']) }}%
                                        </span>
                                    @endif
                                </div>
                                <div class="cal-stat-foot">{{ __('admin.dashboard_page.cal_vs_last_month') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Grid -->
                <div class="cal-grid-wrap">
                    <div class="cal-grid" id="calGrid" data-view="month">
                        <div class="cal-week cal-weekdays">
                            @foreach(['mon','tue','wed','thu','fri','sat','sun'] as $wd)
                                <div class="cal-weekday">{{ __('admin.dashboard_page.cal_'.$wd) }}</div>
                            @endforeach
                        </div>
                        @foreach(\Carbon\CarbonPeriod::create($calendarGridStart, '1 week', $calendarGridEnd) as $weekStart)
                            <div class="cal-week">
                                @for($i = 0; $i < 7; $i++)
                                    @php
                                        $cellDay = $weekStart->copy()->addDays($i);
                                        $cellKey = $cellDay->format('Y-m-d');
                                        $cellEntries = $calendarPostsByDate[$cellKey] ?? collect();
                                        $inMonth = $cellDay->isSameMonth($calendarMonth);
                                        $isPast = $cellKey < $calTodayKey;
                                    @endphp
                                    <div class="cal-cell {{ $inMonth ? '' : 'is-outside' }} {{ $cellKey === $calTodayKey ? 'is-today' : '' }} {{ $isPast ? 'is-past' : '' }} {{ $cellEntries->isNotEmpty() ? 'has-posts' : '' }}"
                                         data-calendar-date="{{ $cellKey }}"
                                         data-calendar-past="{{ $isPast ? '1' : '0' }}"
                                         title="{{ $cellEntries->isEmpty() ? ($isPast ? __('admin.dashboard_page.cal_past_day') : __('admin.dashboard_page.cal_create_for', ['date' => $cellDay->format('M j')])) : '' }}">
                                        <div class="cal-cell-head">
                                            <span class="cal-cell-num">{{ $cellDay->day }}</span>
                                            <span class="cal-cell-actions">
                                                @unless($isPast)
                                                    <button type="button" class="cal-cell-btn" data-cal-create title="{{ __('admin.dashboard_page.create_post') }}"><i class="bx bx-plus"></i></button>
                                                @endunless
                                                @if($cellEntries->isNotEmpty())
                                                    <button type="button" class="cal-cell-btn" data-cal-day-list title="{{ __('admin.dashboard_page.cal_all_posts') }}"><i class="bx bx-dots-horizontal-rounded"></i></button>
                                                @endif
                                            </span>
                                        </div>
                                        @foreach($cellEntries as $entry)
                                            <button type="button"
                                                    class="cal-entry cal-status-{{ $entry['status'] }} {{ $loop->index >= 2 ? 'is-overflow' : '' }}"
                                                    data-cal-entry="{{ $entry['id'] }}"
                                                    data-platforms="{{ collect($entry['platforms'])->pluck('platform')->map(fn ($p) => $p === 'twitter' ? 'x' : $p)->implode(' ') }}">
                                                <span class="cal-entry-main">
                                                    <span class="cal-thumb {{ $entry['is_video'] ? 'is-video' : '' }}" style="--pf: {{ $platformBrandColors[$entry['platform']] ?? '#7c5cff' }}">
                                                        @if($entry['thumb'])
                                                            <img src="{{ $entry['thumb'] }}" alt="" loading="lazy" onerror="this.remove()">
                                                        @else
                                                            <i class="bx {{ $platformMeta[$entry['platform']]['icon'] ?? 'bx-file' }}"></i>
                                                        @endif
                                                    </span>
                                                    <span class="cal-entry-text">
                                                        <span class="cal-entry-title">{{ $entry['title'] }}</span>
                                                        <span class="cal-entry-time"><i class="cal-dot" style="background: {{ $calStatusColors[$entry['status']] }}"></i>{{ $entry['time'] }}</span>
                                                    </span>
                                                </span>
                                                <span class="cal-pfs">{!! $calPlatformIcons($entry, 3) !!}</span>
                                            </button>
                                        @endforeach
                                        <button type="button" class="cal-more {{ $cellEntries->count() > 2 ? '' : 'd-none' }}" data-cal-day-list>
                                            +<span>{{ max(0, $cellEntries->count() - 2) }}</span> {{ __('admin.dashboard_page.cal_more') }}
                                        </button>
                                    </div>
                                @endfor
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Legend + export -->
                <div class="cal-foot">
                    <div class="cal-legend">
                        @foreach($calStatusColors as $status => $color)
                            <span><i class="cal-dot" style="background: {{ $color }}"></i>{{ __('admin.dashboard_page.cal_status_'.$status) }}</span>
                        @endforeach
                    </div>
                    <button type="button" class="cal-export" id="calExportBtn"><i class="bx bx-download"></i> {{ __('admin.dashboard_page.cal_export') }}</button>
                </div>
            </div>
        </div>

        <!-- Side panel: Scheduled / Published lists + selected post details -->
        <div class="col-xl-3">
            <div class="cal-side">
                <div class="dash-card cal-panel">
                    <div class="cal-tabs" role="tablist">
                        <button type="button" class="cal-tab is-active" data-cal-tab="scheduled">{{ __('admin.dashboard_page.cal_scheduled') }} <span class="cal-tab-count">{{ $calendarScheduled->count() }}</span></button>
                        <button type="button" class="cal-tab" data-cal-tab="published">{{ __('admin.dashboard_page.cal_published') }} <span class="cal-tab-count">{{ $calendarPublished->count() }}</span></button>
                    </div>
                    @foreach(['scheduled' => $calendarScheduled, 'published' => $calendarPublished] as $tab => $tabEntries)
                        <div class="cal-tab-pane {{ $tab === 'scheduled' ? '' : 'd-none' }}" data-cal-pane="{{ $tab }}">
                            <div class="cal-panel-head">
                                <strong>{{ trans_choice('admin.dashboard_page.cal_'.$tab.'_count', $tabEntries->count(), ['count' => $tabEntries->count()]) }}</strong>
                                <a href="{{ route('admin.posts.index') }}" class="dash-link">{{ __('admin.dashboard_page.view_all') }}</a>
                            </div>
                            <div class="cal-panel-list">
                                @forelse($tabEntries as $entry)
                                    <div class="cal-panel-item" data-cal-entry="{{ $entry['id'] }}" role="button" tabindex="0">
                                        <span class="cal-thumb cal-thumb-lg {{ $entry['is_video'] ? 'is-video' : '' }}" style="--pf: {{ $platformBrandColors[$entry['platform']] ?? '#7c5cff' }}">
                                            @if($entry['thumb'])
                                                <img src="{{ $entry['thumb'] }}" alt="" loading="lazy" onerror="this.remove()">
                                            @else
                                                <i class="bx {{ $platformMeta[$entry['platform']]['icon'] ?? 'bx-file' }}"></i>
                                            @endif
                                        </span>
                                        <span class="cal-panel-body">
                                            <span class="cal-panel-title">{{ $entry['title'] }}</span>
                                            <span class="cal-panel-when"><i class="bx bx-time-five"></i> {{ $entry['date_label'] }} &middot; {{ $entry['time'] }}</span>
                                            <span class="cal-pfs">{!! $calPlatformIcons($entry, 4) !!}</span>
                                        </span>
                                        <i class="bx bx-dots-horizontal-rounded cal-panel-more"></i>
                                    </div>
                                @empty
                                    <div class="pd-empty pd-empty-sm">
                                        <span class="pd-empty-icon"><i class="bx {{ $tab === 'scheduled' ? 'bx-calendar-plus' : 'bx-check-circle' }}"></i></span>
                                        <strong>{{ __('admin.dashboard_page.cal_no_'.$tab) }}</strong>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Filled in by JS from the selected calendar entry -->
                <div class="dash-card cal-panel cal-details d-none" id="calDetails">
                    <div class="cal-panel-head">
                        <strong>{{ __('admin.dashboard_page.cal_post_details') }}</strong>
                        <button type="button" class="cal-icon-btn cal-icon-btn-sm" id="calDetailsClose" aria-label="{{ __('admin.dashboard_page.close') }}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="cal-details-top">
                        <span class="cal-thumb cal-thumb-xl" id="calDetailsThumb"></span>
                        <div class="cal-details-meta">
                            <span class="cal-badge" id="calDetailsStatus"></span>
                            <span class="cal-details-title" id="calDetailsTitle"></span>
                            <span class="cal-panel-when" id="calDetailsWhen"></span>
                            <span class="cal-pfs" id="calDetailsPlatforms"></span>
                        </div>
                    </div>
                    <p class="cal-details-content" id="calDetailsContent"></p>
                    <button type="button" class="cal-read-more d-none" id="calDetailsReadMore">{{ __('admin.dashboard_page.cal_read_more') }}</button>
                    <div class="cal-details-actions">
                        <a href="#" class="cal-btn cal-btn-ghost" id="calDetailsEdit">{{ __('admin.dashboard_page.cal_edit') }}</a>
                        <button type="button" class="cal-btn cal-btn-primary" id="calDetailsView">{{ __('admin.dashboard_page.cal_view_details') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6/7. Production history (main column) and secondary "what's
         next" widgets (sidebar) -->
    <div class="row g-4">
        <!-- Main column -->
        <div class="col-lg-8">

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
                                <td><x-platform-icon :icon="$meta['icon']" :color="$platformBrandColors[$post->platform] ?? '#7c5cff'" :fill="$platformBrand[$post->platform]['fill'] ?? null" :ink="$platformBrand[$post->platform]['ink'] ?? '#fff'" :glow="$platformBrand[$post->platform]['glow'] ?? 'none'" size="xs" /></td>
                                <td>{{ dash_short($post->reach) }}</td>
                                <td>{{ dash_short($post->likes + $post->comments + $post->shares) }}</td>
                                <td>{{ $post->created_at->format('M j, Y') }}</td>
                                <td><span class="dash-badge dash-badge-{{ $sm['class'] }}">{{ $sm['label'] }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="6">
                                <div class="pd-empty">
                                    <span class="pd-empty-icon"><i class="bx bx-edit-alt"></i></span>
                                    <strong>{{ __('admin.dashboard_page.no_posts_yet') }}</strong>
                                    <a href="{{ route('admin.posts.composer') }}" class="dash-btn dash-btn-primary"><i class="bx bx-plus"></i> {{ __('admin.dashboard_page.create_post') }}</a>
                                </div>
                            </td></tr>
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
                            <span class="dash-list-badge" style="{{ $pfBadge($post->platform) }}">
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
                    <li class="pd-empty pd-empty-sm">
                        <span class="pd-empty-icon"><i class="bx bx-calendar-plus"></i></span>
                        <strong>{{ __('admin.dashboard_page.nothing_scheduled') }}</strong>
                        <a href="{{ route('admin.posts.index') }}" class="dash-link">{{ __('admin.dashboard_page.create_post') }} <i class="bx bx-right-arrow-alt"></i></a>
                    </li>
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
                    <div class="col-12 pd-empty pd-empty-sm">
                        <span class="pd-empty-icon"><i class="bx bx-trophy"></i></span>
                        <strong>{{ __('admin.dashboard_page.no_posts_yet') }}</strong>
                    </div>
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
                                    <span class="quick-account-badge" style="{{ $pfBadge($account->platform) }}">
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
     VIEW POST MODAL - opened from a calendar post; filled in by JS from
     admin.posts.quick-view: media + caption on the left, one tab per
     attached platform (stats, live link, comment threads, reply box)
     on the right.
========================================================= --}}
<div class="modal fade cvm" id="calendarViewPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content cvm-content">
            <div class="cvm-header">
                <div class="cvm-header-main">
                    <span class="cvm-header-icons" id="calendarViewPostPlatformIcon"></span>
                    <div class="min-w-0">
                        <h5 class="cvm-title" id="calendarViewPostAccountName"></h5>
                        <span class="cvm-when" id="calendarViewPostWhen"></span>
                    </div>
                </div>
                <div class="cvm-header-actions">
                    <a href="#" class="cvm-btn cvm-btn-primary" id="calendarViewPostOpenLink">
                        <i class="bx bx-link-external"></i> <span>{{ __('admin.dashboard_page.open_full_post') }}</span>
                    </a>
                    <button type="button" class="cvm-close" data-bs-dismiss="modal" aria-label="{{ __('admin.dashboard_page.close') }}"><i class="bx bx-x"></i></button>
                </div>
            </div>
            <div class="cvm-body" id="calendarViewPostBody">
                <div class="cvm-loading"><i class="bx bx-loader-alt bx-spin"></i></div>
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
        // Meta connects once in the Connection Hub (docs/connection-hub-design.md).
        ['key' => 'facebook',  'class' => 'facebook',  'icon' => 'bxl-facebook',  'label' => 'Facebook',  'url' => \App\Support\Connections\HubLink::for('facebook'), 'note' => \App\Support\Connections\HubLink::note()],
        ['key' => 'instagram', 'class' => 'instagram', 'icon' => 'bxl-instagram', 'label' => 'Instagram', 'url' => \App\Support\Connections\HubLink::for('instagram'), 'note' => \App\Support\Connections\HubLink::note()],
        ['key' => 'threads',   'class' => 'threads',   'icon' => 'bx-at',         'label' => 'Threads',   'url' => route('admin.post-accounts.threads.redirect')],
        ['key' => 'pinterest', 'class' => 'pinterest', 'icon' => 'bx-share-alt',  'label' => 'Pinterest', 'url' => route('admin.post-accounts.pinterest.redirect')],
        ['key' => 'x',         'class' => 'twitter',   'icon' => 'bxl-twitter',   'label' => 'X',         'url' => \App\Support\Connections\HubLink::for('x'), 'note' => \App\Support\Connections\HubLink::note()],
        ['key' => 'linkedin',  'class' => 'linkedin',  'icon' => 'bxl-linkedin',  'label' => 'LinkedIn',  'url' => \App\Support\Connections\HubLink::for('linkedin'), 'note' => \App\Support\Connections\HubLink::note()],
        ['key' => 'tiktok',    'class' => 'tiktok',    'icon' => 'bxl-tiktok',    'label' => 'TikTok',    'url' => route('admin.social-accounts.redirect', ['platform' => 'tiktok'])],
        ['key' => 'google',    'class' => 'google',    'icon' => 'bxl-google',    'label' => 'Google / YouTube', 'url' => \App\Support\Connections\HubLink::for('google'), 'note' => \App\Support\Connections\HubLink::note()],
        ['key' => 'whatsapp',  'class' => 'whatsapp',  'icon' => 'bxl-whatsapp',  'label' => 'WhatsApp',  'url' => \App\Support\Connections\HubLink::for('whatsapp'), 'note' => \App\Support\Connections\HubLink::note()],
        // Snapchat is deliberately NOT a real connect link - there's no
        // posting API to authorize (the OAuth flow on the Ads dashboard
        // only grants Marketing/Ads scopes, which can't publish organic
        // posts either). Creative Kit's "Share to Snapchat" button (see
        // the quick-post success handler below) needs no connected
        // account at all, so this tile just explains that instead of
        // starting an OAuth redirect that wouldn't actually enable
        // anything - the click handler lives further down this file.
        ['key' => 'snapchat',  'class' => 'snapchat',  'icon' => 'bxl-snapchat',  'label' => 'Snapchat',  'url' => '#', 'note' => 'No connection needed'],
    ];
@endphp
<x-social-connect-modal id="addAccountModal" :platforms="$postingConnectPlatforms" />
@endsection

@push('styles')
@include('layouts.partials.dash-styles')
<style>
.socialeaz-dash .dash-bell-badge {
    position: absolute; top: -5px; right: -5px; background: var(--dash-danger); color: #fff;
    font-size: .6rem; font-weight: 700; min-width: 16px; height: 16px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; padding: 0 3px;
}

.socialeaz-dash .dash-mini-icons { display: flex; gap: .25rem; }
.socialeaz-dash .dash-mini-icons .social-icon-mini { width: 22px; height: 22px; font-size: 11px; border-radius: 6px; }

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
.socialeaz-dash .dash-add-account-card {
    width: 100%; height: 100%; min-height: 140px; display: flex; flex-direction: column; align-items: center; justify-content: center;
    border: 1.5px dashed var(--dash-border); border-radius: .7rem; background: transparent;
    color: var(--dash-muted); text-decoration: none; gap: .4rem; cursor: pointer; font: inherit;
}
.socialeaz-dash .dash-add-account-card:hover { color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-add-account-card i { font-size: 1.5rem; }

.socialeaz-dash .dash-chip { background: var(--dash-card-hover); color: var(--dash-muted); font-size: .7rem; padding: .25rem .6rem; border-radius: 1rem; }

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

.socialeaz-dash .dash-top-post { background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: .75rem; height: 100%; position: relative; }
.socialeaz-dash .dash-top-post p { font-size: .78rem; color: var(--dash-text); }
.socialeaz-dash .dash-top-post small { color: var(--dash-muted); font-size: .7rem; }
.socialeaz-dash .dash-badge-best { position: absolute; right: .6rem; top: .6rem; z-index: 1; background: rgba(22,163,74,.9); color: #fff; padding: .15rem .5rem; border-radius: .3rem; font-size: .62rem; font-weight: 600; }

.socialeaz-dash .dash-table-title { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

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

/* =========================================================
   CALENDAR MODALS - deliberately NOT scoped under .socialeaz-dash.
   Bootstrap modals in this layout render outside that wrapper div,
   so every .socialeaz-dash .dash-* rule above never reached them -
   that's why the first version of these rendered as bare, unstyled
   Bootstrap defaults. Own class names, own rules, same look.
========================================================= */
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
/* ---- Post view modal (calendar) ---- */
.cvm .modal-dialog { max-width: 1080px; }
.cvm-content { border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 30px 80px rgba(16,24,40,.28); }
.cvm-header {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.5rem;
    border-bottom: 1px solid #eef0f5; background: linear-gradient(180deg, #faf9ff, #fff);
}
.cvm-header-main { display: flex; align-items: center; gap: .9rem; min-width: 0; }
.cvm-header-icons { display: inline-flex; }
.cvm-header-icons .cvm-pf + .cvm-pf { margin-inline-start: -8px; }
.cvm-pf {
    width: 34px; height: 34px; border-radius: 10px; display: inline-grid; place-items: center; font-size: 1.05rem;
    background: var(--pf-fill, var(--pf)); color: var(--pf-ink, #fff); border: 2px solid #fff; box-shadow: 0 2px 6px rgba(16,24,40,.15);
}
.cvm-title { margin: 0; color: #161b2b; font-weight: 700; font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cvm-when { color: #8a92a3; font-size: .78rem; }
.cvm-header-actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
.cvm-close {
    width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e7e9f0; background: #fff; color: #4b5263;
    display: grid; place-items: center; font-size: 1.25rem; transition: background .15s, color .15s;
}
.cvm-close:hover { background: #f2eeff; color: #6d4aff; }
.cvm-btn {
    display: inline-flex; align-items: center; gap: .4rem; height: 36px; padding: 0 1rem; border-radius: 10px;
    font-size: .8rem; font-weight: 600; text-decoration: none; border: none;
}
.cvm-btn-primary { background: linear-gradient(135deg, #6d4aff, #8f6bff); color: #fff; box-shadow: 0 4px 12px rgba(109,74,255,.3); }
.cvm-btn-primary:hover { color: #fff; opacity: .93; }

.cvm-body { padding: 0; }
.cvm-loading { display: grid; place-items: center; min-height: 360px; font-size: 2rem; color: #6d4aff; }
.cvm-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); min-height: 520px; }
.cvm-left { padding: 1.5rem; background: #fafbfd; border-inline-end: 1px solid #eef0f5; display: flex; flex-direction: column; gap: 1rem; }
.cvm-right { padding: 1.25rem 1.5rem 1.5rem; display: flex; flex-direction: column; min-width: 0; }

.cvm-media { border-radius: 16px; overflow: hidden; background: #0f1020; }
.cvm-media-stage { display: grid; place-items: center; aspect-ratio: 4 / 3; }
.cvm-media-stage img, .cvm-media-stage video { width: 100%; height: 100%; object-fit: contain; }
.cvm-media-thumbs { display: flex; gap: .5rem; padding: .6rem; background: #fff; overflow-x: auto; }
.cvm-media-thumb { width: 54px; height: 54px; flex-shrink: 0; padding: 0; border-radius: 10px; overflow: hidden; border: 2px solid transparent; background: #eef0f5; opacity: .7; transition: opacity .15s, border-color .15s; }
.cvm-media-thumb.is-active, .cvm-media-thumb:hover { opacity: 1; border-color: #6d4aff; }
.cvm-media-thumb img { width: 100%; height: 100%; object-fit: cover; }
.cvm-media-thumb-video { width: 100%; height: 100%; display: grid; place-items: center; background: #1e1e2d; color: #fff; font-size: 1.3rem; }
.cvm-media-none { background: linear-gradient(135deg, #f2eeff, #eaf2ff); color: #6d4aff; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; min-height: 160px; font-weight: 600; font-size: .82rem; }
.cvm-media-none i { font-size: 2rem; }
.cvm-caption { color: #1e2333; font-size: .88rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 180px; overflow-y: auto; }
.cvm-caption em { color: #8a92a3; }
.cvm-totals { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; margin-top: auto; }
.cvm-totals div { background: #fff; border: 1px solid #eef0f5; border-radius: 12px; padding: .65rem .5rem; text-align: center; display: flex; flex-direction: column; }
.cvm-totals strong { color: #161b2b; font-size: 1.05rem; }
.cvm-totals span { color: #8a92a3; font-size: .68rem; }

.cvm-tabs { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .9rem; margin-bottom: 1rem; border-bottom: 1px solid #eef0f5; }
.cvm-tab {
    position: relative; display: inline-flex; align-items: center; gap: .4rem; height: 36px; padding: 0 .8rem; flex-shrink: 0;
    border-radius: 10px; border: 1px solid #e7e9f0; background: #fff; color: #4b5263; font-size: .8rem; font-weight: 600;
    transition: border-color .15s, background .15s, color .15s;
}
.cvm-tab i { font-size: 1.05rem; color: var(--pf); }
.cvm-tab:hover { border-color: var(--pf); }
.cvm-tab.is-active { background: var(--pf-fill, var(--pf)); border-color: transparent; color: var(--pf-ink, #fff); box-shadow: 0 4px 12px color-mix(in srgb, var(--pf) 35%, transparent); }
.cvm-tab.is-active i { color: var(--pf-ink, #fff); filter: var(--pf-glow, none); }
.cvm-tab.is-active .cvm-tab-count { background: color-mix(in srgb, var(--pf-ink, #fff) 22%, transparent); color: var(--pf-ink, #fff); }
.cvm-tab-count { font-size: .66rem; padding: .05rem .4rem; border-radius: 6px; background: #eef0f5; color: #4b5263; }
.cvm-tab-dot { position: absolute; top: -3px; inset-inline-end: -3px; width: 9px; height: 9px; border-radius: 50%; border: 2px solid #fff; background: #94a3b8; }
.cvm-tab-dot.success { background: #16a34a; } .cvm-tab-dot.info { background: #3b82f6; }
.cvm-tab-dot.warning { background: #f59e0b; } .cvm-tab-dot.danger { background: #ef4444; }

.cvm-pane { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.cvm-account { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
.cvm-account-avatar { position: relative; flex-shrink: 0; }
.cvm-account-badge {
    position: absolute; bottom: -2px; inset-inline-end: -2px; width: 20px; height: 20px; border-radius: 50%;
    background: var(--pf-fill, var(--pf)); color: var(--pf-ink, #fff); display: grid; place-items: center; font-size: .7rem; border: 2px solid #fff;
}
/* TikTok's cyan/red glyph edge (and any other --pf-glow) on brand badges */
.cvm-pf i, .cvm-account-badge i, .socialeaz-dash .cal-pf i, .socialeaz-dash .social-icon-mini i,
.socialeaz-dash .dash-account-badge i, .socialeaz-dash .dash-list-badge i, .quick-account-badge i { filter: var(--pf-glow, none); }
.cvm-account-id { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.cvm-account-id strong { color: #161b2b; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cvm-account-id span { color: #8a92a3; font-size: .74rem; }
.cvm-error { display: flex; gap: .5rem; align-items: flex-start; background: #fdecec; color: #b42318; border-radius: 12px; padding: .65rem .8rem; font-size: .78rem; margin-bottom: 1rem; }
.cvm-error i { font-size: 1rem; margin-top: 1px; }
.cvm-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: .4rem; margin-bottom: .9rem; }
.cvm-stat { display: flex; flex-direction: column; align-items: center; gap: 1px; padding: .55rem .25rem; border-radius: 12px; background: color-mix(in srgb, var(--pf) 7%, #fff); }
.cvm-stat i { color: var(--pf); font-size: 1rem; }
.cvm-stat strong { color: #161b2b; font-size: .88rem; }
.cvm-stat span { color: #8a92a3; font-size: .62rem; }
.cvm-live-link { display: inline-flex; align-items: center; gap: .35rem; align-self: flex-start; font-size: .78rem; font-weight: 600; color: var(--pf); text-decoration: none; margin-bottom: .9rem; }
.cvm-live-link:hover { text-decoration: underline; color: var(--pf); }

.cvm-comments-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem; }
.cvm-comments-head strong { color: #161b2b; font-size: .9rem; }
.cvm-count { background: #f2eeff; color: #6d4aff; font-size: .68rem; font-weight: 700; padding: .1rem .45rem; border-radius: 6px; }
.cvm-comments { flex: 1; min-height: 160px; max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: .85rem; padding-inline-end: .25rem; }
.cvm-comment { display: flex; gap: .6rem; }
.cvm-comment-main { flex: 1; min-width: 0; }
.cvm-bubble { background: #f4f5f9; border-radius: 4px 14px 14px 14px; padding: .55rem .8rem; display: inline-block; max-width: 100%; }
.cvm-comment.is-own .cvm-bubble { background: #f2eeff; }
.cvm-comment-author { color: #161b2b; font-size: .78rem; font-weight: 700; }
.cvm-own-tag { font-size: .6rem; font-weight: 700; background: #6d4aff; color: #fff; padding: .05rem .35rem; border-radius: 4px; margin-inline-start: .2rem; }
.cvm-comment-text { color: #343a4c; font-size: .82rem; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
.cvm-comment-meta { display: flex; align-items: center; gap: .8rem; padding: .25rem .4rem 0; color: #8a92a3; font-size: .7rem; }
.cvm-comment-meta .bxs-heart { color: #ef4444; }
.cvm-replies { display: flex; flex-direction: column; gap: .6rem; margin-top: .6rem; padding-inline-start: .25rem; border-inline-start: 2px solid #eef0f5; padding-left: .75rem; }
.cvm-link { border: none; background: none; padding: 0; color: #6d4aff; font-weight: 600; font-size: .7rem; }
.cvm-link:hover { text-decoration: underline; }
.cvm-avatar {
    position: relative; width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
    display: grid; place-items: center; background: linear-gradient(135deg, #8f6bff, #6d4aff); color: #fff; font-size: .78rem; font-weight: 700;
}
.cvm-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.cvm-avatar-lg { width: 44px; height: 44px; font-size: 1rem; }
.cvm-comment.is-reply .cvm-avatar { width: 26px; height: 26px; font-size: .68rem; }

.cvm-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .35rem; text-align: center; color: #8a92a3; font-size: .78rem; padding: 2.5rem 1rem; }
.cvm-empty i { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; background: #f2eeff; color: #6d4aff; font-size: 1.4rem; margin-bottom: .2rem; }
.cvm-empty strong { color: #161b2b; font-size: .86rem; }
.cvm-empty-sm { padding: 1.5rem 1rem; flex: 1; }

.cvm-composer { margin-top: .9rem; padding-top: .9rem; border-top: 1px solid #eef0f5; }
.cvm-replying { display: flex; align-items: center; justify-content: space-between; font-size: .72rem; color: #4b5263; background: #f8f6ff; border-radius: 8px; padding: .3rem .6rem; margin-bottom: .5rem; }
.cvm-composer-row { display: flex; align-items: center; gap: .5rem; border: 1px solid #e7e9f0; border-radius: 14px; padding: 4px 4px 4px .9rem; background: #fff; transition: border-color .15s, box-shadow .15s; }
.cvm-composer-row:focus-within { border-color: #6d4aff; box-shadow: 0 0 0 3px rgba(109,74,255,.12); }
.cvm-input { flex: 1; border: none; outline: none; font-size: .84rem; color: #161b2b; background: transparent; min-width: 0; }
.cvm-send { width: 38px; height: 38px; border: none; border-radius: 10px; background: linear-gradient(135deg, #6d4aff, #8f6bff); color: #fff; display: grid; place-items: center; font-size: 1rem; flex-shrink: 0; }
.cvm-send:disabled { opacity: .6; }
[dir="rtl"] .cvm-bubble { border-radius: 14px 4px 14px 14px; }
[dir="rtl"] .cvm-replies { padding-left: 0; padding-right: .75rem; }

@media (max-width: 991.98px) {
    .cvm-grid { grid-template-columns: 1fr; }
    .cvm-left { border-inline-end: none; border-bottom: 1px solid #eef0f5; }
    .cvm-stats { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 575.98px) {
    .cvm-header { padding: 1rem; }
    .cvm-btn span { display: none; }
    .cvm-left, .cvm-right { padding: 1rem; }
    .cvm-totals { grid-template-columns: repeat(2, 1fr); }
}

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

/* =========================================================
   Premium pass - scoped to this page (.pd) so the shared
   dash-styles used by other dashboards are unchanged.
========================================================= */
.socialeaz-dash.pd { --pd-ln: #e7e9f0; --pd-ln-soft: #f1f3f7; --pd-ink: #161b2b; --pd-brand: #6d4aff; --pd-brand-2: #8f6bff; }
.pd .dash-title { font-size: 1.45rem; letter-spacing: -.01em; color: var(--pd-ink); }
.pd .dash-card { border: 1px solid var(--pd-ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px rgba(16,24,40,.03); }
.pd .dash-card-header { margin-bottom: 1rem; }
.pd .dash-card-header h6, .pd .dash-card > h6, .pd .dash-card .d-flex > h6 { color: var(--pd-ink); font-weight: 700; font-size: .95rem; }
.pd .dash-btn-primary { background: linear-gradient(135deg, var(--pd-brand), var(--pd-brand-2)); border: none; border-radius: 10px; height: 40px; padding: 0 16px; box-shadow: 0 4px 12px rgba(109,74,255,.25); }
.pd .dash-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(109,74,255,.35); }
.pd .dash-bell i { font-size: 1.2rem; }
.pd .dash-bell { padding: 0; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--pd-ln); background: #fff; display: inline-flex; align-items: center; justify-content: center; position: relative; }

/* Date range toolbar */
.pd .pd-range { display: flex; align-items: center; height: 40px; border: 1px solid var(--pd-ln); border-radius: 10px; background: #fff; padding-inline-start: 10px; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
.pd .pd-range:focus-within { border-color: var(--pd-brand); box-shadow: 0 0 0 3px rgba(109,74,255,.12); }
.pd .pd-range-icon { color: #8a92a3; font-size: 1.05rem; }
.pd .pd-range .dash-input { border: none !important; box-shadow: none !important; background: transparent !important; height: 38px; min-width: 190px; font-size: .84rem; }
.pd .pd-range-btn { height: 100%; width: 38px; border: none; border-inline-start: 1px solid var(--pd-ln); background: transparent; color: #545d70; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; text-decoration: none; }
[dir="rtl"] .pd .pd-range-btn .bx-right-arrow-alt { transform: scaleX(-1); }
.pd .pd-range-btn:hover { background: #f6f7fa; color: var(--pd-brand); }

/* KPI cards */
.pd .dash-stat { display: flex; flex-direction: column; padding: 1.15rem 1.25rem; transition: box-shadow .2s, transform .2s; }
.pd .dash-stat:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(16,24,40,.08); }
.pd .dash-stat-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.pd .dash-stat-label { font-weight: 600; color: #8a92a3; font-size: .8rem; }
.pd .dash-stat-value { font-size: 1.75rem; letter-spacing: -.02em; margin-top: .15rem; color: var(--pd-ink); }
.pd .dash-stat-foot { margin-top: auto; padding-top: .6rem; }
.pd .dash-stat-icon { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
.pd .dash-stat-icon.is-primary { background: #f2eeff; color: var(--pd-brand); }
.pd .dash-stat-icon.is-info { background: #eff8ff; color: #1570ef; }
.pd .dash-stat-icon.is-warning { background: #fef6ee; color: #e04f16; }
.pd .dash-stat-icon.is-success { background: #ecfdf3; color: #079455; }

/* Accounts */
.pd .dash-account-card { background: #fff; border: 1px solid var(--pd-ln); border-radius: 14px; transition: border-color .15s, box-shadow .15s, transform .15s; }
.pd .dash-account-card:hover { border-color: #d9d0ff; box-shadow: 0 10px 24px rgba(16,24,40,.07); transform: translateY(-2px); }
.pd .dash-account-badge { border-color: #fff; }
.pd .dash-add-account-card { border-radius: 14px; border-color: #d5d9e2; background: #fbfbfd; font-weight: 600; font-size: .84rem; }
.pd .pd-add-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: #f2eeff; color: var(--pd-brand); }
.pd .pd-add-icon i { font-size: 1.35rem; }
.pd .dash-add-account-card:hover { background: #fbfaff; }

/* Chips */
.pd .dash-chip { background: #f2eeff; color: #4f2fd6; font-weight: 600; font-size: .74rem; padding: .3rem .75rem; }

/* =========================================================
   CONTENT CALENDAR
========================================================= */
.pd .cal-card { padding: 1.5rem; }
.pd .cal-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.pd .cal-title { color: var(--pd-ink); font-weight: 700; font-size: 1.35rem; letter-spacing: -.01em; margin: 0 0 .2rem; }
.pd .cal-subtitle { color: #6b7385; font-size: .86rem; margin: 0; }
.pd .cal-month-switch { display: flex; align-items: center; gap: .25rem; border: 1px solid var(--pd-ln); border-radius: 12px; padding: 4px; background: #fff; }
.pd .cal-month-label { min-width: 132px; text-align: center; color: var(--pd-ink); font-weight: 600; font-size: .9rem; }
.pd .cal-icon-btn {
    width: 32px; height: 32px; border-radius: 8px; display: inline-grid; place-items: center; border: none; background: transparent;
    color: #4b5263; font-size: 1.15rem; text-decoration: none; transition: background .15s, color .15s;
}
.pd .cal-icon-btn:hover { background: #f2eeff; color: var(--pd-brand); }
.pd .cal-today-btn { border-left: 1px solid var(--pd-ln); border-radius: 0 8px 8px 0; font-size: 1rem; }
.pd .cal-icon-btn-sm { width: 26px; height: 26px; font-size: 1.05rem; }

.pd .cal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1.25rem; }
.pd .cal-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
.pd .cal-filter {
    height: 38px; min-width: 38px; padding: 0 .9rem; border-radius: 10px; border: 1px solid var(--pd-ln); background: #fff;
    color: #4b5263; font-size: .82rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;
    transition: border-color .15s, background .15s, box-shadow .15s, transform .15s;
}
.pd .cal-filter-icon { padding: 0; width: 42px; font-size: 1.3rem; color: var(--pf, #4b5263); }
.pd .cal-filter:hover { border-color: #cfc4ff; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16,24,40,.06); }
.pd .cal-filter.is-active { border-color: var(--pd-brand); background: #f2eeff; color: var(--pd-brand); box-shadow: 0 0 0 3px rgba(109,74,255,.1); }
.pd .cal-filter-icon.is-active { color: var(--pf); background: #fff; border-color: var(--pf); box-shadow: 0 0 0 3px color-mix(in srgb, var(--pf) 15%, transparent); }
.pd .cal-filter-icon i { filter: var(--pf-glow, none); }
/* Instagram's glyph in its own gradient (other brands read fine as solid --pf) */
.pd .cal-filter-icon[data-cal-platform="instagram"] i { background: var(--pf-fill); -webkit-background-clip: text; background-clip: text; color: transparent; }
.pd .cal-filter-add { color: #6b7385; border-style: dashed; font-size: 1.15rem; }
.pd .cal-views { display: inline-flex; padding: 4px; border-radius: 12px; border: 1px solid var(--pd-ln); background: #f8f9fc; }
.pd .cal-views button { border: none; background: transparent; color: #6b7385; font-size: .8rem; font-weight: 600; padding: .4rem .95rem; border-radius: 8px; transition: background .15s, color .15s; }
.pd .cal-views button.is-active { background: #fff; color: var(--pd-brand); box-shadow: 0 1px 3px rgba(16,24,40,.1); }

.pd .cal-stats { margin-bottom: 1.25rem; }
.pd .cal-stat { border: 1px solid var(--pd-ln); border-radius: 14px; padding: 1rem 1.1rem; background: #fff; height: 100%; transition: box-shadow .15s, transform .15s; }
.pd .cal-stat:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(16,24,40,.06); }
.pd .cal-stat-top { display: flex; align-items: center; gap: .65rem; margin-bottom: .65rem; }
.pd .cal-stat-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 1.15rem; flex-shrink: 0; }
.pd .cal-stat-icon.primary { background: #f2eeff; color: var(--pd-brand); }
.pd .cal-stat-icon.success { background: #e8f8ee; color: #16a34a; }
.pd .cal-stat-icon.warning { background: #f4efff; color: #8b5cf6; }
.pd .cal-stat-icon.info { background: #eaf2ff; color: #3b82f6; }
.pd .cal-stat-label { color: #4b5263; font-size: .82rem; font-weight: 500; }
.pd .cal-stat-value { display: flex; align-items: baseline; gap: .55rem; color: var(--pd-ink); font-size: 1.55rem; font-weight: 700; line-height: 1.1; }
.pd .cal-stat-change { display: inline-flex; align-items: center; font-size: .74rem; font-weight: 600; }
.pd .cal-stat-change i { font-size: .9rem; }
.pd .cal-stat-change.up { color: #16a34a; }
.pd .cal-stat-change.down { color: #ef4444; }
.pd .cal-stat-change.flat { color: #8a92a3; }
.pd .cal-stat-foot { color: #8a92a3; font-size: .72rem; margin-top: .35rem; }

.pd .cal-grid-wrap { border: 1px solid var(--pd-ln); border-radius: 14px; overflow-x: auto; }
.pd .cal-grid { min-width: 760px; }
.pd .cal-week { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
.pd .cal-week + .cal-week { border-top: 1px solid var(--pd-ln); }
.pd .cal-weekday { padding: .7rem .5rem; text-align: center; color: var(--pd-ink); font-size: .78rem; font-weight: 600; background: #fbfbfd; }
.pd .cal-cell {
    position: relative; min-height: 118px; padding: .5rem; display: flex; flex-direction: column; gap: .35rem;
    background: #fff; cursor: pointer; transition: background .15s, box-shadow .15s;
}
.pd .cal-cell + .cal-cell { border-left: 1px solid var(--pd-ln); }
.pd .cal-cell:hover { background: #fcfbff; }
.pd .cal-cell.is-outside { background: #fafbfc; }
.pd .cal-cell.is-outside .cal-cell-num { color: #b3b9c6; }
.pd .cal-cell.is-past:not(.has-posts) { cursor: default; }
.pd .cal-cell.is-past:not(.has-posts):hover { background: inherit; }
.pd .cal-cell.is-today { background: #f7f4ff; box-shadow: inset 0 0 0 1.5px #cdbfff; }
.pd .cal-grid[data-view="week"] .cal-cell.is-focus { box-shadow: inset 0 0 0 2px var(--pd-brand); }
.pd .cal-cell-head { display: flex; align-items: center; justify-content: space-between; min-height: 22px; }
.pd .cal-cell-num { font-size: .78rem; font-weight: 600; color: #4b5263; min-width: 22px; height: 22px; display: inline-grid; place-items: center; border-radius: 50%; }
.pd .cal-cell.is-today .cal-cell-num { background: var(--pd-brand); color: #fff; box-shadow: 0 3px 8px rgba(109,74,255,.35); }
.pd .cal-cell-actions { display: inline-flex; gap: 2px; }
.pd .cal-cell-btn {
    width: 22px; height: 22px; border: none; border-radius: 6px; background: transparent; color: #8a92a3;
    display: inline-grid; place-items: center; font-size: 1rem; opacity: 0; transition: opacity .15s, background .15s, color .15s;
}
.pd .cal-cell-btn[data-cal-day-list] { opacity: 1; }
.pd .cal-cell:hover .cal-cell-btn { opacity: 1; }
.pd .cal-cell-btn:hover { background: #f2eeff; color: var(--pd-brand); }

.pd .cal-entry {
    width: 100%; text-align: left; border: 1px solid transparent; background: transparent; border-radius: 10px;
    padding: .3rem; display: flex; flex-direction: column; gap: .3rem; transition: background .15s, border-color .15s, box-shadow .15s;
}
.pd .cal-entry:hover { background: #fff; border-color: #e3dcff; box-shadow: 0 6px 16px rgba(16,24,40,.08); }
.pd .cal-entry.is-selected { background: #fff; border-color: var(--pd-brand); box-shadow: 0 0 0 3px rgba(109,74,255,.12); }
.pd .cal-entry.is-overflow, .pd .cal-entry.is-filtered { display: none; }
.pd .cal-entry-main { display: flex; align-items: center; gap: .45rem; min-width: 0; }
.pd .cal-entry-text { display: flex; flex-direction: column; min-width: 0; }
.pd .cal-entry-title { color: var(--pd-ink); font-size: .7rem; font-weight: 600; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pd .cal-entry-time { display: inline-flex; align-items: center; gap: .25rem; color: #8a92a3; font-size: .64rem; }
.pd .cal-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.pd .cal-more { border: none; background: none; padding: 0 .3rem; text-align: left; color: var(--pd-brand); font-size: .7rem; font-weight: 600; }
.pd .cal-more:hover { text-decoration: underline; }

.pd .cal-thumb {
    position: relative; width: 30px; height: 30px; border-radius: 8px; overflow: hidden; flex-shrink: 0; display: grid; place-items: center;
    background: color-mix(in srgb, var(--pf, #7c5cff) 12%, #fff); color: var(--pf, #7c5cff); font-size: .95rem;
}
.pd .cal-thumb img { width: 100%; height: 100%; object-fit: cover; }
.pd .cal-thumb.is-video::after {
    content: "\25B6"; position: absolute; inset: 0; display: grid; place-items: center; color: #fff; font-size: .55rem; background: rgba(15,15,30,.3);
}
.pd .cal-thumb-lg { width: 56px; height: 56px; border-radius: 12px; font-size: 1.4rem; }
.pd .cal-thumb-xl { width: 64px; height: 64px; border-radius: 12px; font-size: 1.6rem; }

.pd .cal-pfs { display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.pd .cal-pf {
    width: 18px; height: 18px; border-radius: 5px; display: inline-grid; place-items: center;
    background: var(--pf-fill, var(--pf)); color: var(--pf-ink, #fff); font-size: .7rem;
}
.pd .cal-pf-more { background: #eef0f5; color: #6b7385; font-size: .6rem; font-weight: 700; width: auto; padding: 0 4px; }

.pd .cal-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1rem; }
.pd .cal-legend { display: flex; flex-wrap: wrap; gap: 1.25rem; color: #4b5263; font-size: .78rem; }
.pd .cal-legend span { display: inline-flex; align-items: center; gap: .4rem; }
.pd .cal-legend .cal-dot { width: 9px; height: 9px; }
.pd .cal-export {
    display: inline-flex; align-items: center; gap: .4rem; height: 36px; padding: 0 .95rem; border-radius: 10px;
    border: 1px solid var(--pd-ln); background: #fff; color: var(--pd-ink); font-size: .8rem; font-weight: 600; transition: border-color .15s, color .15s;
}
.pd .cal-export:hover { border-color: var(--pd-brand); color: var(--pd-brand); }

/* Week / Day views reuse the same grid, hiding what's out of focus */
.pd .cal-grid[data-view="week"] .cal-week:not(.cal-weekdays):not(.is-focus-week),
.pd .cal-grid[data-view="day"] .cal-week:not(.is-focus-week) { display: none; }
.pd .cal-grid[data-view="week"] .cal-cell,
.pd .cal-grid[data-view="day"] .cal-cell { min-height: 320px; }
.pd .cal-grid[data-view="week"] .cal-entry.is-overflow,
.pd .cal-grid[data-view="day"] .cal-entry.is-overflow { display: flex; }
.pd .cal-grid[data-view="week"] .cal-entry.is-filtered,
.pd .cal-grid[data-view="day"] .cal-entry.is-filtered { display: none; }
.pd .cal-grid[data-view="week"] .cal-more,
.pd .cal-grid[data-view="day"] .cal-more { display: none; }
.pd .cal-grid[data-view="day"] .is-focus-week { grid-template-columns: 1fr; }
.pd .cal-grid[data-view="day"] .cal-cell:not(.is-focus) { display: none; }
.pd .cal-grid[data-view="day"] .cal-cell { border-left: none; }
.pd .cal-grid[data-view="day"] .cal-entry { flex-direction: row; align-items: center; justify-content: space-between; padding: .6rem .75rem; border-color: var(--pd-ln); }
.pd .cal-grid[data-view="day"] .cal-entry-title { font-size: .85rem; }
.pd .cal-grid[data-view="day"] .cal-entry-time { font-size: .74rem; }
.pd .cal-grid[data-view="day"] .cal-thumb { width: 44px; height: 44px; }

/* Side panel */
.pd .cal-side { display: flex; flex-direction: column; gap: 1rem; height: 100%; }
.pd .cal-panel { padding: 1.1rem 1.25rem; }
.pd .cal-tabs { display: flex; gap: 1.5rem; border-bottom: 1px solid var(--pd-ln); margin: -.1rem 0 1rem; }
.pd .cal-tab {
    border: none; background: none; padding: .35rem .15rem .75rem; color: #4b5263; font-size: .9rem; font-weight: 600;
    position: relative; display: inline-flex; align-items: center; gap: .45rem;
}
.pd .cal-tab.is-active { color: var(--pd-brand); }
.pd .cal-tab.is-active::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; border-radius: 2px; background: var(--pd-brand); }
.pd .cal-tab-count { background: #eef0f5; color: #4b5263; font-size: .66rem; font-weight: 700; padding: .1rem .45rem; border-radius: 6px; }
.pd .cal-tab.is-active .cal-tab-count { background: #f2eeff; color: var(--pd-brand); }
.pd .cal-panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
.pd .cal-panel-head strong { color: var(--pd-ink); font-size: .86rem; }
.pd .cal-panel-list { display: flex; flex-direction: column; max-height: 520px; overflow-y: auto; margin: 0 -.5rem; }
.pd .cal-panel-item {
    display: flex; align-items: flex-start; gap: .8rem; padding: .65rem .5rem; border-radius: 12px; cursor: pointer;
    transition: background .15s;
}
.pd .cal-panel-item + .cal-panel-item { border-top: 1px solid var(--pd-ln-soft); }
.pd .cal-panel-item:hover, .pd .cal-panel-item.is-selected { background: #f8f6ff; }
.pd .cal-panel-body { display: flex; flex-direction: column; gap: .3rem; min-width: 0; flex: 1; }
.pd .cal-panel-title { color: var(--pd-ink); font-size: .84rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pd .cal-panel-when { display: inline-flex; align-items: center; gap: .3rem; color: #8a92a3; font-size: .72rem; }
.pd .cal-panel-more { color: #8a92a3; font-size: 1.15rem; }

.pd .cal-details-top { display: flex; gap: .85rem; margin-bottom: .75rem; }
.pd .cal-details-meta { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; min-width: 0; }
.pd .cal-details-title { color: var(--pd-ink); font-weight: 700; font-size: .92rem; overflow-wrap: anywhere; }
.pd .cal-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .66rem; font-weight: 700; padding: .15rem .5rem; border-radius: 6px; }
.pd .cal-badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pd .cal-badge.published { background: #e8f8ee; color: #16a34a; }
.pd .cal-badge.scheduled { background: #eaf2ff; color: #3b82f6; }
.pd .cal-badge.draft { background: #f1f3f7; color: #64748b; }
.pd .cal-badge.failed { background: #fdecec; color: #ef4444; }
.pd .cal-details-content { color: #4b5263; font-size: .8rem; line-height: 1.5; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
.pd .cal-details-content.is-expanded { -webkit-line-clamp: unset; }
.pd .cal-read-more { border: none; background: none; padding: 0; color: var(--pd-brand); font-size: .78rem; font-weight: 600; margin-top: .3rem; }
.pd .cal-details-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-top: 1rem; }
.pd .cal-details-actions .cal-btn { justify-content: center; }

[dir="rtl"] .pd .cal-cell + .cal-cell { border-left: none; border-right: 1px solid var(--pd-ln); }
[dir="rtl"] .pd .cal-entry, [dir="rtl"] .pd .cal-more { text-align: right; }
[dir="rtl"] .pd .cal-month-switch .bx-chevron-left, [dir="rtl"] .pd .cal-month-switch .bx-chevron-right { transform: scaleX(-1); }
[dir="rtl"] .pd .cal-today-btn { border-left: none; border-right: 1px solid var(--pd-ln); border-radius: 8px 0 0 8px; }

@media (max-width: 575.98px) {
    .pd .cal-card { padding: 1rem; }
    .pd .cal-month-label { min-width: 110px; }
}

/* Summary / lists / table */
.pd .dash-summary-icon { width: 34px; height: 34px; border-radius: 10px; }
.pd .dash-summary-list li { padding: .7rem 0; }
.pd .dash-table th { background: #fbfbfd; padding-top: .65rem; padding-bottom: .65rem; }
.pd .dash-table tbody tr:hover td { background: #fafaff; }
.pd .dash-top-post { background: #fff; border-radius: 12px; }
.pd .dash-inbox-tile { border-radius: 12px; transition: border-color .15s, box-shadow .15s, transform .15s; }
.pd a.dash-inbox-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,24,40,.07); }

/* Empty states */
.pd .pd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; padding: 28px 16px; list-style: none; }
.pd .pd-empty strong { color: var(--pd-ink); font-size: .86rem; font-weight: 600; }
.pd .pd-empty-sm { padding: 18px 8px 10px; border: none !important; }
.pd .pd-empty-inline { flex-direction: row; justify-content: flex-start; padding: 0 8px; min-height: 140px; color: #8a92a3; font-size: .85rem; }
.pd .pd-empty-icon { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 22px; background: #f2eeff; color: var(--pd-brand); }
.pd .pd-empty .dash-link { display: inline-flex; align-items: center; gap: 2px; font-weight: 600; }
[dir="rtl"] .pd .pd-empty .dash-link i { transform: scaleX(-1); }

@media (max-width: 575.98px) {
    .pd .pd-range .dash-input { min-width: 0; }
}
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
        var platformBrand = @json($platformBrand);
        // Same as the Blade $pfVars() helper: --pf/--pf-fill/--pf-ink/--pf-glow.
        function pfVars(platform) {
            var b = platformBrand[platform] || { color: '#7c5cff', fill: '#7c5cff', ink: '#fff', glow: 'none' };
            return '--pf:' + b.color + ';--pf-fill:' + b.fill + ';--pf-ink:' + b.ink + ';--pf-glow:' + b.glow + ';';
        }
        var shareUrlBase = @json(url('share/posts'));

        // quickStore()'s response is keyed by platform, each an array of
        // that platform's own Post row - any one of them points at the
        // same content/media for the public share-preview page (see
        // PostController::sharePreview()).
        function quickPostSnapchatShareUrl(results) {
            if (!results) return null;
            for (var platform in results) {
                var rows = results[platform];
                if (Array.isArray(rows) && rows.length && rows[0].id) {
                    return shareUrlBase + '/' + rows[0].id;
                }
            }
            return null;
        }

        // The Snapchat tile in the Add Account modal (#addAccountModal,
        // see the $postingConnectPlatforms array below) is a 'url' => '#'
        // placeholder, not a real OAuth link - there's no posting API to
        // connect to. Explain that instead of letting the '#' click do
        // nothing silently.
        var snapchatInfoTile = document.querySelector('.social-card-link-snapchat');
        if (snapchatInfoTile) {
            snapchatInfoTile.addEventListener('click', function (e) {
                e.preventDefault();
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'info',
                        title: 'Snapchat',
                        html: 'Snapchat has no API for connecting an account to auto-publish posts.' +
                            '<br><br>Publish a post here as usual, then use the <strong>Share to Snapchat</strong> ' +
                            'button that appears afterward to send it manually - no connection needed.'
                    });
                }
            });
        }

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

        // ---- Content calendar interactions ----
        var calEntries = @json($calendarEntries->keyBy('id'));
        var calStatusLabels = @json($calStatusLabels);
        var calGrid = document.getElementById('calGrid');
        var calDetails = document.getElementById('calDetails');
        var calPlatformFilter = 'all';
        var calSelectedId = null;
        var CAL_CELL_LIMIT = 2;

        function calEntryPlatforms(entry) {
            return (entry.platforms || []).map(function (p) { return p.platform === 'twitter' ? 'x' : p.platform; });
        }
        function calEntryVisible(entry) {
            return calPlatformFilter === 'all' || calEntryPlatforms(entry).indexOf(calPlatformFilter) !== -1;
        }
        function calEntriesForDate(date) {
            return Object.values(calEntries).filter(function (e) { return e.date === date && calEntryVisible(e); });
        }
        function calPlatformIconsHtml(entry) {
            return calEntryPlatforms(entry).filter(function (p, i, all) { return all.indexOf(p) === i; }).map(function (p) {
                var meta = platformMeta[p] || { icon: 'bx-globe', label: p };
                return '<span class="cal-pf" style="' + pfVars(p) + '" title="' + escapeHtml(meta.label) + '"><i class="bx ' + meta.icon + '"></i></span>';
            }).join('');
        }

        // Narrow screens stack the side panel under the grid, so selecting a
        // post there opens the full view modal instead of a panel offscreen.
        function calHasSidePanel() { return window.matchMedia('(min-width: 1200px)').matches; }

        function calSelect(id) {
            var entry = calEntries[id];
            if (!entry || !calDetails) return;
            calSelectedId = id;
            document.querySelectorAll('[data-cal-entry]').forEach(function (el) {
                el.classList.toggle('is-selected', el.getAttribute('data-cal-entry') == id);
            });
            var thumb = document.getElementById('calDetailsThumb');
            var meta = platformMeta[entry.platform] || { icon: 'bx-file' };
            thumb.style.setProperty('--pf', platformBrandColors[entry.platform] || '#7c5cff');
            thumb.classList.toggle('is-video', !!entry.is_video);
            thumb.innerHTML = entry.thumb
                ? '<img src="' + escapeHtml(entry.thumb) + '" alt="" onerror="this.remove()">'
                : '<i class="bx ' + meta.icon + '"></i>';
            var status = document.getElementById('calDetailsStatus');
            status.className = 'cal-badge ' + entry.status;
            status.textContent = calStatusLabels[entry.status] || entry.status;
            document.getElementById('calDetailsTitle').textContent = entry.title;
            document.getElementById('calDetailsWhen').innerHTML = '<i class="bx bx-time-five"></i> ' + escapeHtml(entry.date_label + ' · ' + entry.time);
            document.getElementById('calDetailsPlatforms').innerHTML = calPlatformIconsHtml(entry);
            var content = document.getElementById('calDetailsContent');
            content.textContent = entry.content || '';
            content.classList.remove('is-expanded');
            var readMore = document.getElementById('calDetailsReadMore');
            readMore.classList.toggle('d-none', (entry.content || '').length < 90);
            document.getElementById('calDetailsEdit').href = entry.show_url;
            calDetails.classList.remove('d-none');
        }

        function calOpenEntry(id) {
            if (calHasSidePanel()) calSelect(id); else openViewPostModal(id);
        }

        // Calendar posts open straight into the full view modal; the side
        // panel's details card follows along so it stays in sync.
        function calViewEntry(id) {
            if (calHasSidePanel()) calSelect(id);
            openViewPostModal(id);
        }

        function calSetFocus(cell) {
            if (!cell || !calGrid) return;
            calGrid.querySelectorAll('.cal-cell.is-focus').forEach(function (c) { c.classList.remove('is-focus'); });
            calGrid.querySelectorAll('.cal-week.is-focus-week').forEach(function (w) { w.classList.remove('is-focus-week'); });
            cell.classList.add('is-focus');
            cell.parentElement.classList.add('is-focus-week');
        }

        // Re-applies the platform filter and the per-cell entry cap, keeping
        // each cell's "+N more" count in step with what's actually hidden.
        function calApplyFilter() {
            if (!calGrid) return;
            calGrid.querySelectorAll('.cal-cell').forEach(function (cell) {
                var shown = 0, hidden = 0;
                cell.querySelectorAll('.cal-entry').forEach(function (el) {
                    var visible = calEntryVisible(calEntries[el.getAttribute('data-cal-entry')] || {});
                    el.classList.toggle('is-filtered', !visible);
                    el.classList.remove('is-overflow');
                    if (!visible) return;
                    if (shown >= CAL_CELL_LIMIT) { el.classList.add('is-overflow'); hidden++; } else { shown++; }
                });
                var more = cell.querySelector('.cal-more');
                if (more) {
                    more.querySelector('span').textContent = hidden;
                    more.classList.toggle('d-none', hidden === 0);
                }
                cell.classList.toggle('has-posts', shown + hidden > 0);
            });
            document.querySelectorAll('.cal-panel-item[data-cal-entry]').forEach(function (el) {
                el.classList.toggle('d-none', !calEntryVisible(calEntries[el.getAttribute('data-cal-entry')] || {}));
            });
        }

        if (calGrid) {
            // Initial focus (for Week/Day views): today if it's on the grid,
            // otherwise the 1st of the displayed month.
            calSetFocus(calGrid.querySelector('.cal-cell.is-today') || calGrid.querySelector('.cal-cell:not(.is-outside)'));

            calGrid.addEventListener('click', function (e) {
                var cell = e.target.closest('.cal-cell');
                if (!cell) return;
                var date = cell.getAttribute('data-calendar-date');
                var isPast = cell.getAttribute('data-calendar-past') === '1';
                calSetFocus(cell);

                var entryEl = e.target.closest('.cal-entry');
                if (entryEl) { calViewEntry(entryEl.getAttribute('data-cal-entry')); return; }
                if (e.target.closest('[data-cal-create]')) { openQuickPostModal(date); return; }

                var posts = calEntriesForDate(date);
                if (e.target.closest('[data-cal-day-list]') || posts.length > 0) {
                    if (posts.length === 1) { calViewEntry(posts[0].id); return; }
                    if (posts.length > 1) { openDayPostsModal(date, posts); return; }
                }
                // A past day with nothing on it can't have a post created for
                // it (no such thing as scheduling into the past).
                if (!isPast) openQuickPostModal(date);
            });
        }

        document.querySelectorAll('.cal-panel-item[data-cal-entry]').forEach(function (item) {
            var open = function () { calOpenEntry(item.getAttribute('data-cal-entry')); };
            item.addEventListener('click', open);
            item.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
        });

        document.getElementById('calDetailsClose')?.addEventListener('click', function () {
            calDetails.classList.add('d-none');
            calSelectedId = null;
            document.querySelectorAll('[data-cal-entry].is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
        });
        document.getElementById('calDetailsView')?.addEventListener('click', function () {
            if (calSelectedId) openViewPostModal(calSelectedId);
        });
        document.getElementById('calDetailsReadMore')?.addEventListener('click', function () {
            var content = document.getElementById('calDetailsContent');
            var expanded = content.classList.toggle('is-expanded');
            this.textContent = expanded ? @json(__('admin.dashboard_page.cal_show_less')) : @json(__('admin.dashboard_page.cal_read_more'));
        });

        document.querySelectorAll('#calPlatformFilters [data-cal-platform]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                calPlatformFilter = btn.getAttribute('data-cal-platform');
                document.querySelectorAll('#calPlatformFilters [data-cal-platform]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                calApplyFilter();
            });
        });

        document.querySelectorAll('#calViewSwitch [data-cal-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                calGrid.setAttribute('data-view', btn.getAttribute('data-cal-view'));
                document.querySelectorAll('#calViewSwitch [data-cal-view]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            });
        });

        document.querySelectorAll('.cal-tab[data-cal-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-cal-tab');
                document.querySelectorAll('.cal-tab[data-cal-tab]').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                document.querySelectorAll('.cal-tab-pane[data-cal-pane]').forEach(function (p) { p.classList.toggle('d-none', p.getAttribute('data-cal-pane') !== name); });
            });
        });

        // Pre-select the next scheduled post (or latest published) so the
        // details card isn't empty on load.
        var calFirstItem = document.querySelector('.cal-panel-item[data-cal-entry]');
        if (calFirstItem && calHasSidePanel()) calSelect(calFirstItem.getAttribute('data-cal-entry'));

        // Export the visible (platform-filtered) entries as an .ics file.
        document.getElementById('calExportBtn')?.addEventListener('click', function () {
            function icsDate(iso) { return new Date(iso).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); }
            function icsText(str) { return String(str || '').replace(/\\/g, '\\\\').replace(/\r?\n/g, '\\n').replace(/([,;])/g, '\\$1'); }
            var lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//SocialEaz//Content Calendar//EN', 'CALSCALE:GREGORIAN'];
            Object.values(calEntries).filter(calEntryVisible).forEach(function (e) {
                var start = icsDate(e.datetime);
                var end = icsDate(new Date(new Date(e.datetime).getTime() + 30 * 60000).toISOString());
                var platforms = calEntryPlatforms(e).map(function (p) { return (platformMeta[p] || { label: p }).label; }).join(', ');
                lines.push('BEGIN:VEVENT', 'UID:post-' + e.id + '@socialeaz', 'DTSTAMP:' + icsDate(new Date().toISOString()),
                    'DTSTART:' + start, 'DTEND:' + end, 'SUMMARY:' + icsText(e.title + ' (' + platforms + ')'),
                    'DESCRIPTION:' + icsText((calStatusLabels[e.status] || e.status) + ' - ' + (e.content || '')), 'URL:' + e.show_url, 'END:VEVENT');
            });
            lines.push('END:VCALENDAR');
            var blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'content-calendar-{{ $calendarMonth->format('Y-m') }}.ics';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
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

                        var shareUrl = quickPostSnapchatShareUrl(data.results);

                        // Snapchat has no auto-publish API (Ads only, plus a
                        // read-only Public Profile API - see
                        // developers.snap.com/marketing-api/Public-Profile-API) -
                        // Creative Kit's client-side share button is the
                        // legitimate way to get this post onto Snapchat, but it
                        // needs the user to actually click it, so the reload
                        // that used to happen immediately here now waits for
                        // the modal to close instead of racing it away.
                        if (shareUrl) {
                            document.querySelector('#calendarQuickPostModal .modal-body').innerHTML =
                                '<div class="text-center py-4">' +
                                    '<i class="bx bx-check-circle text-success" style="font-size:40px;"></i>' +
                                    '<p class="mt-2 mb-3">Post published successfully!</p>' +
                                    '<p class="text-muted small mb-3">Snapchat has no auto-publish API - share this post manually instead:</p>' +
                                    '<a href="#" class="btn btn-outline-dark btn-sm snapchat-share-button" data-share-url="' + shareUrl + '">' +
                                        '<i class="bx bxl-snapchat"></i> Share to Snapchat' +
                                    '</a>' +
                                    '<button type="button" class="btn btn-primary btn-sm ms-2" data-bs-dismiss="modal">Done</button>' +
                                '</div>';
                            document.querySelector('#calendarQuickPostModal .modal-footer')?.classList.add('d-none');

                            if (window.snap && window.snap.creativekit) {
                                window.snap.creativekit.initalizeShareButtons(
                                    document.getElementsByClassName('snapchat-share-button')
                                );
                            }

                            quickPostModalEl.addEventListener('hidden.bs.modal', function () {
                                window.location.reload();
                            }, { once: true });
                        } else {
                            quickPostModal.hide();
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        submitBtn.disabled = false;
                        updateQuickPostSubmitLabel();
                        errorEl.textContent = 'Something went wrong. Please try again.';
                        errorEl.classList.remove('d-none');
                    });
            });
        }

        var cvmText = @json($cvmText);
        var cvmLoadingHtml = '<div class="cvm-loading"><i class="bx bx-loader-alt bx-spin"></i></div>';
        var cvmReplyUrl = '';
        var cvmPreviewUrl = @json(route('admin.posts.preview', ['post' => '__POST__', 'platform' => '__PLATFORM__']));

        // "Open full post" goes to that platform's preview page; it follows
        // whichever platform tab is open in the modal.
        function cvmSetOpenLink(pl) {
            if (!pl) return;
            document.getElementById('calendarViewPostOpenLink').href = cvmPreviewUrl
                .replace('__POST__', pl.post_id)
                .replace('__PLATFORM__', pl.platform);
        }

        function openViewPostModal(postId) {
            var bodyEl = document.getElementById('calendarViewPostBody');
            bodyEl.innerHTML = cvmLoadingHtml;
            document.getElementById('calendarViewPostPlatformIcon').innerHTML = '';
            document.getElementById('calendarViewPostAccountName').textContent = '';
            document.getElementById('calendarViewPostWhen').textContent = '';
            viewPostModal.show();

            fetch(@json(route('admin.posts.quick-view', '__POST__')).replace('__POST__', postId), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    if (!result.success) {
                        bodyEl.innerHTML = '<div class="cvm-empty"><i class="bx bx-error-circle"></i><strong>' + cvmText.load_failed + '</strong></div>';
                        return;
                    }
                    renderViewPost(result.post);
                })
                .catch(function () {
                    bodyEl.innerHTML = '<div class="cvm-empty"><i class="bx bx-error-circle"></i><strong>' + cvmText.load_failed + '</strong></div>';
                });
        }

        function cvmNumber(n) {
            n = Number(n) || 0;
            if (n >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
            if (n >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
            return String(n);
        }

        function cvmAvatar(name, url, extraClass) {
            var initial = escapeHtml((name || '?').trim().charAt(0).toUpperCase());
            return '<span class="cvm-avatar ' + (extraClass || '') + '">' +
                (url ? '<img src="' + escapeHtml(url) + '" alt="" onerror="this.remove()">' : '') +
                '<span>' + initial + '</span></span>';
        }

        function cvmCommentHtml(c, isReply) {
            var repliesHtml = (c.replies || []).map(function (r) { return cvmCommentHtml(r, true); }).join('');
            return '<div class="cvm-comment' + (isReply ? ' is-reply' : '') + (c.isOwn ? ' is-own' : '') + '">' +
                cvmAvatar(c.author, c.avatar) +
                '<div class="cvm-comment-main">' +
                    '<div class="cvm-bubble">' +
                        '<div class="cvm-comment-author">' + escapeHtml(c.author) + (c.isOwn ? ' <span class="cvm-own-tag">' + cvmText.you + '</span>' : '') + '</div>' +
                        '<div class="cvm-comment-text">' + escapeHtml(c.content) + '</div>' +
                    '</div>' +
                    '<div class="cvm-comment-meta">' +
                        '<span>' + escapeHtml(c.timeAgo || '') + '</span>' +
                        (c.likes ? '<span><i class="bx bxs-heart"></i> ' + cvmNumber(c.likes) + '</span>' : '') +
                        (!isReply && c.id ? '<button type="button" class="cvm-link" data-cvm-reply="' + c.id + '">' + cvmText.reply + '</button>' : '') +
                    '</div>' +
                    (repliesHtml ? '<div class="cvm-replies">' + repliesHtml + '</div>' : '') +
                '</div>' +
            '</div>';
        }

        function cvmPlatformPaneHtml(pl, index) {
            var meta = platformMeta[pl.platform] || { icon: 'bx-globe', label: pl.platform };
            var color = platformBrandColors[pl.platform] || '#7c5cff';
            var status = statusMeta[pl.status] || { label: pl.status, class: 'muted' };
            var comments = pl.comments || [];
            var stats = [
                ['bx-heart', pl.stats.likes, cvmText.likes],
                ['bx-message-rounded', pl.stats.comments, cvmText.comments],
                ['bx-share', pl.stats.shares, cvmText.shares],
                ['bx-show', pl.stats.views, cvmText.views],
                ['bx-trending-up', pl.stats.impressions, cvmText.impressions],
                ['bx-broadcast', pl.stats.reach, cvmText.reach]
            ];

            return '<div class="cvm-pane' + (index === 0 ? '' : ' d-none') + '" data-cvm-pane="' + index + '" style="' + pfVars(pl.platform) + '">' +
                '<div class="cvm-account">' +
                    '<span class="cvm-account-avatar">' + cvmAvatar(pl.account_name, pl.account_avatar, 'cvm-avatar-lg') +
                        '<span class="cvm-account-badge"><i class="bx ' + meta.icon + '"></i></span></span>' +
                    '<div class="cvm-account-id">' +
                        '<strong>' + escapeHtml(pl.account_name) + '</strong>' +
                        '<span>' + (pl.account_username ? '@' + escapeHtml(pl.account_username) + ' · ' : '') + escapeHtml(meta.label) + '</span>' +
                    '</div>' +
                    '<span class="cal-status-badge ' + status.class + '">' + escapeHtml(status.label) + '</span>' +
                '</div>' +
                (pl.status === 'failed' && pl.error_message
                    ? '<div class="cvm-error"><i class="bx bx-error-circle"></i><span>' + escapeHtml(pl.error_message) + '</span></div>' : '') +
                '<div class="cvm-stats">' + stats.map(function (s) {
                    return '<div class="cvm-stat"><i class="bx ' + s[0] + '"></i><strong>' + cvmNumber(s[1]) + '</strong><span>' + s[2] + '</span></div>';
                }).join('') + '</div>' +
                (pl.post_url ? '<a href="' + escapeHtml(pl.post_url) + '" target="_blank" rel="noopener" class="cvm-live-link"><i class="bx ' + meta.icon + '"></i> ' + cvmText.view_on.replace(':platform', escapeHtml(meta.label)) + ' <i class="bx bx-link-external"></i></a>' : '') +
                '<div class="cvm-comments-head"><strong>' + cvmText.comments + '</strong><span class="cvm-count">' + comments.length + '</span></div>' +
                '<div class="cvm-comments" data-cvm-comments>' +
                    (comments.length
                        ? comments.map(function (c) { return cvmCommentHtml(c, false); }).join('')
                        : '<div class="cvm-empty cvm-empty-sm" data-cvm-empty><i class="bx bx-message-rounded-dots"></i><strong>' + cvmText.no_comments + '</strong><span>' + cvmText.no_comments_hint + '</span></div>') +
                '</div>' +
                '<form class="cvm-composer" data-cvm-composer data-comment-url="' + escapeHtml(pl.comment_url) + '">' +
                    '<div class="cvm-replying d-none" data-cvm-replying><span></span><button type="button" class="cvm-link" data-cvm-cancel-reply>' + cvmText.cancel + '</button></div>' +
                    '<div class="cvm-composer-row">' +
                        '<input type="text" class="cvm-input" maxlength="2000" placeholder="' + escapeHtml(cvmText.write_comment) + '" required>' +
                        '<button type="submit" class="cvm-send" aria-label="' + escapeHtml(cvmText.send) + '"><i class="bx bxs-send"></i></button>' +
                    '</div>' +
                '</form>' +
            '</div>';
        }

        function renderViewPost(post) {
            var platforms = post.platforms && post.platforms.length ? post.platforms : [];
            cvmReplyUrl = post.reply_url || '';

            document.getElementById('calendarViewPostPlatformIcon').innerHTML = platforms.map(function (pl) {
                var meta = platformMeta[pl.platform] || { icon: 'bx-globe' };
                return '<span class="cvm-pf" style="' + pfVars(pl.platform) + '"><i class="bx ' + meta.icon + '"></i></span>';
            }).join('');
            document.getElementById('calendarViewPostAccountName').textContent = platforms.length > 1
                ? cvmText.platforms_count.replace(':count', platforms.length)
                : (platforms[0] ? platforms[0].account_name : cvmText.post);
            cvmSetOpenLink(platforms[0]);
            document.getElementById('calendarViewPostWhen').textContent = post.schedule_mode && post.schedule_at
                ? cvmText.scheduled_for + ' ' + dateLabelFormatter.format(new Date(post.schedule_at))
                : (post.published_at
                    ? cvmText.published_on + ' ' + dateLabelFormatter.format(new Date(post.published_at))
                    : cvmText.created_on + ' ' + dateLabelFormatter.format(new Date(post.created_at)));

            var media = (post.media || []).filter(function (m) { return ['image', 'gif', 'video'].indexOf(m.type) !== -1; });
            var mediaEl = function (m) {
                return m.type === 'video'
                    ? '<video src="' + escapeHtml(m.url) + '" controls playsinline></video>'
                    : '<img src="' + escapeHtml(m.url) + '" alt="">';
            };
            var mediaHtml = media.length
                ? '<div class="cvm-media"><div class="cvm-media-stage" data-cvm-stage>' + mediaEl(media[0]) + '</div>' +
                    (media.length > 1 ? '<div class="cvm-media-thumbs">' + media.map(function (m, i) {
                        return '<button type="button" class="cvm-media-thumb' + (i === 0 ? ' is-active' : '') + '" data-cvm-media="' + i + '">' +
                            (m.type === 'video' ? '<span class="cvm-media-thumb-video"><i class="bx bx-play"></i></span>' : '<img src="' + escapeHtml(m.url) + '" alt="">') +
                        '</button>';
                    }).join('') + '</div>' : '') +
                  '</div>'
                : '<div class="cvm-media cvm-media-none"><i class="bx bx-text"></i><span>' + cvmText.text_only + '</span></div>';

            var totals = platforms.reduce(function (t, pl) {
                t.likes += pl.stats.likes; t.comments += pl.stats.comments; t.shares += pl.stats.shares; t.reach += pl.stats.reach;
                return t;
            }, { likes: 0, comments: 0, shares: 0, reach: 0 });

            var tabsHtml = platforms.length > 1
                ? '<div class="cvm-tabs" role="tablist">' + platforms.map(function (pl, i) {
                    var meta = platformMeta[pl.platform] || { icon: 'bx-globe', label: pl.platform };
                    var status = statusMeta[pl.status] || { class: 'muted' };
                    return '<button type="button" class="cvm-tab' + (i === 0 ? ' is-active' : '') + '" data-cvm-tab="' + i + '" style="' + pfVars(pl.platform) + '">' +
                        '<i class="bx ' + meta.icon + '"></i><span>' + escapeHtml(meta.label) + '</span>' +
                        '<span class="cvm-tab-count">' + (pl.comments || []).length + '</span>' +
                        '<span class="cvm-tab-dot ' + status.class + '"></span>' +
                    '</button>';
                }).join('') + '</div>'
                : '';

            var bodyEl = document.getElementById('calendarViewPostBody');
            bodyEl.innerHTML =
                '<div class="cvm-grid">' +
                    '<div class="cvm-left">' +
                        mediaHtml +
                        '<div class="cvm-caption">' + (post.content ? escapeHtml(post.content) : '<em>' + cvmText.no_caption + '</em>') + '</div>' +
                        '<div class="cvm-totals">' +
                            '<div><strong>' + cvmNumber(totals.likes) + '</strong><span>' + cvmText.likes + '</span></div>' +
                            '<div><strong>' + cvmNumber(totals.comments) + '</strong><span>' + cvmText.comments + '</span></div>' +
                            '<div><strong>' + cvmNumber(totals.shares) + '</strong><span>' + cvmText.shares + '</span></div>' +
                            '<div><strong>' + cvmNumber(totals.reach) + '</strong><span>' + cvmText.reach + '</span></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="cvm-right">' + tabsHtml + platforms.map(cvmPlatformPaneHtml).join('') + '</div>' +
                '</div>';

            bodyEl.querySelectorAll('[data-cvm-media]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    bodyEl.querySelector('[data-cvm-stage]').innerHTML = mediaEl(media[btn.getAttribute('data-cvm-media')]);
                    bodyEl.querySelectorAll('[data-cvm-media]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                });
            });
            bodyEl.querySelectorAll('[data-cvm-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var i = tab.getAttribute('data-cvm-tab');
                    cvmSetOpenLink(platforms[i]);
                    bodyEl.querySelectorAll('[data-cvm-tab]').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
                    bodyEl.querySelectorAll('[data-cvm-pane]').forEach(function (p) { p.classList.toggle('d-none', p.getAttribute('data-cvm-pane') !== i); });
                });
            });
            bodyEl.querySelectorAll('[data-cvm-pane]').forEach(cvmBindPane);
        }

        // Comment / reply composer for one platform pane. Replies go through
        // posts.comments.reply (published live on the platform where
        // supported); new top-level comments through posts.comments.store.
        function cvmBindPane(pane) {
            var form = pane.querySelector('[data-cvm-composer]');
            var input = form.querySelector('.cvm-input');
            var replyingEl = form.querySelector('[data-cvm-replying]');
            var replyTo = null;

            function setReplyTarget(commentEl, id) {
                replyTo = id;
                replyingEl.classList.toggle('d-none', !id);
                if (id) {
                    var author = commentEl.querySelector('.cvm-comment-author').firstChild.textContent;
                    replyingEl.querySelector('span').textContent = cvmText.replying_to.replace(':name', author);
                    input.focus();
                }
            }

            pane.addEventListener('click', function (e) {
                var replyBtn = e.target.closest('[data-cvm-reply]');
                if (replyBtn) setReplyTarget(replyBtn.closest('.cvm-comment'), replyBtn.getAttribute('data-cvm-reply'));
                if (e.target.closest('[data-cvm-cancel-reply]')) setReplyTarget(null, null);
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var content = input.value.trim();
                if (!content) return;
                var sendBtn = form.querySelector('.cvm-send');
                sendBtn.disabled = true;
                var url = replyTo ? cvmReplyUrl.replace('__COMMENT__', replyTo) : form.getAttribute('data-comment-url');

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content: content })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (!data.success) throw new Error(data.message || cvmText.send_failed);
                        var list = pane.querySelector('[data-cvm-comments]');
                        pane.querySelector('[data-cvm-empty]')?.remove();
                        if (replyTo) {
                            var parent = pane.querySelector('[data-cvm-reply="' + replyTo + '"]').closest('.cvm-comment-main');
                            var replies = parent.querySelector('.cvm-replies');
                            if (!replies) { replies = document.createElement('div'); replies.className = 'cvm-replies'; parent.appendChild(replies); }
                            replies.insertAdjacentHTML('beforeend', cvmCommentHtml(data.reply, true));
                        } else {
                            list.insertAdjacentHTML('beforeend', cvmCommentHtml(data.comment, false));
                            var count = pane.querySelector('.cvm-count');
                            count.textContent = Number(count.textContent) + 1;
                        }
                        list.scrollTop = list.scrollHeight;
                        input.value = '';
                        setReplyTarget(null, null);
                    })
                    .catch(function (err) {
                        if (typeof toastr !== 'undefined') toastr.error(err.message || cvmText.send_failed); else alert(err.message || cvmText.send_failed);
                    })
                    .finally(function () { sendBtn.disabled = false; });
            });
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
                        '<span class="cal-day-post-content">' + escapeHtml(p.title || p.content || '(no text)') + '</span>' +
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
