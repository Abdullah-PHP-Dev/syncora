{{-- Google Analytics 4 (gtag.js). Only on the production host (seo.base_url) with
     GOOGLE_ANALYTICS_ID set - labs, staging and local never send data. --}}
@php($gaId = config('seo.google_analytics_id'))
@if($gaId && preg_match('/^G-[A-Z0-9]+$/', $gaId) && app(\App\Support\Seo\MarketingSeo::class)->isProductionHost(request()->getHost()))
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '{{ $gaId }}');
</script>
@endif
