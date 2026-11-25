@extends('layouts.app')
@section('title', __('platform_emails.create.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/platform-emails.css') }}?v={{ filemtime(public_path('assets/css/platform-emails.css')) }}">
@endpush

@section('content')
{{-- v-pre: the page sits inside the #app Vue root. --}}
<div class="account-page" v-pre>
    @include('admin.platform-emails._nav', ['title' => __('platform_emails.create.title'), 'intro' => __('platform_emails.create.intro')])

    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <form class="account-card account-section pe-steps" method="POST" action="{{ route('admin.platform-emails.create.start') }}" id="pe-create">
        @csrf
        <div class="pe-step">
            <h2><span class="pe-step-num">1</span>{{ __('platform_emails.create.module') }}</h2>
            <p>{{ __('platform_emails.create.module_hint') }}</p>
            <div class="pe-tabs" id="pe-modules" role="group" aria-label="{{ __('platform_emails.create.module') }}">
                @foreach($modules as $module)
                    <a href="#" role="button" data-module="{{ $module }}" @class(['active' => $loop->first]) aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ __('notifications.categories.'.$module) }}</a>
                @endforeach
            </div>
        </div>

        <div class="pe-step">
            <h2><span class="pe-step-num">2</span>{{ __('platform_emails.create.event') }}</h2>
            <p>{{ __('platform_emails.create.event_hint') }}</p>
            <div class="pe-choices" id="pe-events">
                @foreach($events as $event)
                    <label class="pe-choice" data-module="{{ $event['module'] }}">
                        <input type="radio" name="key" value="{{ $event['key'] }}" required>
                        <span>{{ $event['name'] }}
                            <small class="pe-mono">{{ $event['key'] }}</small>
                            @if($event['custom'])<small>{{ __('platform_emails.create.already', ['languages' => collect($event['custom'])->map(fn ($l) => __('platform_emails.languages.'.$l))->implode(', ')]) }}</small>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="pe-step">
            <h2><span class="pe-step-num">3</span>{{ __('platform_emails.create.language') }}</h2>
            <div class="pe-choices">
                @foreach(__('platform_emails.languages') as $locale => $label)
                    <label class="pe-choice"><input type="radio" name="locale" value="{{ $locale }}" @checked($loop->first) required> <span>{{ $label }}</span></label>
                @endforeach
            </div>
        </div>

        <p class="pe-note">{{ __('platform_emails.create.note') }}</p>
        <div><button class="btn btn-primary" type="submit">{{ __('platform_emails.create.continue') }}</button></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    // Delegated on document: the #app Vue root re-renders this markup after mounting,
    // so listeners bound to the original elements would be lost.
    const show = (module) => {
        document.querySelectorAll('#pe-modules [data-module]').forEach((c) => {
            const on = c.dataset.module === module;
            c.classList.toggle('active', on);
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.querySelectorAll('#pe-events .pe-choice').forEach((el) => {
            const visible = el.dataset.module === module;
            el.hidden = !visible;
            if (!visible) el.querySelector('input').checked = false;
        });
    };
    document.addEventListener('click', (e) => {
        const chip = e.target.closest('#pe-modules [data-module]');
        if (chip) {
            e.preventDefault();
            show(chip.dataset.module);
        }
    });
    const first = document.querySelector('#pe-modules [data-module]');
    if (first) show(first.dataset.module);
})();
</script>
@endpush
