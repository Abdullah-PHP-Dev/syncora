<div class="bg-dark text-white">

    <div class="container py-2">

        <div class="d-flex justify-content-center align-items-center gap-2 small flex-wrap">

            <span class="badge bg-white text-dark rounded-pill fw-bold">
                NEW
            </span>

            <span>
                Meet Copilot — turn one idea into a complete social campaign.
            </span>

            <a
                    href="{{ route('ai-copilot') }}"
                    class="text-white text-decoration-underline fw-semibold"
            >
                Try it free →
            </a>

        </div>

    </div>

</div>


{{-- =========================================================
     NAVIGATION
========================================================= --}}

<header class="sticky-top border-bottom glass-nav">

    <nav class="navbar navbar-expand-lg navbar-socialeaz">

        <div class="container">

            {{-- Logo --}}

            <a
                    href="{{ route('home') }}"
                    class="d-flex align-items-center gap-2 text-dark fw-900 fs-5 text-decoration-none"
            >

                <span class="brand-logo">
                    <img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz">
                </span>

                Socialeaz

            </a>


            {{-- Desktop Navigation --}}

            <div class="desktop-nav d-none d-lg-flex align-items-center gap-4 ms-auto">

                <a
                        href="{{ route('product') }}"
                        class="nav-link-custom"
                >
                    Product
                </a>

                <a
                        href="{{ route('ai-copilot') }}"
                        class="nav-link-custom"
                >
                    AI Copilot
                </a>

                <a
                        href="{{ route('channels') }}"
                        class="nav-link-custom"
                >
                    Channels
                </a>

                <a
                        href="{{ route('tools') }}"
                        class="nav-link-custom"
                >
                    Tools
                </a>

                <a
                        href="{{ route('pricing') }}"
                        class="nav-link-custom"
                >
                    Pricing
                </a>


                <a
                        href="{{ route('login') }}"
                        class="ms-3 px-3 py-2 text-dark small fw-semibold text-decoration-none"
                >
                    Log in
                </a>


                <a
                        href="{{ route('register') }}"
                        class="btn btn-black btn-socialeaz"
                >
                    Get started
                </a>

            </div>


            {{-- Mobile Toggle --}}

            <button
                    class="navbar-toggler border-0 shadow-none d-lg-none"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#mobileNavigation"
                    aria-controls="mobileNavigation"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
            >

                <i class="bi bi-list fs-2"></i>

            </button>

        </div>

    </nav>


    {{-- Mobile Navigation --}}

    <div
            class="collapse"
            id="mobileNavigation"
    >

        <div class="container py-4">

            <div class="d-flex flex-column gap-3">

                <a
                        href="{{ route('product') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    Product
                </a>

                <a
                        href="{{ route('ai-copilot') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    AI Copilot
                </a>

                <a
                        href="{{ route('channels') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    Channels
                </a>

                <a
                        href="{{ route('tools') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    Tools
                </a>

                <a
                        href="{{ route('pricing') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    Pricing
                </a>

                <hr>

                <a
                        href="{{ route('login') }}"
                        class="fw-semibold text-decoration-none text-dark"
                >
                    Log in
                </a>

                <a
                        href="{{ route('register') }}"
                        class="btn btn-black btn-socialeaz text-center"
                >
                    Get started
                </a>

            </div>

        </div>

    </div>

</header>