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
                    ['dashboard', 'Dashboard', 'bx-grid-alt', 'dashboard'],
                    ['subscribers.index', 'Subscribers', 'bx-group', 'subscribers.*'],
                    ['tickets.index', 'Support tickets', 'bx-support', 'tickets.*'],
                ];
                if(auth()->user()->hasRole('admin')) {
                    $links[] = ['employees.index', 'Employees', 'bx-user-plus', 'employees.*'];
                    $links[] = ['plans.index', 'Subscription plans', 'bx-crown', 'plans.*'];
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
