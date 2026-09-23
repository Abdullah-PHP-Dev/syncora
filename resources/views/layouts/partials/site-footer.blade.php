{{-- =========================================================
     PUBLIC SITE FOOTER
     Styled via .footer / .footer-link / .brand-logo in socialeaz.css
     (kept separate from layouts.partials.footer, which is the admin
     dashboard footer styled via socialeaz-admin.css and not loaded
     on the public marketing pages).
========================================================= --}}

<footer class="footer py-5 mt-5">

    <div class="container">

        <div class="row gy-4 align-items-start">

            <div class="col-lg-4">

                <a
                        href="{{ route('home') }}"
                        class="d-inline-flex align-items-center gap-2 text-white text-decoration-none fw-900 fs-5"
                >
                    <span class="brand-logo">
                        <img src="{{ asset('assets/img/logo/socialeaz-logo-64.png') }}" alt="Socialeaz">
                    </span>
                    Socialeaz
                </a>

                <p class="mt-3 mb-0" style="color:rgba(255,255,255,.55);font-size:14px;max-width:320px;">
                    The AI-powered social media workspace for creators, teams and agencies.
                </p>

            </div>

            <div class="col-6 col-lg-2">

                <div class="text-uppercase fw-bold mb-3" style="color:rgba(255,255,255,.35);letter-spacing:.08em;font-size:11px;">
                    Product
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('product') }}" class="footer-link">Product</a>
                    <a href="{{ route('ai-copilot') }}" class="footer-link">AI Copilot</a>
                    <a href="{{ route('channels') }}" class="footer-link">Channels</a>
                    <a href="{{ route('tools') }}" class="footer-link">Tools</a>
                    <a href="{{ route('pricing') }}" class="footer-link">Pricing</a>
                </div>

            </div>

            <div class="col-6 col-lg-2">

                <div class="text-uppercase fw-bold mb-3" style="color:rgba(255,255,255,.35);letter-spacing:.08em;font-size:11px;">
                    Account
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('login') }}" class="footer-link">Log in</a>
                    <a href="{{ route('register') }}" class="footer-link">Get started</a>
                </div>

            </div>

            <div class="col-6 col-lg-2">

                <div class="text-uppercase fw-bold mb-3" style="color:rgba(255,255,255,.35);letter-spacing:.08em;font-size:11px;">
                    Legal
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('terms') }}" class="footer-link">Terms</a>
                    <a href="{{ route('privacy') }}" class="footer-link">Privacy</a>
                </div>

            </div>

        </div>

        <hr style="border-color:rgba(255,255,255,.1);" class="my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">

            <span style="color:rgba(255,255,255,.4);font-size:12px;" class="text-center text-md-start">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                <br>
                {{-- Meta's Business Verification requires the registered
                     legal business name to appear on the business's own
                     website - without it, verification is rejected with
                     "We can't verify your business website is associated
                     with the business ... because your legal business
                     name must be present on the website." Plain, crawlable
                     text (not an image) so Meta's automated check can find
                     it. dir="rtl" keeps the Arabic name's own punctuation/
                     spacing correct regardless of the surrounding page's
                     (English, LTR) direction. --}}
                Legal Business Name: <span dir="rtl" lang="ar">شركة همايون اي تي سوليوشنز العربية لتقنية المعلومات</span>
            </span>

            <span
                    class="d-inline-flex align-items-center gap-2"
                    style="color:rgba(255,255,255,.4);font-size:12px;"
            >
                <span
                        class="rounded-circle"
                        style="width:6px;height:6px;background:#22c55e;display:inline-block;"
                ></span>
                All systems operational
            </span>

        </div>

    </div>

</footer>
