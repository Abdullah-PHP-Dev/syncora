<aside id="layout-menu" class="layout-menu menu-vertical admin-sidebar">

    {{-- =========================================================
         SIDEBAR HEADER
    ========================================================== --}}
    <div class="admin-sidebar-header">

        <a href="{{ LaravelLocalization::getLocalizedURL(app()->getLocale(), route('dashboard'), [], true) }}" class="admin-sidebar-brand">

            <img class="admin-sidebar-logo" src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="145" height="48">
            <img class="admin-sidebar-logo-compact" src="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}" alt="Socialeaz" width="34" height="34">

        </a>

        <button type="button"
                class="admin-sidebar-collapse layout-menu-toggle"
                aria-label="Toggle sidebar"
                title="Collapse sidebar">

            <i class="bx bx-chevron-left"></i>

        </button>

    </div>


    <div class="admin-sidebar-body">
        <ul class="menu-inner admin-sidebar-menu">
            @php
                $links = [
                    ['dashboard.overview', 'overview.title', 'bx-bar-chart-alt-2', 'dashboard.overview'],
                    ['dashboard', 'Dashboard', 'bx-grid-alt', 'dashboard'],
                    ['subscribers.index', 'Subscribers', 'bx-group', 'subscribers.*'],
                    ['admin.tickets.index', 'Support tickets', 'bx-support', 'admin.tickets.*'],
                ];
                if(auth()->user()->hasRole('admin')) {
                    // FAQ management previously had no link anywhere in this
                    // (the real, actually-shown-to-admins) sidebar at all -
                    // only reachable by typing the URL directly.
                    $links[] = ['admin.faqs.index', 'FAQ Management', 'bx-help-circle', 'admin.faqs.*'];
                    $links[] = ['employees.index', 'Employees', 'bx-user-plus', 'employees.*'];
                    $links[] = ['plans.index', 'Subscription plans', 'bx-crown', 'plans.*'];
                    $links[] = ['admin.apis.index', 'API Integrations', 'bx-code-alt', 'admin.apis.*'];
                }
            @endphp
            @foreach($links as [$route, $label, $icon, $match])
                <li class="menu-item {{ request()->routeIs($match) ? 'active' : '' }}">
                    <a href="{{ route($route) }}" class="menu-link">
                        <i class="menu-icon tf-icons bx {{ $icon }}"></i><span>{{ __($label) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="admin-sidebar-footer"><span>{{ auth()->user()->hasRole('admin') ? __('Social Eaz team') : __('Customer support') }}</span></div>
</aside>
