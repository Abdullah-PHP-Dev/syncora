<footer class="footer py-5">

    <div class="container">

        <div class="row g-5">

            {{-- Brand --}}

            <div class="col-lg-5">

                <a
                        href="{{ route('home') }}"
                        class="d-flex align-items-center gap-2 fw-900 fs-5 text-white text-decoration-none"
                >

                    <span
                            class="brand-logo"
                            style="background:#fff;color:#000;"
                    >
                        S
                    </span>

                    Socialeaz

                </a>

                <p
                        class="text-white-50 small mt-3"
                        style="max-width:420px;line-height:1.7;"
                >
                    The AI-powered social media workspace for creators,
                    teams and agencies.
                </p>

            </div>


            {{-- Product --}}

            <div class="col-6 col-lg-2">

                <div class="fw-bold small mb-3">
                    Product
                </div>

                <div class="d-flex flex-column gap-2">

                    <a
                            href="{{ route('product') }}"
                            class="footer-link"
                    >
                        Features
                    </a>

                    <a
                            href="{{ route('ai-copilot') }}"
                            class="footer-link"
                    >
                        AI Copilot
                    </a>

                    <a
                            href="{{ route('channels') }}"
                            class="footer-link"
                    >
                        Channels
                    </a>

                    <a
                            href="{{ route('pricing') }}"
                            class="footer-link"
                    >
                        Pricing
                    </a>

                </div>

            </div>


            {{-- Resources --}}

            <div class="col-6 col-lg-2">

                <div class="fw-bold small mb-3">
                    Resources
                </div>

                <div class="d-flex flex-column gap-2">

                    <a
                            href="{{ route('tools') }}"
                            class="footer-link"
                    >
                        Free Tools
                    </a>

                    <a
                            href="{{ route('guides') }}"
                            class="footer-link"
                    >
                        Guides
                    </a>

                    <a
                            href="{{ route('help') }}"
                            class="footer-link"
                    >
                        Help Center
                    </a>

                    <a
                            href="{{ route('api') }}"
                            class="footer-link"
                    >
                        API
                    </a>

                </div>

            </div>


            {{-- Company --}}

            <div class="col-6 col-lg-2">

                <div class="fw-bold small mb-3">
                    Company
                </div>

                <div class="d-flex flex-column gap-2">

                    <a
                            href="{{ route('about') }}"
                            class="footer-link"
                    >
                        About
                    </a>

                    <a
                            href="{{ route('contact') }}"
                            class="footer-link"
                    >
                        Contact
                    </a>

                    <a
                            href="{{ route('privacy') }}"
                            class="footer-link"
                    >
                        Privacy
                    </a>

                    <a
                            href="{{ route('terms') }}"
                            class="footer-link"
                    >
                        Terms
                    </a>

                </div>

            </div>

        </div>


        {{-- Bottom --}}

        <div
                class="border-top border-secondary border-opacity-25 mt-5 pt-4
                   d-flex flex-column flex-sm-row
                   justify-content-between gap-2"
        >

            <span class="small text-white-50">
                © {{ date('Y') }} Socialeaz. All rights reserved.
            </span>

            <span class="small text-white-50">
                Built for modern social teams.
            </span>

        </div>

    </div>

</footer>