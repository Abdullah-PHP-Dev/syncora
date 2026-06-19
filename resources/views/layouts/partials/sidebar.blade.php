<!-- Menu -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

    <!-- App Brand -->
    <div class="app-brand demo">
        <a href="{{ url('/') }}" class="app-brand-link">
      <span class="app-brand-logo demo">
        {{-- SVG Logo (unchanged) --}}
        <span class="text-primary">
         {{--{{ config('app.name') }}--}}
        </span>
      </span>

            <span class="app-brand-text demo menu-text fw-bold ms-2">
        {{ config('app.name') }}
      </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
        </a>
    </div>

    <div class="menu-divider mt-0"></div>
    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        {{-- DASHBOARD --}}
        <li class="menu-item {{ request()->routeIs('dashboard') || request()->routeIs('crm-dashboard')  ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div>Dashboards</div>
                <span class="badge rounded-pill bg-danger ms-auto">5</span>
            </a>

            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ url('/admin/dashboard') }}" class="menu-link">Analytics</a>
                </li>

                <li class="menu-item {{ request()->routeIs('crm-dashboard') ? 'active' : '' }}">
                    <a href="{{ url('/admin/dashboard/crm') }}" class="menu-link">CRM</a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">eCommerce</a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">Logistics</a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">Academy</a>
                </li>
            </ul>
        </li>

        {{-- LAYOUTS --}}
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-layout"></i>
                <div>Layouts</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item"><a href="#" class="menu-link">Without menu</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Without navbar</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Fluid</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Container</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Blank</a></li>
            </ul>
        </li>

        {{-- FRONT PAGES --}}
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div>Front Pages</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item"><a href="#" class="menu-link">Landing</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Pricing</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Payment</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Checkout</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Help Center</a></li>
            </ul>
        </li>
        <li class="menu-item {{ (request()->routeIs('admin.ads.*') || request()->routeIs('admin.posts.*') || request()->routeIs('admin.chats.*') || request()->routeIs('admin.comments.*')) ? 'active open' : '' }}">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-broadcast"></i>
                <div>{{ __('admin.marketing_tools.header') }}</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item  {{ request()->routeIs('admin.ads.*')  ? 'active' : '' }}"><a href="{{route('admin.ads.dashboard')}}" class="menu-link">{{ __('admin.marketing_tools.ads.header') }}</a></li>
                <li class="menu-item {{ request()->routeIs('admin.posts.*')  ? 'active' : '' }}"><a href="{{route('admin.posts.dashboard')}}" class="menu-link">{{ __('admin.marketing_tools.posts.header') }}</a></li>
                <li class="menu-item {{ request()->routeIs('admin.chats.*')  ? 'active' : '' }}"><a href="{{route('admin.chats.dashboard')}}" class="menu-link">{{ __('admin.marketing_tools.chats.header') }}</a></li>
                <li class="menu-item {{ request()->routeIs('admin.comments.*')  ? 'active' : '' }}"><a href="{{route('admin.comments.dashboard')}}" class="menu-link">{{ __('admin.marketing_tools.comments.header') }}</a></li>
            </ul>
        </li>

        {{-- APPS & PAGES --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Apps & Pages</span>
        </li>

        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-envelope me-2"></i>Email</a></li>
        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-chat me-2"></i>Chat</a></li>
        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-calendar me-2"></i>Calendar</a></li>
        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-grid me-2"></i>Kanban</a></li>

        {{-- ACCOUNT --}}
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-dock-top"></i>
                <div>Account Settings</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item"><a href="#" class="menu-link">Account</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Notifications</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Connections</a></li>
            </ul>
        </li>

        {{-- AUTH --}}
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-lock-open-alt"></i>
                <div>Authentications</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item"><a href="#" class="menu-link">Login</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Register</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Forgot Password</a></li>
            </ul>
        </li>

        {{-- COMPONENTS --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Components</span>
        </li>

        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-collection me-2"></i>Cards</a></li>

        {{-- UI --}}
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-box"></i>
                <div>User Interface</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item"><a href="#" class="menu-link">Accordion</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Alerts</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Buttons</a></li>
                <li class="menu-item"><a href="#" class="menu-link">Modals</a></li>
            </ul>
        </li>

        {{-- TABLES --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Forms & Tables</span>
        </li>

        <li class="menu-item"><a href="#" class="menu-link"><i class="bx bx-table me-2"></i>Tables</a></li>

        {{-- SUPPORT --}}
        <li class="menu-item">
            <a href="https://github.com/" target="_blank" class="menu-link">
                <i class="bx bx-support me-2"></i>Support
            </a>
        </li>

        <li class="menu-item">
            <a href="https://docs.example.com" target="_blank" class="menu-link">
                <i class="bx bx-file me-2"></i>Documentation
            </a>
        </li>

    </ul>
</aside>
<!-- / Menu -->