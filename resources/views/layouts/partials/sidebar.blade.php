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

            <li class="menu-item {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
                <a href="{{ route('admin.ads.dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-bullseye"></i>
                    <span>{{ __('admin.sidebar.ads') }}</span>
                </a>
            </li>

            <li class="menu-item {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
                <a href="{{ route('admin.posts.dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-calendar-edit"></i>
                    <span>{{ __('admin.sidebar.posts') }}</span>
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


            {{-- =================================================
                 ADMINISTRATION - admin-role users only
            ================================================== --}}
            @if (Auth::user()?->hasAnyRole(['admin', 'administrator']))
                <li class="admin-sidebar-section">
                    <span>{{ __('admin.sidebar.administration') }}</span>
                </li>

                <li class="menu-item {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.faqs.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-list-check"></i>
                        <span>{{ __('admin.sidebar.system_faq') }}</span>
                    </a>
                </li>

                <li class="menu-item {{ request()->routeIs('admin.apis.*') ? 'active' : '' }}">
                    <a href="{{ route('admin.apis.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons bx bx-code-alt"></i>
                        <span>{{ __('admin.sidebar.api') }}</span>
                    </a>
                </li>
            @endif

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
