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
            <li
                class="menu-item {{ request()->routeIs('dashboard') || request()->routeIs('crm-dashboard') ? 'active' : '' }}">

                <a href="{{ route('dashboard') }}" class="menu-link">

                    <i class="menu-icon tf-icons bx bx-grid-alt"></i>

                    <span>{{ __('admin.dashboard') }}</span>

                </a>

            </li>


            {{-- =================================================
                 MANAGEMENT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.management') }}</span>
            </li>


            {{-- MARKETING --}}
            <li
                class="menu-item
                {{ request()->routeIs('admin.ads.*') ||
                request()->routeIs('admin.posts.*') ||
                request()->routeIs('admin.chats.*') ||
                request()->routeIs('admin.comments.*') ||
                request()->routeIs('admin.email.*') ||
                request()->routeIs('admin.knowledge-base.*') ||
                request()->routeIs('admin.ai-copilot.*')
                    ? 'active open'
                    : '' }}">

                <a href="javascript:void(0)" class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-broadcast"></i>

                    <span>
                        {{ __('admin.marketing_tools.header') }}
                    </span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ads.dashboard') }}" class="menu-link">
                            {{ __('admin.marketing_tools.ads.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.posts.dashboard') }}" class="menu-link">
                            {{ __('admin.marketing_tools.posts.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.chats.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.chats.dashboard') }}" class="menu-link">
                            {{ __('admin.marketing_tools.chats.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.comments.dashboard') }}" class="menu-link">
                            {{ __('admin.marketing_tools.comments.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.email.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.email.dashboard') }}" class="menu-link">
                            {{ __('admin.marketing_tools.email.header') }}
                        </a>
                    </li>

                    {{-- Seller's own business FAQ - feeds the AI Copilot's
                         customer-facing answers (Phase 3), distinct from
                         the read-only System Help Center below. --}}
                    <li class="menu-item {{ request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.knowledge-base.index') }}" class="menu-link">
                            {{ __('admin.marketing_tools.knowledge_base.header') }}
                        </a>
                    </li>

                    {{-- Automatic customer-reply loop on top of the Knowledge
                         Base above: settings, Business Profile (structured
                         data), Knowledge Gaps (unanswered questions) and
                         Analytics (copilot_messages KPIs) - four flat
                         entries rather than a nested sub-menu, since this
                         theme's menu styling isn't confirmed to support a
                         third nesting level. --}}
                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.settings.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.settings.index') }}" class="menu-link">
                            {{ __('admin.marketing_tools.ai_copilot.header') }}
                        </a>
                    </li>

                    <li
                        class="menu-item {{ request()->routeIs('admin.ai-copilot.business-profile.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.business-profile.edit') }}" class="menu-link">
                            {{ __('admin.marketing_tools.business_profile.header') }}
                        </a>
                    </li>

                    <li
                        class="menu-item {{ request()->routeIs('admin.ai-copilot.knowledge-gaps.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.knowledge-gaps.index') }}" class="menu-link">
                            {{ __('admin.marketing_tools.knowledge_gaps.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.ai-copilot.analytics.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ai-copilot.analytics.index') }}" class="menu-link">
                            {{ __('admin.marketing_tools.ai_copilot_analytics.header') }}
                        </a>
                    </li>

                </ul>

            </li>





            {{-- API --}}
            <li class="menu-item {{ request()->routeIs('admin.apis.*') ? 'active' : '' }}">

                <a href="{{ route('admin.apis.index') }}" class="menu-link">

                    <i class="menu-icon tf-icons bx bx-code-alt"></i>

                    <span>
                        {{ __('admin.api.header') }}
                    </span>

                </a>

            </li>


            {{-- =================================================
                 SUPPORT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.support') }}</span>
            </li>


            {{-- SUPPORT: Help Center + Tickets for every seller, plus
                 System FAQ management for admin-role users only. Outside
                 the subscription-required tier server-side (see
                 routes/web.php) - the link still only needs to render,
                 not re-enforce that. --}}
            <li
                class="menu-item
                {{ request()->routeIs('admin.help-center.*') ||
                request()->routeIs('admin.tickets.*') ||
                request()->routeIs('admin.faqs.*')
                    ? 'active open'
                    : '' }}">

                <a href="javascript:void(0)" class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-support"></i>

                    <span>
                        {{ __('admin.support.tickets') }}
                    </span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item {{ request()->routeIs('admin.help-center.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.help-center.index') }}" class="menu-link">
                            {{ __('admin.support.help_center') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.tickets.index') }}" class="menu-link">
                            {{ __('admin.support.tickets') }}
                        </a>
                    </li>

                    @if (Auth::user()?->hasRole('admin'))
                        <li class="menu-item {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
                            <a href="{{ route('admin.faqs.index') }}" class="menu-link">
                                {{ __('admin.support.faq_management') }}
                            </a>
                        </li>
                    @endif

                </ul>

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
