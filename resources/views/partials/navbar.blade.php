<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
    <div class="container">

        <a class="navbar-brand fw-bold" href="/">
            Syncora
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link" href="/">{{ __('nav.home') }}</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/about">{{ __('nav.about') }}</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/services">{{ __('nav.services') }}</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/product">{{ __('nav.product') }}</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/pricing">{{ __('nav.pricing') }}</a>
                </li>

            </ul>

            <div class="d-flex gap-2">
                <div class="dropdown ms-3">

                    <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        🌐 {{ app()->getLocale() }}
                    </button>

                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item" href="{{ LaravelLocalization::getLocalizedURL('en') }}">
                                English
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item" href="{{ LaravelLocalization::getLocalizedURL('ar') }}">
                                العربية
                            </a>
                        </li>

                    </ul>

                </div>
                <a href="/login" class="btn btn-outline-light btn-sm">
                    {{ __('nav.login') }}
                </a>

                <a href="/register" class="btn btn-primary btn-sm">
                    {{ __('nav.register') }}
                </a>

            </div>

        </div>
    </div>
</nav>