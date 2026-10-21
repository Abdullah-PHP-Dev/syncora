<footer class="theme-footer theme-page">
    <div class="theme-footer-brand"><a href="{{ route('home') }}"><img src="{{ asset('assets/images/home-theme/logo.webp') }}" alt="Socialeaz" width="145" height="40"></a><p>{{ __('The AI-powered social media workspace') }}<br>{{ __('for creators, teams and agencies.') }}</p><x-footer-social-links /></div>
    <div>
        <h2>{{ __('Product') }}</h2>
        @foreach(config('marketing-products') as $slug => $product)
            <a href="{{ route('product.'.$slug) }}">{{ $product['title'][app()->getLocale()] ?? $product['title']['en'] }}</a>
        @endforeach
    </div>
    <div><h2>{{ __('Solutions') }}</h2>@foreach(config('marketing-solutions') as $slug => $solution)<a href="{{ route('solutions.'.$slug) }}">{{ $solution['title'][app()->getLocale()] ?? $solution['title']['en'] }}</a>@endforeach</div>
    <div><h2>{{ __('Resources') }}</h2><a href="{{ route('help') }}">{{ __('Help Center') }}</a><a href="{{ route('services') }}">{{ __('Services') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a><a href="{{ route('pricing') }}">{{ __('Pricing') }}</a></div>
    <div><h2>{{ __('Company') }}</h2><a href="{{ route('about') }}">{{ __('About Us') }}</a><a href="{{ route('contact') }}">{{ __('Contact') }}</a><a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a><a href="{{ route('terms') }}">{{ __('Terms of Service') }}</a></div>
    <p class="theme-copyright">© {{ date('Y') }} {{ __('Socialeaz. All rights reserved.') }}</p>
</footer>
