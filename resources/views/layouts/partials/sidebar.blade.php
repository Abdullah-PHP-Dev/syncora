<aside id="layout-menu" class="layout-menu menu-vertical admin-sidebar">

    {{-- =========================================================
         SIDEBAR HEADER
    ========================================================== --}}
    <div class="admin-sidebar-header">

        <a href="{{ url('/') }}" class="admin-sidebar-brand">

            <span class="admin-sidebar-brand-icon">
                {{-- Keep your existing SVG/logo here --}}
                <span class="text-primary"></span>
            </span>

            <span class="admin-sidebar-brand-name">
                {{ config('app.name') }}
            </span>

        </a>

        <button type="button"
                class="admin-sidebar-collapse layout-menu-toggle"
                aria-label="{{ __('admin.navbar.toggle_sidebar') }}"
                title="{{ __('admin.sidebar.collapse_sidebar') }}">

            <i class="bx bx-chevron-left"></i>

        </button>

    </div>


    {{-- =========================================================
         SIDEBAR MENU
    ========================================================== --}}
    <div class="admin-sidebar-body">

        <ul class="menu-inner admin-sidebar-menu">

            {{-- DASHBOARD --}}
            <li class="menu-item {{ request()->routeIs('admin.dashboard') || request()->routeIs('crm-dashboard') ? 'active' : '' }}">

                <a href="{{ url('/admin/dashboard') }}"
                   class="menu-link">

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
            <li class="menu-item
                {{
                    (
                        request()->routeIs('admin.ads.*') ||
                        request()->routeIs('admin.posts.*') ||
                        request()->routeIs('admin.chats.*') ||
                        request()->routeIs('admin.comments.*') ||
                        request()->routeIs('admin.email.*') ||
                        request()->routeIs('admin.knowledge-base.*')
                    ) ? 'active open' : ''
                }}">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-broadcast"></i>

                    <span>
                        {{ __('admin.marketing_tools.header') }}
                    </span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.ads.dashboard') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.ads.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.posts.dashboard') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.posts.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.chats.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.chats.dashboard') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.chats.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.comments.dashboard') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.comments.header') }}
                        </a>
                    </li>

                    <li class="menu-item {{ request()->routeIs('admin.email.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.email.dashboard') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.email.header') }}
                        </a>
                    </li>

                    {{-- Seller's own business FAQ - feeds the AI Copilot's
                         customer-facing answers (Phase 3), distinct from
                         the read-only System Help Center below. --}}
                    <li class="menu-item {{ request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.knowledge-base.index') }}"
                           class="menu-link">
                            {{ __('admin.marketing_tools.knowledge_base.header') }}
                        </a>
                    </li>

                </ul>

            </li>


            {{-- SUPPORT: Help Center + Tickets for every seller, plus
                 System FAQ management for admin-role users only. Outside
                 the subscription-required tier server-side (see
                 routes/web.php) - the link still only needs to render,
                 not re-enforce that. --}}
            <li class="menu-item
                {{
                    (
                        request()->routeIs('admin.help-center.*') ||
                        request()->routeIs('admin.tickets.*') ||
                        request()->routeIs('admin.faqs.*')
                    ) ? 'active open' : ''
                }}">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

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


            {{-- API --}}
            <li class="menu-item {{ request()->routeIs('admin.apis.*') ? 'active' : '' }}">

                <a href="{{ route('admin.apis.index') }}"
                   class="menu-link">

                    <i class="menu-icon tf-icons bx bx-code-alt"></i>

                    <span>
                        {{ __('admin.api.header') }}
                    </span>

                </a>

            </li>


            {{-- =================================================
                 APPLICATIONS
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.applications') }}</span>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-envelope"></i>
                    <span>{{ __('admin.sidebar.email') }}</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-message-rounded"></i>
                    <span>{{ __('admin.sidebar.chat') }}</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-calendar"></i>
                    <span>{{ __('admin.sidebar.calendar') }}</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-task"></i>
                    <span>{{ __('admin.sidebar.kanban') }}</span>
                </a>
            </li>


            {{-- =================================================
                 ACCOUNT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.account') }}</span>
            </li>


            <li class="menu-item">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-user-circle"></i>

                    <span>{{ __('admin.sidebar.account_settings') }}</span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.account') }}
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.notifications') }}
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.connections') }}
                        </a>
                    </li>

                </ul>

            </li>


            <li class="menu-item">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-lock-alt"></i>

                    <span>{{ __('admin.sidebar.authentications') }}</span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.login') }}
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.register') }}
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            {{ __('admin.sidebar.forgot_password') }}
                        </a>
                    </li>

                </ul>

            </li>


            {{-- =================================================
                 DATA
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.data') }}</span>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-table"></i>
                    <span>{{ __('admin.sidebar.tables') }}</span>
                </a>
            </li>


            {{-- =================================================
                 SUPPORT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>{{ __('admin.sidebar.support') }}</span>
            </li>


            <li class="menu-item">

                <a href="https://github.com/"
                   target="_blank"
                   class="menu-link">

                    <i class="menu-icon tf-icons bx bx-support"></i>

                    <span>{{ __('admin.sidebar.support') }}</span>

                    <i class="bx bx-link-external admin-sidebar-external"></i>

                </a>

            </li>


            <li class="menu-item">

                <a href="https://docs.example.com"
                   target="_blank"
                   class="menu-link">

                    <i class="menu-icon tf-icons bx bx-file"></i>

                    <span>{{ __('admin.sidebar.documentation') }}</span>

                    <i class="bx bx-link-external admin-sidebar-external"></i>

                </a>

            </li>

        </ul>

    </div>


    {{-- =========================================================
         SIDEBAR FOOTER
    ========================================================== --}}
    <div class="admin-sidebar-footer">

        <a href="{{ url('admin/subscription/select') }}"
           class="admin-sidebar-subscription">

            <i class="bx bx-crown"></i>

            <div>
                <strong>{{ __('admin.sidebar.subscription') }}</strong>
                <small>{{ __('admin.sidebar.manage_your_plan') }}</small>
            </div>

            <i class="bx bx-chevron-right"></i>

        </a>

    </div>

</aside>