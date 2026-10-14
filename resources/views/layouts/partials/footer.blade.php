<footer class="admin-footer">

    <div class="admin-footer-inner">

        {{-- Left --}}
        <div class="admin-footer-left">

            <div class="admin-footer-brand">
                <img class="admin-footer-logo" src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="105" height="35">
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
                    {{ __('All systems operational') }}
                </span>

            </div>

            {{-- Version --}}
            <span class="admin-footer-version">
                v{{ config('app.version', '1.0.0') }}
            </span>

        </div>

    </div>

</footer>
