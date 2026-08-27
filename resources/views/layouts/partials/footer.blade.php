<footer class="admin-footer">

    <div class="admin-footer-inner">

        {{-- Left --}}
        <div class="admin-footer-left">

            <div class="admin-footer-brand">
                <span class="admin-footer-brand-icon">
                    <img src="{{ asset('assets/img/logo/socialeaz-logo-32.png') }}" alt="{{ config('app.name') }}">
                </span>

                <span class="admin-footer-brand-name">
                    {{ config('app.name') }}
                </span>
            </div>

            <span class="admin-footer-divider"></span>

            <span class="admin-footer-copyright">
                © {{ date('Y') }} {{ config('app.name') }}
            </span>

        </div>


        {{-- Right --}}
        <div class="admin-footer-right">

            <a href="{{ route('terms') }}"
               class="admin-footer-link">
                <i class="bx bx-file"></i>
                <span>Terms</span>
            </a>

            <a href="{{ route('privacy') }}"
               class="admin-footer-link">
                <i class="bx bx-shield-quarter"></i>
                <span>Privacy</span>
            </a>

            <span class="admin-footer-divider"></span>

            {{-- System Status --}}
            <div class="admin-footer-status">

                <span class="admin-footer-status-dot"></span>

                <span>
                    All systems operational
                </span>

            </div>

            {{-- Version --}}
            <span class="admin-footer-version">
                v{{ config('app.version', '1.0.0') }}
            </span>

        </div>

    </div>

</footer>