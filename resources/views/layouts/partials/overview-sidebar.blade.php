@php
    $team = auth()->user()->isTeamMember();
    // EnsureConnectedAccount sends these to the Connection Hub until an account
    // is connected. Only a hint, so it never breaks the page (rescue → no lock).
    $connectionLocked = auth()->user()->hasRole('seller') && rescue(fn () => ! auth()->user()->hasEligibleConnection(), false);
    $connectionGated = ['admin.posts.dashboard', 'admin.posts.composer', 'admin.posts.index', 'admin.chats.dashboard', 'admin.comments.dashboard'];
    $platforms = [
        'facebook' => ['Facebook', 'bxl-facebook-circle'],
        'instagram' => ['Instagram', 'bxl-instagram'],
        'tiktok' => ['TikTok', 'bxl-tiktok'],
        'x' => ['X', 'bxl-x-logo'],
        'snapchat' => ['Snapchat', 'bxl-snapchat'],
        'google' => ['Google', 'bxl-google'],
        'youtube' => ['YouTube', 'bxl-youtube'],
        'linkedin' => ['LinkedIn', 'bxl-linkedin-square'],
    ];
    $children = [
        'admin.posts.dashboard' => [
            ['admin.posts.dashboard', 'admin.sidebar.overview', 'bx-grid-alt', []],
            ['admin.posts.composer', 'overview.create_post', 'bx-plus', []],
            ['admin.posts.index', 'overview.all_posts', 'bx-list-ul', []],
        ],
        'admin.ads.dashboard' => [['admin.ads.dashboard', 'admin.sidebar.overview', 'bx-grid-alt', []]],
        'admin.ai-copilot.analytics.index' => [
            ['admin.ai-copilot.analytics.index', 'admin.sidebar.analytics', 'bx-bar-chart-alt-2', []],
            ['admin.ai-copilot.settings.index', 'admin.sidebar.copilot_settings', 'bx-cog', []],
            ['admin.ai-copilot.business-profile.edit', 'admin.sidebar.business_profile', 'bx-buildings', []],
            ['admin.knowledge-base.index', 'admin.sidebar.knowledge_base', 'bx-book-open', []],
            ['admin.ai-copilot.knowledge-gaps.index', 'admin.sidebar.knowledge_gaps', 'bx-help-circle', []],
        ],
        'admin.knowledge-base.index' => [
            ['admin.knowledge-base.index', 'admin.sidebar.knowledge_base', 'bx-book-open', []],
            ['admin.ai-copilot.knowledge-gaps.index', 'admin.sidebar.knowledge_gaps', 'bx-help-circle', []],
        ],
        // Google Suite - Analytics & Insights (GoogleSuiteController), one collapsible group.
        'admin.google.overview' => [
            ['admin.google.overview', 'google_suite.nav.overview', 'bx-pie-chart-alt-2', []],
            ['admin.google.analytics', 'google_suite.nav.analytics', 'bx-line-chart', []],
            ['admin.google.search-console', 'google_suite.nav.search_console', 'bx-search-alt', []],
            ['admin.google.advertising', 'google_suite.nav.google_ads', 'bxl-google', []],
            ['admin.google.website-performance', 'google_suite.nav.pagespeed', 'bx-tachometer', []],
            ['admin.google.integrations', 'google_suite.nav.integrations', 'bx-extension', []],
            ['admin.google.reports', 'google_suite.nav.reports', 'bx-file', []],
            ['admin.google.preferences', 'google_suite.nav.settings', 'bx-slider-alt', []],
        ],
        'employees.index' => [['employees.index', 'overview.sidebar_employees', 'bx-user', []], ['employees.create', 'overview.add_employee', 'bx-user-plus', []]],
    ];
    // Tints for the Google Suite links (overview-sidebar.css, [data-service]), matching the suite's own colours.
    $googleTones = [
        'admin.google.overview' => 'google', 'admin.google.analytics' => 'ga4', 'admin.google.search-console' => 'search-console',
        'admin.google.advertising' => 'google-ads', 'admin.google.website-performance' => 'pagespeed', 'admin.google.integrations' => 'integrations',
        'admin.google.reports' => 'reports', 'admin.google.preferences' => 'settings',
    ];
    // Google screens without a sidebar link of their own.
    $childAliases = [
        'admin.google.integrations' => ['admin.google.setup', 'admin.google.status', 'admin.google.properties', 'admin.google.business-profile', 'admin.google.tag-manager'],
        'admin.google.preferences' => ['admin.google.health', 'admin.google.settings'],
    ];
    if (!$team) {
        foreach ($platforms as $platform => [$platformLabel, $platformIcon]) {
            $children['admin.posts.dashboard'][] = ['admin.posts.index', $platformLabel, $platformIcon, ['platform' => $platform]];
            $children['admin.ads.dashboard'][] = ['admin.ads.campaigns.index', $platformLabel, $platformIcon, ['platform' => $platform]];
        }
        // OpenAI (ChatGPT Ads) is an ads-only platform, so it isn't in $platforms (which also feeds Posts).
        $children['admin.ads.dashboard'][] = ['admin.ads.campaigns.index', 'ChatGPT Ads', 'bxs-bot', ['platform' => 'openai']];
    }
    $groups = $team ? [
        'overview.navigation' => [
            ['dashboard', 'admin.dashboard', 'bx-grid-alt'],
        ],
        'overview.sidebar_team' => array_merge([
            ['admin.tickets.index', 'admin.sidebar.tickets', 'bx-headphone'],
            ['subscribers.index', 'overview.subscribers_total', 'bx-group'],
        ], auth()->user()->hasRole('admin') ? [
            ['employees.index', 'overview.sidebar_employees', 'bx-user'],
            ['plans.index', 'overview.sidebar_plans', 'bx-crown'],
            ['coupons.index', 'coupons.title', 'bx-purchase-tag'],
            ['package-features.index', 'Feature catalog', 'bx-grid-alt'],
            ['admin.faqs.index', 'overview.sidebar_faqs', 'bx-book-open'],
            ['admin.apis.index', 'overview.integrations', 'bx-plug'],
        ] : []),
    ] : [
        'overview.navigation' => [
            ['dashboard', 'admin.dashboard', 'bx-grid-alt'],
        ],
        'admin.sidebar.marketing' => [
            ['admin.ads.dashboard', 'admin.sidebar.ads', 'bx-bullseye'],
        ],
        'overview.sidebar_posts_heading' => [
            ['admin.posts.dashboard', 'admin.sidebar.posts', 'bx-calendar-edit'],
        ],
        'google_suite.nav.section' => [
            ['admin.google.overview', 'google_suite.title', 'bxl-google'],
        ],
        'overview.sidebar_workspace' => [
            ['seller-workspaces.index', 'seller_team.workspaces', 'bx-buildings'],
            ['admin.seller-team.index', 'seller_team.title', 'bx-group'],
            ['admin.connections.index', 'admin.sidebar.connections', 'bx-link'],
            ['admin.media-gallery.index', 'admin.sidebar.media_gallery', 'bx-images'],
            ['admin.email.dashboard', 'admin.sidebar.email_marketing', 'bx-envelope'],
        ],
        'admin.sidebar.engagement' => [
            ['admin.chats.dashboard', 'admin.sidebar.inbox', 'bx-message-square-dots'],
            ['admin.comments.dashboard', 'admin.sidebar.comments', 'bx-comment-dots'],
        ],
        'admin.sidebar.ai_assistant' => [
            ['admin.ai-copilot.analytics.index', 'admin.sidebar.ai_copilot', 'bx-brain'],
        ],
        'admin.sidebar.support' => [
            ['admin.help-center.index', 'admin.sidebar.help_center', 'bx-book-reader'],
            ['admin.tickets.index', 'admin.sidebar.tickets', 'bx-headphone'],
        ],
    ];
    if ($workspaceMember = request()->attributes->get('workspace_member')) {
        $access = app(\App\Services\PackageAccessService::class);
        foreach ($groups as $heading => $links) {
            $groups[$heading] = array_values(array_filter($links, function ($link) use ($workspaceMember, $access) {
                if ($link[0] === 'seller-workspaces.index') return true;
                // Help Center for everyone; Admins also Dashboard, Connections, My team and Support tickets.
                if ($workspaceMember->allowsRoute($link[0])) return true;
                // Modules they were given. Ones the agency's plan lacks stay visible with a lock (below), like the owner sees them.
                $modules = $access->modulesForRoute($link[0]);
                return $modules !== [] && collect($modules)->every(fn ($module) => $workspaceMember->canUse($module));
            }));
        }
    } elseif (! $team && ! auth()->user()->hasAnyRole(['seller', 'admin', 'customer_support']) && ! auth()->user()->isWorkspaceAccount()) {
        // A teammate-only account outside a workspace: just its workspace list.
        $groups = ['overview.sidebar_workspace' => [['seller-workspaces.index', 'seller_team.workspaces', 'bx-buildings']]];
    }
    $planLocked = [];
    $lockText = $workspaceMember ? __('package_access.sidebar_locked_member') : __('package_access.sidebar_locked');
    // Owners and teammates: mark links to modules the (agency's) plan doesn't include (the backend answers 403 there).
    if (! $team && auth()->user()->hasRole('seller')) {
        $planLocked = rescue(function () use ($groups) {
            $access = app(\App\Services\PackageAccessService::class);
            $allowed = [];
            $locked = [];
            foreach ($groups as $links) {
                foreach ($links as [$destination]) {
                    foreach ($access->modulesForRoute($destination) as $module) {
                        $allowed[$module] ??= $access->allows(auth()->user(), $module);
                        if (! $allowed[$module]) {
                            $locked[$destination] = true;
                        }
                    }
                }
            }

            return $locked;
        }, [], false);
    }
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical admin-sidebar overview-sidebar" aria-label="{{ __('overview.sidebar_navigation') }}">
    <div class="admin-sidebar-header ov-sidebar-header">
        <a href="{{ LaravelLocalization::getLocalizedURL(app()->getLocale(), route('dashboard'), [], true) }}" class="admin-sidebar-brand" aria-label="Socialeaz">
            <img class="admin-sidebar-logo" src="{{ asset('assets/images/home-theme/logo.webp') }}" alt="Socialeaz" width="145" height="48">
            <img class="admin-sidebar-logo-compact" src="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}" alt="Socialeaz" width="34" height="34">
        </a>
        <button type="button" class="admin-sidebar-collapse layout-menu-toggle" aria-label="{{ __('admin.navbar.toggle_sidebar') }}" title="{{ __('admin.navbar.toggle_sidebar') }}"><i class="bx bx-chevron-left" aria-hidden="true"></i></button>
    </div>
    @php
        // The open workspace: an owned brand/client (UseSellerWorkspace sets it) or a joined team workspace.
        $sidebarWorkspace = request()->attributes->get('workspace');
        // Nothing opened: the seller's own account is their default workspace.
        $sidebarWorkspaceName = $sidebarWorkspace?->name
            ?? request()->attributes->get('workspace_member')?->workspace_name
            ?? ($team ? null : auth()->user()->name);
    @endphp
    @if($sidebarWorkspaceName && Route::has('seller-workspaces.index'))
        @php
            // Switcher list for the signed-in person (not the workspace account the request runs as).
            $wsPerson = request()->attributes->get('workspace_actor') ?? auth()->user();
            $wsInitials = fn ($name) => mb_strtoupper(collect(preg_split('/\s+/u', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?';
            $wsMember = request()->attributes->get('workspace_member');
            $wsOwned = $team ? collect() : rescue(fn () => \App\Models\Workspace::where('owner_id', $wsPerson->id)->orderByDesc('last_opened_at')->orderBy('id')->get(['id', 'name', 'logo_path', 'account_id', 'owner_id']), collect(), false);
            $wsJoined = $team ? collect() : rescue(fn () => \App\Models\SellerTeamMember::where('user_id', $wsPerson->id)->whereNotNull('accepted_at')->with('owner:id,name')->get()->filter(fn ($m) => $m->owner), collect(), false);
            // Current: the opened workspace, the joined one being worked in, or (nothing opened) the default.
            $wsCurrentId = $sidebarWorkspace?->id ?? ($wsMember ? null : $wsOwned->firstWhere('account_id', $wsPerson->id)?->id);
            $wsCurrentLogo = $sidebarWorkspace?->logo_url ?? $wsOwned->firstWhere('id', $wsCurrentId)?->logo_url;
        @endphp
        <div class="ov-ws" data-ws-switcher>
            <button type="button" class="ov-ws-switch" aria-haspopup="true" aria-expanded="false" aria-controls="ov-ws-menu" title="{{ __('workspaces.switcher.switch') }}">
                <span class="ov-ws-avatar {{ $wsCurrentLogo ? 'has-logo' : '' }}" aria-hidden="true">@if($wsCurrentLogo)<img src="{{ $wsCurrentLogo }}" alt="">@else{{ $wsInitials($sidebarWorkspaceName) }}@endif</span>
                <span class="ov-ws-text"><strong>{{ $sidebarWorkspaceName }}</strong><small>{{ __('workspaces.switcher.switch') }}</small></span>
                <i class="bx bx-sort-alt-2" aria-hidden="true"></i>
            </button>
            <div class="ov-ws-menu" id="ov-ws-menu" role="menu" hidden>
                @if($wsOwned->isNotEmpty())
                    <p class="ov-ws-label">{{ __('workspaces.switcher.yours') }}</p>
                    @foreach($wsOwned as $ws)
                        @if($ws->id === $wsCurrentId)
                            <span class="ov-ws-item is-current" role="menuitem" aria-current="true" tabindex="-1">
                                <span class="ov-ws-avatar sm {{ $ws->logo_path ? 'has-logo' : '' }}" aria-hidden="true">@if($ws->logo_path)<img src="{{ $ws->logo_url }}" alt="">@else{{ $wsInitials($ws->name) }}@endif</span><span class="ov-ws-name">{{ $ws->name }}</span><i class="bx bx-check" aria-hidden="true"></i>
                            </span>
                        @else
                            <form method="POST" action="{{ route('seller-workspaces.open', $ws) }}">@csrf
                                <button type="submit" class="ov-ws-item" role="menuitem" tabindex="-1"><span class="ov-ws-avatar sm is-alt {{ $ws->logo_path ? 'has-logo' : '' }}" aria-hidden="true">@if($ws->logo_path)<img src="{{ $ws->logo_url }}" alt="">@else{{ $wsInitials($ws->name) }}@endif</span><span class="ov-ws-name">{{ $ws->name }}</span></button>
                            </form>
                        @endif
                    @endforeach
                @endif
                @if($wsJoined->isNotEmpty())
                    <p class="ov-ws-label">{{ __('workspaces.switcher.joined') }}</p>
                    @foreach($wsJoined as $membership)
                        @if($wsMember && $wsMember->id === $membership->id)
                            <span class="ov-ws-item is-current" role="menuitem" aria-current="true" tabindex="-1">
                                <span class="ov-ws-avatar sm is-team" aria-hidden="true">{{ $wsInitials($membership->workspace_name) }}</span><span class="ov-ws-name">{{ $membership->workspace_name }}</span><i class="bx bx-check" aria-hidden="true"></i>
                            </span>
                        @else
                            <form method="POST" action="{{ route('seller-workspaces.enter', $membership) }}">@csrf
                                <button type="submit" class="ov-ws-item" role="menuitem" tabindex="-1"><span class="ov-ws-avatar sm is-team" aria-hidden="true">{{ $wsInitials($membership->workspace_name) }}</span><span class="ov-ws-name">{{ $membership->workspace_name }}</span></button>
                            </form>
                        @endif
                    @endforeach
                @endif
                <div class="ov-ws-foot">
                    <a href="{{ route('seller-workspaces.index') }}" class="ov-ws-item" role="menuitem" tabindex="-1"><span class="ov-ws-icon" aria-hidden="true"><i class="bx bx-grid-alt"></i></span><span class="ov-ws-name">{{ __('workspaces.switcher.all') }}</span></a>
                    @unless($team)
                        <a href="{{ route('seller-workspaces.index', ['create' => 1]) }}" class="ov-ws-item is-new" role="menuitem" tabindex="-1"><span class="ov-ws-icon" aria-hidden="true"><i class="bx bx-plus"></i></span><span class="ov-ws-name">{{ __('workspaces.switcher.new') }}</span></a>
                    @endunless
                </div>
            </div>
        </div>
        <style>
            .ov-ws { position: relative; margin: 4px 14px 10px; }
            .ov-ws-switch { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; border: 1px solid var(--theme-border, #edf0fa); border-radius: 14px; background: #fff; color: var(--theme-ink, #09072d); font: inherit; text-align: start; cursor: pointer; }
            .ov-ws-switch:hover, .ov-ws-switch[aria-expanded="true"] { border-color: #D9C6FF; background: var(--theme-soft, #f1eaff); }
            .ov-ws-switch:focus-visible, .ov-ws-item:focus-visible { outline: 3px solid rgba(114,10,255,.35); outline-offset: 2px; }
            .ov-ws-avatar { display: grid; place-items: center; flex-shrink: 0; width: 34px; height: 34px; border-radius: 10px; background: var(--theme-gradient, linear-gradient(110deg, #004cff, #710bff)); color: #fff; font-size: 13px; font-weight: 700; }
            .ov-ws-avatar.sm { width: 28px; height: 28px; border-radius: 8px; font-size: 11px; }
            .ov-ws-avatar { overflow: hidden; }
            .ov-ws-avatar.has-logo { background: #fff; border: 1px solid var(--theme-border, #edf0fa); }
            .ov-ws-avatar img { width: 100%; height: 100%; object-fit: contain; }
            .ov-ws-avatar.is-alt { background: #0F766E; }
            .ov-ws-avatar.is-team { background: #9A3412; }
            .ov-ws-text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.25; }
            .ov-ws-text strong { font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ov-ws-text small { color: var(--theme-muted, #626498); font-size: 12px; }
            .ov-ws-switch > i { color: var(--theme-muted, #626498); font-size: 18px; }
            .ov-ws-menu { position: absolute; z-index: 1085; top: calc(100% + 6px); inset-inline: 0; max-height: min(420px, 70vh); overflow-y: auto; padding: 6px; border: 1px solid var(--theme-border, #edf0fa); border-radius: 14px; background: #fff; box-shadow: 0 18px 40px rgba(9, 7, 45, .14); }
            .ov-ws-menu[hidden] { display: none; }
            .ov-ws-menu form { margin: 0; }
            .ov-ws-label { margin: 8px 8px 4px; color: var(--theme-muted, #626498); font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
            .ov-ws-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 7px 8px; border: 0; border-radius: 10px; background: none; color: var(--theme-ink, #09072d); font: inherit; font-size: 13.5px; text-align: start; text-decoration: none; cursor: pointer; }
            .ov-ws-item:hover { background: var(--theme-soft, #f1eaff); color: var(--theme-ink, #09072d); }
            .ov-ws-item.is-current { background: #F7F5FF; cursor: default; font-weight: 600; }
            .ov-ws-item > i { color: var(--theme-purple, #720aff); font-size: 18px; }
            .ov-ws-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ov-ws-foot { margin-top: 6px; padding-top: 6px; border-top: 1px solid var(--theme-border, #edf0fa); }
            .ov-ws-icon { display: grid; place-items: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 8px; background: #F5F6FB; color: var(--theme-muted, #626498); font-size: 16px; }
            .ov-ws-foot .ov-ws-item.is-new { color: var(--theme-purple, #720aff); font-weight: 600; }
            .ov-ws-foot .ov-ws-item.is-new .ov-ws-icon { background: var(--theme-soft, #f1eaff); color: var(--theme-purple, #720aff); }
            .layout-menu-collapsed:not(.layout-menu-hover) .ov-ws-text, .layout-menu-collapsed:not(.layout-menu-hover) .ov-ws-switch > i { display: none; }
            .layout-menu-collapsed:not(.layout-menu-hover) .ov-ws-menu { inset-inline-end: auto; width: 260px; }
        </style>
        <script>
            (function () {
                var root = document.querySelector('[data-ws-switcher]');
                if (!root || root.dataset.ready) return;
                root.dataset.ready = '1';
                var button = root.querySelector('.ov-ws-switch');
                var menu = root.querySelector('.ov-ws-menu');
                var items = function () { return Array.prototype.slice.call(menu.querySelectorAll('.ov-ws-item')); };
                function open(focusFirst) {
                    menu.hidden = false;
                    button.setAttribute('aria-expanded', 'true');
                    if (focusFirst) { var first = menu.querySelector('.ov-ws-item:not(.is-current)') || items()[0]; if (first) first.focus(); }
                }
                function close(returnFocus) {
                    if (menu.hidden) return;
                    menu.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                    if (returnFocus) button.focus();
                }
                button.addEventListener('click', function () { menu.hidden ? open(false) : close(false); });
                button.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(true); }
                });
                menu.addEventListener('keydown', function (e) {
                    var list = items(), i = list.indexOf(document.activeElement);
                    if (e.key === 'ArrowDown') { e.preventDefault(); (list[i + 1] || list[0]).focus(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); (list[i - 1] || list[list.length - 1]).focus(); }
                    else if (e.key === 'Tab') { close(false); }
                });
                // Escape closes it wherever focus is inside the switcher (menu opened by mouse keeps focus on the button).
                root.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { e.preventDefault(); close(true); } });
                document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(false); });
            })();
        </script>
    @endif
    <nav class="admin-sidebar-body ov-sidebar-body" aria-label="{{ __('overview.sidebar_navigation') }}">
        <ul class="menu-inner ov-sidebar-menu">
            @foreach($groups as $heading => $links)
                <li class="ov-sidebar-section"><span>{{ __($heading) }}</span></li>
                @foreach($links as [$destination, $label, $icon])
                    @php
                        $active = request()->routeIs($destination)
                            || ($destination === 'dashboard' && request()->routeIs('dashboard.overview'))
                            || ($destination === 'plans.index' && request()->routeIs('plans.*'))
                            || ($destination === 'coupons.index' && request()->routeIs('coupons.*'))
                            || ($destination === 'package-features.index' && request()->routeIs('package-features.*'));
                        $submenu = $children[$destination] ?? [];
                        $submenuOpen = ($destination === 'admin.ads.dashboard' && request()->routeIs('admin.ads.*'))
                            || ($destination === 'admin.posts.dashboard' && request()->routeIs('admin.posts.*'))
                            || ($destination === 'admin.google.overview' && request()->routeIs('admin.google.*'));
                        $active = $active || $submenuOpen;
                        $submenuId = 'ov-sidebar-'.str_replace('.', '-', $destination);
                    @endphp
                    <li class="menu-item {{ $active ? 'active' : '' }} {{ $submenu ? 'ov-sidebar-dropdown' : '' }}">
                        @if($submenu)
                            <button type="button" class="menu-link ov-sidebar-link ov-sidebar-parent-toggle" data-module="{{ $destination }}" title="{{ __($label) }}" aria-expanded="{{ $submenuOpen ? 'true' : 'false' }}" aria-controls="{{ $submenuId }}">
                        @else
                            <a href="{{ route($destination) }}" class="menu-link ov-sidebar-link" data-module="{{ $destination }}" title="{{ __($label) }}" @if($active) aria-current="page" @endif>
                        @endif
                            <span class="ov-sidebar-icon"><i class="bx {{ $icon }}" aria-hidden="true"></i></span>
                            <span class="ov-sidebar-label">{{ __($label) }}</span>
                            @if($destination === 'admin.chats.dashboard' && ($unread ?? 0) > 0)<span class="ov-sidebar-badge">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                            @if(isset($planLocked[$destination]))<i class="bx bx-crown ov-sidebar-badge ov-sidebar-lock" title="{{ $lockText }}" aria-label="{{ $lockText }}"></i>
                            @elseif($connectionLocked && in_array($destination, $connectionGated, true))<i class="bx bx-lock-alt ov-sidebar-badge ov-sidebar-lock" title="{{ __('connections.gate.locked') }}" aria-label="{{ __('connections.gate.locked') }}"></i>@endif
                            @if($destination === 'admin.tickets.index' && ($openTickets ?? $ticketCounts['open'] ?? 0) > 0)<span class="ov-sidebar-badge">{{ min(99, $openTickets ?? $ticketCounts['open']) }}</span>@endif
                        @if($submenu)
                                <span class="ov-sidebar-dropdown-toggle" aria-hidden="true"><i class="bx bx-chevron-down"></i></span>
                            </button>
                        @else
                            </a>
                        @endif
                        @if($submenu)
                            <ul id="{{ $submenuId }}" class="ov-sidebar-submenu" @unless($submenuOpen) hidden @endunless>
                                @foreach($submenu as [$childRoute, $childLabel, $childIcon, $parameters])
                                    @php
                                        $childActive = request()->routeIs($childRoute, ...($childAliases[$childRoute] ?? []))
                                            && (request()->route('platform') ?? request()->query('platform')) === ($parameters['platform'] ?? null);
                                    @endphp
                                    <li>
                                        <a href="{{ route($childRoute, $parameters) }}" @if($childActive) aria-current="page" @endif @if(isset($parameters['platform'])) data-platform="{{ $parameters['platform'] }}" @elseif(isset($googleTones[$childRoute])) data-service="{{ $googleTones[$childRoute] }}" @endif>
                                            <span class="ov-sidebar-subicon" aria-hidden="true"><i class="bx {{ $childIcon }}"></i></span>
                                            <span>{{ __($childLabel) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </nav>
    <div class="admin-sidebar-footer ov-sidebar-footer">
        @unless($team || request()->attributes->has('workspace_member'))
            <a class="ov-sidebar-plan" href="{{ route('admin.subscription.usage') }}" title="{{ __('admin.sidebar.manage_your_plan') }}"><span class="ov-plan-icon"><i class="bx bx-crown" aria-hidden="true"></i></span><div><strong>{{ __('overview.sidebar_plan_title') }}</strong><small>{{ __('admin.sidebar.manage_your_plan') }}</small></div><i class="bx bx-arrow-back ov-plan-arrow" aria-hidden="true"></i></a>
        @endunless
    </div>
</aside>
<script src="{{ asset('assets/js/overview-sidebar.js') }}?v={{ filemtime(public_path('assets/js/overview-sidebar.js')) }}" defer></script>
