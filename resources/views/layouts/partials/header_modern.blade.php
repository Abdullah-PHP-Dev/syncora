<header class="theme-header">
    <div class="theme-nav">
        <a href="{{ route('home') }}" class="theme-brand" aria-label="{{ __('Socialeaz home') }}">
            <img src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="145" height="40">
        </a>
        <nav class="theme-links" aria-label="{{ __('Primary navigation') }}">
            <details class="theme-product-dropdown" data-product-dropdown>
                <summary>{{ __('Product') }} <span class="theme-dropdown-chevron" aria-hidden="true">@include('front.partials.home-icon', ['icon' => 'chevron-down'])</span></summary>
                <div class="theme-product-menu">
                    <p class="theme-product-menu-label">{{ __('EXPLORE SOCIALEAZ') }}</p>
                    <div class="theme-product-menu-grid">
                        @foreach ([
                            ['spark', 'purple', 'Content Creation', 'Craft and customize captions and visuals for every platform.', route('product.content-creation')],
                            ['spark', 'blue', 'AI Assistant', 'Use our AI assistant to plan your social media strategy.', route('product.ai-assistant')],
                            ['team', 'pink', 'Engagement', 'Manage your social mentions, comments and DMs efficiently.', route('product.engagement')],
                            ['chart', 'green', 'Analytics', 'Track your social performance and generate detailed PDF reports.', route('product.analytics')],
                            ['calendar', 'blue', 'Scheduling & publishing', 'Schedule and repurpose your content for all your accounts.', route('product.scheduling-publishing')],
                            ['team', 'purple', 'Integrations', 'Find all Socialeaz integrations with your favorite platforms.', route('product.integrations')],
                            ['team', 'pink', 'Collaboration', 'Invite your team, provide notes and approve posts before publishing.', route('product.collaboration')],
                        ] as [$icon, $color, $title, $description, $url])
                            <a class="theme-product-menu-item" href="{{ $url }}">
                                <span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span>
                                <span class="theme-product-menu-copy"><strong>{{ __($title) }}</strong><small>{{ __($description) }}</small></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </details>
            <details class="theme-product-dropdown" data-solutions-dropdown>
                <summary>{{ __('Solutions') }} <span class="theme-dropdown-chevron" aria-hidden="true">@include('front.partials.home-icon', ['icon' => 'chevron-down'])</span></summary>
                <div class="theme-product-menu">
                    <p class="theme-product-menu-label">{{ __('FIND YOUR WORKFLOW') }}</p>
                    <div class="theme-product-menu-grid">
                        @foreach(config('marketing-solutions') as $slug => $solution)
                            <a class="theme-product-menu-item" href="{{ route('solutions.'.$slug) }}">
                                <span class="theme-icon theme-icon-{{ $solution['color'] }}">@include('front.partials.home-icon', ['icon' => $solution['icon']])</span>
                                <span class="theme-product-menu-copy"><strong>{{ $solution['title'][app()->getLocale()] ?? $solution['title']['en'] }}</strong><small>{{ $solution['menu'][app()->getLocale()] ?? $solution['menu']['en'] }}</small></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </details>
            <a href="{{ route('services') }}" @if(request()->routeIs('services')) aria-current="page" @endif>{{ __('Services') }}</a>
            <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
            <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>{{ __('Contact') }}</a>
        </nav>
        <div class="theme-nav-actions">
            <a href="{{ route('login') }}">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="theme-button theme-button-primary theme-button-small">{{ __('Get started') }} <span>→</span></a>
        </div>
        <details class="theme-language-dropdown">
            <summary aria-label="{{ __('Choose language') }}">{{ app()->getLocale() === 'ar' ? 'العربية' : 'EN' }} <span class="theme-dropdown-chevron" aria-hidden="true">@include('front.partials.home-icon', ['icon' => 'chevron-down'])</span></summary>
            <nav aria-label="{{ __('Language') }}">
                @foreach (['en' => 'English', 'ar' => 'العربية'] as $locale => $label)
                    <a href="{{ LaravelLocalization::getLocalizedURL($locale, request()->fullUrl(), [], true) }}"
                       lang="{{ $locale }}" hreflang="{{ $locale }}"
                       @if(app()->getLocale() === $locale) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </details>
    </div>
</header>
