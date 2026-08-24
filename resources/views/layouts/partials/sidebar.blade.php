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
                aria-label="Toggle sidebar"
                title="Collapse sidebar">

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

                    <span>Dashboard</span>

                </a>

            </li>


            {{-- =================================================
                 MANAGEMENT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>Management</span>
            </li>


            {{-- MARKETING --}}
            <li class="menu-item
                {{
                    (
                        request()->routeIs('admin.ads.*') ||
                        request()->routeIs('admin.posts.*') ||
                        request()->routeIs('admin.chats.*') ||
                        request()->routeIs('admin.comments.*') ||
                        request()->routeIs('admin.email.*')
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
                <span>Applications</span>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-envelope"></i>
                    <span>Email</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-message-rounded"></i>
                    <span>Chat</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-task"></i>
                    <span>Kanban</span>
                </a>
            </li>


            {{-- =================================================
                 ACCOUNT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>Account</span>
            </li>


            <li class="menu-item">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-user-circle"></i>

                    <span>Account Settings</span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Account
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Notifications
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Connections
                        </a>
                    </li>

                </ul>

            </li>


            <li class="menu-item">

                <a href="javascript:void(0)"
                   class="menu-link menu-toggle">

                    <i class="menu-icon tf-icons bx bx-lock-alt"></i>

                    <span>Authentications</span>

                </a>

                <ul class="menu-sub">

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Login
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Register
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Forgot Password
                        </a>
                    </li>

                </ul>

            </li>


            {{-- =================================================
                 DATA
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>Data</span>
            </li>


            <li class="menu-item">
                <a href="#" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-table"></i>
                    <span>Tables</span>
                </a>
            </li>


            {{-- =================================================
                 SUPPORT
            ================================================== --}}
            <li class="admin-sidebar-section">
                <span>Support</span>
            </li>


            <li class="menu-item">

                <a href="https://github.com/"
                   target="_blank"
                   class="menu-link">

                    <i class="menu-icon tf-icons bx bx-support"></i>

                    <span>Support</span>

                    <i class="bx bx-link-external admin-sidebar-external"></i>

                </a>

            </li>


            <li class="menu-item">

                <a href="https://docs.example.com"
                   target="_blank"
                   class="menu-link">

                    <i class="menu-icon tf-icons bx bx-file"></i>

                    <span>Documentation</span>

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
                <strong>Subscription</strong>
                <small>Manage your plan</small>
            </div>

            <i class="bx bx-chevron-right"></i>

        </a>

    </div>

</aside>