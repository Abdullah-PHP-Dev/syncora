@props(['exclude' => null, 'headingId' => 'related-products-title'])
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/related-products.css') }}?v={{ filemtime(public_path('assets/css/related-products.css')) }}">
    @endpush
@endonce

    <section {{ $attributes->class(['feature-related']) }} aria-labelledby="{{ $headingId }}">
        <div class="feature-section-heading"><p class="theme-eyebrow">{{ __('EXPLORE SOCIALEAZ') }}</p><h2 id="{{ $headingId }}">{{ __('Better together in one workspace.') }}</h2></div>
        <div class="feature-related-grid">
            @foreach(config('marketing-products') as $slug => $related)
                @if($slug !== $exclude)
                @php
                    $relatedStyles = [
                        'content-creation' => ['purple', 'spark'],
                        'ai-assistant' => ['blue', 'spark'],
                        'engagement' => ['pink', 'team'],
                        'analytics' => ['green', 'chart'],
                        'scheduling-publishing' => ['amber', 'calendar'],
                        'integrations' => ['cyan', 'link'],
                        'collaboration' => ['violet', 'team'],
                    ];
                    [$relatedColor, $relatedIcon] = $relatedStyles[$slug];
                @endphp
                <a class="feature-related-card feature-related-card-{{ $relatedColor }}" href="{{ route('product.'.$slug) }}">
                    <span class="theme-icon theme-icon-{{ $relatedColor }}">@include('front.partials.home-icon', ['icon' => $relatedIcon])</span>
                    <strong>{{ $related['title'][app()->getLocale()] ?? $related['title']['en'] }}</strong>
                    <span class="feature-related-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
                </a>
                @endif
            @endforeach
        </div>
    </section>
