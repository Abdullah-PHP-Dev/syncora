<aside id="layout-menu" class="layout-menu menu-vertical admin-sidebar">

    {{-- =========================================================
         SIDEBAR HEADER
    ========================================================== --}}
    <div class="admin-sidebar-header">

        <a href="{{ url('/') }}" class="admin-sidebar-brand">

            <span class="admin-sidebar-brand-icon">
                <img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="{{ config('app.name') }}">
            </span>

            <span class="admin-sidebar-brand-name">
                {{ config('app.name') }}
            </span>

        </a>

        <button type="button" class="admin-sidebar-collapse layout-menu-toggle"
            aria-label="{{ __('admin.navbar.toggle_sidebar') }}" title="{{ __('admin.sidebar.collapse_sidebar') }}">

            <i class="bx bx-chevron-left"></i>

        </button>

    </div>


    {{-- =========================================================
         SIDEBAR MENU
    ========================================================== --}}
    <div class="admin-sidebar-body">

        <ul class="menu-inner admin-sidebar-menu">

            {{-- DASHBOARD --}}
            <li class="menu-item {{ request()->routeIs('dashboard', 'crm-dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-grid-alt"></i>
                    <span>{{ __('admin.dashboard') }}</span>
                </a>
            </li>


            {{-- =================================================
                 MARKETING - outbound: paid ads, organic posts, email
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.marketing') }}</span>
            </li>

            @php
                // Platforms listed under the Ads Manager and Content Publishing
                // groups - key is the {platform} route segment / ?platform= value.
                $navPlatforms = [
                    'facebook'  => ['Facebook',  'bxl-facebook-circle'],
                    'instagram' => ['Instagram', 'bxl-instagram'],
                    'tiktok'    => ['TikTok',    'bxl-tiktok'],
                    'snapchat'  => ['Snapchat',  'bxl-snapchat'],
                    'google'    => ['Google',    'bxl-google'],
                    'youtube'   => ['YouTube',   'bxl-youtube'],
                    'linkedin'  => ['LinkedIn',  'bxl-linkedin-square'],
                ];

                $adsPlatform = request()->routeIs('admin.ads.campaigns.*', 'admin.ads.identities') ? request()->route('platform') : null;
                $postsPlatform = request()->routeIs('admin.posts.index') ? strtolower((string) request('platform')) : null;

                // A group is open (and its parent highlighted) on any page
                // inside it; otherwise the script at the bottom of this file restores the seller's
                // last manual expand/collapse choice.
                $navGroups = [
                    'ads' => [
                        'label'    => __('admin.sidebar.ads'),
                        'icon'     => 'bx-bullseye',
                        'overview' => route('admin.ads.dashboard'),
                        'current'  => request()->routeIs('admin.ads.*'),
                        'overviewActive' => request()->routeIs('admin.ads.dashboard'),
                        'active'   => $adsPlatform,
                        'url'      => fn ($key) => route('admin.ads.campaigns.index', ['platform' => $key]),
                    ],
                    'posts' => [
                        'label'    => __('admin.sidebar.posts'),
                        'icon'     => 'bx-calendar-edit',
                        'overview' => route('admin.posts.dashboard'),
                        'current'  => request()->routeIs('admin.posts.*'),
                        'overviewActive' => request()->routeIs('admin.posts.dashboard'),
                        'active'   => $postsPlatform,
                        'url'      => fn ($key) => route('admin.posts.index', ['platform' => $key]),
                    ],
                ];
            @endphp

            @foreach ($navGroups as $groupKey => $group)
                <li class="menu-item admin-nav-group {{ $group['current'] ? 'active open' : '' }}" data-nav-group="{{ $groupKey }}">

                    {{-- The label opens the unified dashboard; the chevron
                         only expands/collapses the platform list. --}}
                    <a href="{{ $group['overview'] }}" class="menu-link">
                        <i class="menu-icon tf-icons bx {{ $group['icon'] }}"></i>
                        <span>{{ $group['label'] }}</span>
                    </a>

                    <button type="button" class="admin-nav-chevron"
                        aria-expanded="{{ $group['current'] ? 'true' : 'false' }}"
                        aria-controls="admin-nav-{{ $groupKey }}"
                        aria-label="{{ __('admin.sidebar.toggle_group', ['name' => $group['label']]) }}">
                        <i class="bx bx-chevron-down"></i>
                    </button>

                    <div class="admin-nav-collapse" id="admin-nav-{{ $groupKey }}">
                        <ul class="admin-nav-sub">
                            <li class="{{ $group['overviewActive'] ? 'active' : '' }}">
                                <a href="{{ $group['overview'] }}" @if ($group['overviewActive']) aria-current="page" @endif>
                                    <i class="bx bx-grid-alt"></i>
                                    <span>{{ __('admin.sidebar.overview') }}</span>
                                </a>
                            </li>
                            @foreach ($navPlatforms as $key => [$name, $icon])
                                <li class="{{ $group['active'] === $key ? 'active' : '' }}">
                                    <a href="{{ $group['url']($key) }}" @if ($group['active'] === $key) aria-current="page" @endif>
                                        <i class="bx {{ $icon }}"></i>
                                        <span>{{ $name }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                </li>
            @endforeach

            {{-- Reusable images/videos for posts and ad campaigns. --}}
            <li class="menu-item {{ request()->routeIs('admin.media-gallery.*') ? 'active' : '' }}">
                <a href="{{ route('admin.media-gallery.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-images"></i>
                    <span>{{ __('admin.sidebar.media_gallery') }}</span>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.email.*') ? 'active' : '' }}">
                <a href="{{ route('admin.email.dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-envelope"></i>
                    <span>{{ __('admin.sidebar.email_marketing') }}</span>
                </a>
            </li>


            {{-- =================================================
                 ENGAGEMENT - inbound: customer messages and comments
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.engagement') }}</span>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.chats.*') ? 'active' : '' }}">
                <a href="{{ route('admin.chats.dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-message-square-dots"></i>
                    <span>{{ __('admin.sidebar.inbox') }}</span>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}">
                <a href="{{ route('admin.comments.dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-comment-detail"></i>
                    <span>{{ __('admin.sidebar.comments') }}</span>
                </a>
            </li>


            {{-- =================================================
                 AI ASSISTANT - the automatic customer-reply loop.
                 The seller's Knowledge Base feeds the Copilot's
                 answers (distinct from the read-only System Help
                 Center under Support), so it lives in this group.
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.ai_assistant') }}</span>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.ai-copilot.*', 'admin.knowledge-base.*') ? 'active open' : '' }}">
                <a href="javascript:void(0)" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons bx bx-bot"></i>
                    <span>{{ __('admin.sidebar.ai_copilot') }}</span>
                </a>

                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.settings.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.settings.index') }}" class="menu-link">
                            {{ __('admin.sidebar.copilot_settings') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.business-profile.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.business-profile.edit') }}" class="menu-link">
                            {{ __('admin.sidebar.business_profile') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.knowledge-base.index') }}" class="menu-link">
                            {{ __('admin.sidebar.knowledge_base') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.knowledge-gaps.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.knowledge-gaps.index') }}" class="menu-link">
                            {{ __('admin.sidebar.knowledge_gaps') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.analytics.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.analytics.index') }}" class="menu-link">
                            {{ __('admin.sidebar.analytics') }}
                        </a>
                    </li>
                </ul>
            </li>


            {{-- =================================================
                 SUPPORT - outside the subscription-required tier
                 server-side (see routes/web.php).
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.support') }}</span>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.help-center.*') ? 'active' : '' }}">
                <a href="{{ route('admin.help-center.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-help-circle"></i>
                    <span>{{ __('admin.sidebar.help_center') }}</span>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                <a href="{{ route('admin.tickets.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-support"></i>
                    <span>{{ __('admin.sidebar.tickets') }}</span>
                </a>
            </li>

        </ul>

    </div>


    {{-- =========================================================
         SIDEBAR FOOTER
    ========================================================== --}}
    <div class="admin-sidebar-footer">

        <a href="{{ url('subscription/select') }}" class="admin-sidebar-subscription">

            <i class="bx bx-crown"></i>

            <div>
                <strong>{{ __('admin.sidebar.subscription') }}</strong>
                <small>{{ __('admin.sidebar.manage_your_plan') }}</small>
            </div>

            <i class="bx bx-chevron-right"></i>

        </a>

    </div>

</aside>

{{-- Ads Manager / Content Publishing expand-collapse. Outside #app, so
     plain DOM listeners are safe here (Vue never re-mounts the sidebar). --}}
<script>
    (function () {
        document.querySelectorAll('.admin-nav-group').forEach(function (group) {
            var key = 'sidebar-nav-' + group.dataset.navGroup;
            var button = group.querySelector('.admin-nav-chevron');

            function setOpen(open) {
                group.classList.toggle('open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            // Pages inside the group always render it open; elsewhere,
            // restore the last manual choice (no animation on load).
            if (!group.classList.contains('active')) {
                try {
                    if (localStorage.getItem(key) === '1') {
                        group.classList.add('no-anim');
                        setOpen(true);
                        requestAnimationFrame(function () { group.classList.remove('no-anim'); });
                    }
                } catch (e) {}
            }

            button.addEventListener('click', function () {
                var open = !group.classList.contains('open');
                setOpen(open);
                try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) {}
            });
        });
    })();
</script>
