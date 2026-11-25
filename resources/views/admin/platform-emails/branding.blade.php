@extends('layouts.app')
@section('title', __('platform_emails.brand.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/platform-emails.css') }}?v={{ filemtime(public_path('assets/css/platform-emails.css')) }}">
@endpush

@section('content')
{{-- v-pre: the page sits inside the #app Vue root; template text like "{{ plan }}" must stay text. --}}
<div class="account-page" v-pre>
    @include('admin.platform-emails._nav', ['title' => __('platform_emails.brand.title'), 'intro' => __('platform_emails.brand.intro')])

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form class="account-card" method="POST" action="{{ route('admin.platform-emails.branding.update') }}">
        @csrf
        @method('PUT')
        @php
            $field = fn ($name, $type = 'text') => ['name' => $name, 'type' => $type, 'value' => old($name, $brand[$name] ?? '')];
            $groups = [
                'identity' => [$field('app_name'), $field('logo_url', 'url'), $field('website_url', 'url')],
                'sender' => [$field('sender_name'), $field('sender_email', 'email'), $field('reply_to', 'email'), $field('support_email', 'email')],
                'footer' => [$field('footer_text'), $field('copyright')],
            ];
        @endphp
        @foreach($groups as $group => $fields)
        <section class="account-section" @if(!$loop->first) style="border-top:1px solid var(--theme-border)" @endif>
            <div class="account-section-heading"><div><h2>{{ __('platform_emails.brand.'.$group) }}</h2>
                @if($group === 'sender')<p>{{ __('platform_emails.brand.sender_hint') }}</p>@endif
                @if($group === 'footer')<p>{{ __('platform_emails.brand.copyright_hint') }}</p>@endif
            </div></div>
            <div class="account-fields">
                @foreach($fields as $f)
                <div class="account-field">
                    <label for="brand-{{ $f['name'] }}">{{ __('platform_emails.brand.'.$f['name']) }}</label>
                    <input id="brand-{{ $f['name'] }}" class="form-control" type="{{ $f['type'] }}" name="{{ $f['name'] }}" value="{{ $f['value'] }}" dir="auto">
                </div>
                @endforeach
            </div>
        </section>
        @endforeach

        <section class="account-section" style="border-top:1px solid var(--theme-border)">
            <div class="account-section-heading"><div><h2>{{ __('platform_emails.brand.colors') }}</h2></div></div>
            <div class="account-fields">
                @foreach(['primary_color', 'secondary_color'] as $name)
                <div class="account-field">
                    <label for="brand-{{ $name }}">{{ __('platform_emails.brand.'.$name) }}</label>
                    <div class="pe-color">
                        <input type="color" value="{{ old($name, $brand[$name]) }}" oninput="document.getElementById('brand-{{ $name }}').value = this.value" aria-hidden="true" tabindex="-1">
                        <input id="brand-{{ $name }}" class="form-control" name="{{ $name }}" value="{{ old($name, $brand[$name]) }}" pattern="#[0-9a-fA-F]{6}" dir="ltr">
                    </div>
                </div>
                @endforeach
            </div>
        </section>

        <section class="account-section" style="border-top:1px solid var(--theme-border)">
            <div class="account-section-heading"><div><h2>{{ __('platform_emails.brand.social') }}</h2></div></div>
            <div class="account-fields">
                @foreach($social as $network)
                <div class="account-field">
                    <label for="brand-social-{{ $network }}">{{ ucfirst($network) }}</label>
                    <input id="brand-social-{{ $network }}" class="form-control" type="url" name="social[{{ $network }}]" value="{{ old('social.'.$network, $brand['social'][$network] ?? '') }}" placeholder="https://" dir="ltr">
                </div>
                @endforeach
            </div>
        </section>

        <footer class="account-footer"><span></span>
            <button class="btn btn-primary account-save" type="submit"><i class="bx bx-check" aria-hidden="true"></i><span>{{ __('platform_emails.brand.save') }}</span></button>
        </footer>
    </form>
</div>
@endsection
