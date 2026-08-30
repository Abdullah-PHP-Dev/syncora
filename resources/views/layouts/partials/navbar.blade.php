<nav class="admin-navbar" id="layout-navbar">

    <!-- =====================================================
         LEFT
    ====================================================== -->
    <div class="admin-navbar-left">

        <!-- Desktop Sidebar Toggle -->
        <button type="button"
                class="admin-menu-toggle d-none d-xl-flex"
                id="adminSidebarToggle"
                aria-label="Toggle sidebar"
                title="Toggle sidebar">
            <i class="bx bx-menu"></i>
        </button>

        <!-- Mobile Menu -->
        <button type="button"
                class="admin-menu-toggle d-xl-none"
                aria-label="Open menu"
                data-bs-toggle="offcanvas"
                data-bs-target="#layout-menu">
            <i class="bx bx-menu"></i>
        </button>

        <!-- Search -->
        <div class="admin-search">
            <i class="bx bx-search"></i>

            <input type="text"
                   placeholder="Search..."
                   aria-label="Search">

            <span class="admin-search-shortcut">
                ⌘ K
            </span>
        </div>

    </div>


    <!-- =====================================================
         RIGHT
    ====================================================== -->
    <div class="admin-navbar-right">

        <!-- =================================================
             SUBSCRIPTION STATUS
        ================================================== -->
        @php
            $hasActiveSubscription = isset($subscription)
                && $subscription
                && (
                    !isset($subscription->active)
                    || $subscription->active
                );

            $remainingDays = 0;

            if ($hasActiveSubscription && isset($subscription->remaining_days)) {
                $remainingDays = max(0, (int) $subscription->remaining_days);
            }

            $subscriptionPlan = $hasActiveSubscription
                ? ($subscription->plan_name ?? 'Premium')
                : 'Free Plan';
        @endphp

        <a href="{{ url('admin/subscription/select') }}"
           class="admin-subscription-status {{ !$hasActiveSubscription ? 'is-free' : '' }}">

            @if($hasActiveSubscription)

                @if($remainingDays <= 7)
                    <span class="admin-subscription-dot warning"></span>
                @elseif($remainingDays <= 30)
                    <span class="admin-subscription-dot attention"></span>
                @else
                    <span class="admin-subscription-dot"></span>
                @endif

                <span class="admin-subscription-info">

                    <span class="admin-subscription-plan">
                        {{ $subscriptionPlan }}
                    </span>

                    <span class="admin-subscription-days">
                        @if($remainingDays <= 0)
                            Expired
                        @elseif($remainingDays == 1)
                            1 day left
                        @else
                            {{ $remainingDays }} days left
                        @endif
                    </span>

                </span>

            @else

                <span class="admin-subscription-icon">
                    <i class="bx bx-sparkles"></i>
                </span>

                <span class="admin-subscription-info">

                    <span class="admin-subscription-plan">
                        Free Plan
                    </span>

                    <span class="admin-subscription-days">
                        Upgrade
                    </span>

                </span>

            @endif

        </a>


        <!-- =================================================
             LANGUAGE
        ================================================== -->
        <div class="dropdown">

            <button type="button"
                    class="admin-navbar-icon dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="Language">

                <i class="bx bx-globe"></i>

            </button>

            <ul class="dropdown-menu dropdown-menu-end admin-language-dropdown">

                <li>
                    <a href="#"
                       class="dropdown-item admin-language-item">

                        <span class="admin-language-flag">
                            🇬🇧
                        </span>

                        <span>
                            English
                        </span>

                        @if(app()->getLocale() == 'en')
                            <i class="bx bx-check admin-language-check"></i>
                        @endif

                    </a>
                </li>

                <li>
                    <a href="#"
                       class="dropdown-item admin-language-item">

                        <span class="admin-language-flag">
                            🇸🇦
                        </span>

                        <span>
                            العربية
                        </span>

                        @if(app()->getLocale() == 'ar')
                            <i class="bx bx-check admin-language-check"></i>
                        @endif

                    </a>
                </li>

            </ul>

        </div>


        <!-- =================================================
             NOTIFICATIONS - combined unread Comments + Messages.
             Own Vue root (see resources/js/app.js) - the navbar renders
             outside the #app root's DOM subtree, so this can't be
             registered as a plain component inside that root.
        ================================================== -->
        <div id="notification-center-root">
            <notification-center
                index-url="{{ route('admin.notifications.index') }}"
                comment-read-url-template="{{ route('admin.comments.read', ['comment' => ':id']) }}"
                conversation-read-url-template="{{ route('admin.chats.read', ['conversation' => ':id']) }}"
                current-user-id="{{ auth()->id() }}"
            ></notification-center>
        </div>


        <!-- =================================================
             USER
        ================================================== -->
        <div class="admin-user dropdown">

            <a href="javascript:void(0);"
               class="admin-user-toggle dropdown-toggle"
               data-bs-toggle="dropdown"
               aria-expanded="false">

                <div class="admin-user-avatar">

                    <img src="{{ asset('assets/img/avatars/1.png') }}"
                         alt="User Avatar">

                    <span class="admin-user-online"></span>

                </div>

                <div class="admin-user-info d-none d-sm-flex">

                    <span class="admin-user-name">
                        {{ auth()->user()->name ?? 'Guest User' }}
                    </span>

                    <span class="admin-user-role">
                        {{ auth()->user()->role ?? 'Administrator' }}
                    </span>

                </div>

                <i class="bx bx-chevron-down admin-user-chevron d-none d-sm-block"></i>

            </a>


            <!-- User Dropdown -->
            <ul class="dropdown-menu dropdown-menu-end admin-user-dropdown">

                <!-- Profile Header -->
                <li class="admin-dropdown-profile">

                    <div class="admin-dropdown-profile-inner">

                        <div class="admin-user-avatar admin-user-avatar-lg">

                            <img src="{{ asset('assets/img/avatars/1.png') }}"
                                 alt="User Avatar">

                            <span class="admin-user-online"></span>

                        </div>

                        <div>

                            <div class="admin-dropdown-name">
                                {{ auth()->user()->name ?? 'Guest User' }}
                            </div>

                            <div class="admin-dropdown-role">
                                {{ auth()->user()->role ?? 'Administrator' }}
                            </div>

                        </div>

                    </div>

                </li>


                <li>
                    <hr class="dropdown-divider">
                </li>


                <!-- Profile -->
                <li>

                    <a class="dropdown-item admin-dropdown-item"
                       href="{{ route('admin.profiles.show', ['profile' => Auth::user()->id]) }}">

                        <span class="admin-dropdown-icon">
                            <i class="bx bx-user"></i>
                        </span>

                        <span>
                            {{ __('admin.profile.my_profile') }}
                        </span>

                    </a>

                </li>


                <!-- Settings -->
                <li>

                    <a class="dropdown-item admin-dropdown-item"
                       href="#">

                        <span class="admin-dropdown-icon">
                            <i class="bx bx-cog"></i>
                        </span>

                        <span>
                            {{ __('admin.setting.header') }}
                        </span>

                    </a>

                </li>


                <!-- Billing -->
                <li>

                    <a class="dropdown-item admin-dropdown-item"
                       href="{{ url('admin/subscription/select') }}">

                        <span class="admin-dropdown-icon">
                            <i class="bx bx-credit-card"></i>
                        </span>

                        <span>
                            {{ __('admin.setting.billing_plan') }}
                        </span>

                    </a>

                </li>


                <li>
                    <hr class="dropdown-divider">
                </li>


                <!-- Logout -->
                <li>

                    <a class="dropdown-item admin-dropdown-item admin-logout-item"
                       href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">

                        <span class="admin-dropdown-icon">
                            <i class="bx bx-log-out"></i>
                        </span>

                        <span>
                            {{ __('admin.setting.logout') }}
                        </span>

                    </a>


                    <form id="logout-form"
                          action="{{ route('logout') }}"
                          method="POST"
                          class="d-none">

                        @csrf

                    </form>

                </li>

            </ul>

        </div>

    </div>

</nav>
