@php
    $seo = app(\App\Support\Seo\MarketingSeo::class);
    $seoRoute = request()->route()?->getName() ?? '';
    $seoLocale = app()->getLocale();
    $seoMetadata = $seo->metadata($seoRoute, $seoLocale);
    $seoTitle = $seoMetadata['title'] ?? trim($__env->yieldContent('title', 'Socialeaz'));
    $seoDescription = $seoMetadata['description'] ?? trim($__env->yieldContent('meta_description', __('AI-powered social media workspace for creators, teams and agencies.')));
    $seoCanonical = $seo->localizedUrl($seoRoute, $seoLocale);
    $seoIndexable = $seoCanonical && $seo->indexableHost(request()->getHost());
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoIndexable ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
@if($seoCanonical)
<link rel="canonical" href="{{ $seoCanonical }}">
@foreach(config('seo.locales') as $locale)
<link rel="alternate" hreflang="{{ $locale }}" href="{{ $seo->localizedUrl($seoRoute, $locale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $seo->localizedUrl($seoRoute, config('seo.default_locale')) }}">
@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="Socialeaz">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:locale" content="{{ $seoLocale === 'ar' ? 'ar_AR' : 'en_US' }}">
<meta property="og:locale:alternate" content="{{ $seoLocale === 'ar' ? 'en_US' : 'ar_AR' }}">
@if($seoCanonical)<meta property="og:url" content="{{ $seoCanonical }}">@endif
<meta property="og:image" content="{{ $seoMetadata['image'] }}">
<meta property="og:image:alt" content="{{ $seoTitle }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoMetadata['image'] }}">
@if(config('seo.google_verification'))<meta name="google-site-verification" content="{{ config('seo.google_verification') }}">@endif
@if(config('seo.bing_verification'))<meta name="msvalidate.01" content="{{ config('seo.bing_verification') }}">@endif
@if($seoCanonical)
<script type="application/ld+json">{!! json_encode($seo->schema($seoRoute, $seoLocale, $seoTitle, $seoDescription), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
