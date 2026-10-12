@props(['profiles' => null])

<div {{ $attributes->class(['theme-footer-social']) }} aria-label="{{ __('Follow Socialeaz') }}">
    @foreach($profiles ?? config('social', []) as $platform => $url)
        @php
            $validUrl = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
        @endphp
        @if($validUrl)
            <a class="theme-footer-social-icon theme-footer-social-{{ strtolower($platform) }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Socialeaz on :platform', ['platform' => $platform]) }}" title="{{ $platform }}">@include('front.partials.social-brand-icon', ['platform' => $platform])</a>
        @else
            <span class="theme-footer-social-icon theme-footer-social-{{ strtolower($platform) }}" role="img" aria-label="{{ $platform }}">@include('front.partials.social-brand-icon', ['platform' => $platform])</span>
        @endif
    @endforeach
</div>
