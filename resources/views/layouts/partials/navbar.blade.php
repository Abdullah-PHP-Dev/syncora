<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
     id="layout-navbar">

    <!-- Menu Toggle -->
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base bx bx-menu icon-md"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">

        <!-- Search -->
        <div class="navbar-nav align-items-center me-auto">
            <div class="nav-item d-flex align-items-center">
                <span class="w-px-22 h-px-22">
                    <i class="icon-base bx bx-search icon-md"></i>
                </span>

                <input type="text"
                       class="form-control border-0 shadow-none ps-1 ps-sm-2 d-md-block d-none"
                       placeholder="Search..."
                       aria-label="Search..." />
            </div>
        </div>

        <!-- Right Menu -->
        <ul class="navbar-nav flex-row align-items-center ms-md-auto">

            <!-- GitHub Button -->
            <li class="nav-item lh-1 me-4">
                <a class="github-button"
                   href="https://github.com/themeselection/sneat-bootstrap-html-admin-template-free"
                   data-icon="octicon-star"
                   data-size="large"
                   data-show-count="true"
                   aria-label="Star Sneat on GitHub">
                    Star
                </a>
            </li>

            <!-- User Dropdown -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">

                <a class="nav-link dropdown-toggle hide-arrow p-0"
                   href="javascript:void(0);"
                   data-bs-toggle="dropdown">

                    <div class="avatar avatar-online">
                        <img src="{{ asset('assets/img/avatars/1.png') }}"
                             alt="User Avatar"
                             class="w-px-40 h-auto rounded-circle" />
                    </div>

                </a>

                <ul class="dropdown-menu dropdown-menu-end">

                    <!-- User Info -->
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="d-flex">

                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="{{ asset('assets/img/avatars/1.png') }}"
                                             class="w-px-40 h-auto rounded-circle"
                                             alt="User Avatar" />
                                    </div>
                                </div>

                                <div class="flex-grow-1">
                                    <h6 class="mb-0">
                                        {{ auth()->user()->name ?? 'Guest User' }}
                                    </h6>
                                    <small class="text-body-secondary">
                                        {{ auth()->user()->role ?? 'User' }}
                                    </small>
                                </div>

                            </div>
                        </a>
                    </li>

                    <li><div class="dropdown-divider my-1"></div></li>

                    <!-- Profile -->
                    <li>
                        <a class="dropdown-item" href="{{route('admin.profiles.show', ['profile' => Auth::user()->id])}}">
                            <i class="icon-base bx bx-user icon-md me-3"></i>
                            <span>{{ __('admin.profile.my_profile') }}</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="icon-base bx bx-cog icon-md me-3"></i>
                            <span>{{ __('admin.setting.header') }}</span>
                        </a>
                    </li>

                    <!-- Billing -->
                    <li>
                        <a class="dropdown-item" href="{{url('admin/subscription/select')}}">
                            <span class="d-flex align-items-center">
                                <i class="icon-base bx bx-credit-card icon-md me-3"></i>
                                <span class="flex-grow-1">{{ __('admin.setting.billing_plan') }}</span>

                            </span>
                        </a>
                    </li>

                    <li><div class="dropdown-divider my-1"></div></li>

                    <!-- Logout -->
                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">

                            <i class="icon-base bx bx-power-off icon-md me-3"></i>
                            <span>{{ __('admin.setting.logout') }}</span>
                        </a>

                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>

                </ul>
            </li>

        </ul>
    </div>
</nav>