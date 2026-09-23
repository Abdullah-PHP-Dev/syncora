<nav aria-label="{{ __('Language') }}" class="d-flex flex flex-wrap gap-3 mb-4">
    @foreach (['en' => 'English', 'ar' => 'العربية'] as $locale => $label)
        <a href="{{ LaravelLocalization::getLocalizedURL($locale, request()->fullUrl(), [], true) }}"
           lang="{{ $locale }}" hreflang="{{ $locale }}"
           @if(app()->getLocale() === $locale) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</nav>
