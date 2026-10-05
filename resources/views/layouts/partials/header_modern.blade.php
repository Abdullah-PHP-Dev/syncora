<header class="theme-header">
    <div class="theme-nav">
        <a href="{{ route('home') }}" class="theme-brand" aria-label="Socialeaz home">
            <img src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="145" height="40">
        </a>
        <nav class="theme-links" aria-label="Primary navigation">
            <a href="#product">Product <span>⌄</span></a>
            <a href="#solutions">Solutions <span>⌄</span></a>
            <a href="{{ route('help') }}">Resources <span>⌄</span></a>
            <a href="{{ route('pricing') }}">Pricing</a>
        </nav>
        <div class="theme-nav-actions">
            <a href="{{ route('login') }}">Log in</a>
            <a href="{{ route('register') }}" class="theme-button theme-button-primary theme-button-small">Get started <span>→</span></a>
        </div>
    </div>
</header>
