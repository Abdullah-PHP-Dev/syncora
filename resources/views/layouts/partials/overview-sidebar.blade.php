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
        'employees.index' => [['employees.index', 'overview.sidebar_employees', 'bx-user', []], ['employees.create', 'overview.add_employee', 'bx-user-plus', []]],
    ];
    if (!$team) {
        foreach ($platforms as $platform => [$platformLabel, $platformIcon]) {
            $children['admin.posts.dashboard'][] = ['admin.posts.index', $platformLabel, $platformIcon, ['platform' => $platform]];
            $children['admin.ads.dashboard'][] = ['admin.ads.campaigns.index', $platformLabel, $platformIcon, ['platform' => $platform]];
        }
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
                $modules = $access->modulesForRoute($link[0]);
                return $modules !== [] && collect($modules)->every(fn ($module) => in_array($workspaceMember->permissions[$module] ?? 'none', ['read', 'write'], true) && $access->allows(auth()->user(), $module));
            }));
        }
    }
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical admin-sidebar overview-sidebar" aria-label="{{ __('overview.sidebar_navigation') }}">
    <div class="admin-sidebar-header ov-sidebar-header">
        <a href="{{ LaravelLocalization::getLocalizedURL(app()->getLocale(), route('dashboard'), [], true) }}" class="admin-sidebar-brand" aria-label="Socialeaz">
            <img class="admin-sidebar-logo" src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="145" height="48">
            <img class="admin-sidebar-logo-compact" src="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}" alt="Socialeaz" width="34" height="34">
        </a>
        <button type="button" class="admin-sidebar-collapse layout-menu-toggle" aria-label="{{ __('admin.navbar.toggle_sidebar') }}" title="{{ __('admin.navbar.toggle_sidebar') }}"><i class="bx bx-chevron-left" aria-hidden="true"></i></button>
    </div>
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
                            || ($destination === 'admin.posts.dashboard' && request()->routeIs('admin.posts.*'));
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
                            @if($connectionLocked && in_array($destination, $connectionGated, true))<i class="bx bx-lock-alt ov-sidebar-badge ov-sidebar-lock" title="{{ __('connections.gate.locked') }}" aria-label="{{ __('connections.gate.locked') }}"></i>@endif
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
                                        $childActive = request()->routeIs($childRoute)
                                            && (request()->route('platform') ?? request()->query('platform')) === ($parameters['platform'] ?? null);
                                    @endphp
                                    <li>
                                        <a href="{{ route($childRoute, $parameters) }}" @if($childActive) aria-current="page" @endif @if(isset($parameters['platform'])) data-platform="{{ $parameters['platform'] }}" @endif>
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
