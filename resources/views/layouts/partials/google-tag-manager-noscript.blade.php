{{-- Google Tag Manager (noscript). Goes immediately after the opening <body> tag. --}}
@php($gtmId = trim((string) config('seo.google_tag_manager_id')))
@if($gtmId && preg_match('/^GTM-[A-Z0-9]+$/', $gtmId) && app(\App\Support\Seo\MarketingSeo::class)->isProductionHost(request()->getHost()))
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
@endif
