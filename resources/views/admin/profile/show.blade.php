@extends('layouts.app')
@section('title', __('profile.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
@endpush

@section('content')
@php($account = Auth::user())
<div class="account-page">
    <header class="account-heading">
        <span class="account-eyebrow">{{ __('profile.settings') }}</span>
        <h1>{{ __('profile.title') }}</h1>
        <p>{{ __('profile.description') }}</p>
    </header>

    <div class="account-layout">
        <aside class="account-sidebar">
            <section class="account-card account-summary">
                <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}</span>
                <h2 data-profile-name>{{ $account->name }}</h2>
                <p class="account-email">{{ $account->email }}</p>
                <span class="account-badge"><i class="bi bi-person-check" aria-hidden="true"></i>{{ __('profile.personal_account') }}</span>
                <div class="account-summary-note"><i class="bi bi-shield-check" aria-hidden="true"></i><p>{{ __('profile.summary_note') }}</p></div>
            </section>
            <div class="account-tip"><i class="bi bi-shield-lock" aria-hidden="true"></i><h3>{{ __('profile.security_tip') }}</h3><p>{{ __('profile.security_help') }}</p></div>
        </aside>

        <form id="profile" class="account-card account-form" method="POST" action="{{ route('admin.profiles.update', ['profile' => $account->id]) }}">
            @csrf
            @method('PUT')
            <section aria-labelledby="personal-heading" class="account-section">
                <div class="account-section-heading"><span class="account-icon"><i class="bi bi-person" aria-hidden="true"></i></span><div><h2 id="personal-heading">{{ __('profile.personal_details') }}</h2><p>{{ __('profile.personal_help') }}</p></div></div>
                <div class="account-fields">
                    <div class="account-field">
                        <label for="profile-name">{{ __('admin.profile.name') }} <span aria-hidden="true">*</span></label>
                        <input id="profile-name" type="text" name="name" class="form-control" value="{{ old('name', $account->name) }}" required maxlength="255" autocomplete="name" aria-describedby="error-name">
                        <p id="error-name" class="error-message error-name" aria-live="polite"></p>
                    </div>
                    <div class="account-field">
                        <label for="mobile">{{ __('profile.mobile') }}</label>
                        <input type="tel" id="mobile" name="mobile" class="form-control" value="{{ old('mobile', $account->mobile) }}" autocomplete="tel" dir="ltr" placeholder="+966500000000" maxlength="16" pattern="\+?[0-9]{9,15}" aria-describedby="mobile-help error-mobile">
                        <small id="mobile-help">{{ __('profile.mobile_help') }}</small>
                        <p id="error-mobile" class="error-message error-mobile" aria-live="polite"></p>
                    </div>
                    <div class="account-field account-field-wide">
                        <label for="profile-email">{{ __('admin.profile.email') }} <span class="account-readonly"><i class="bi bi-lock" aria-hidden="true"></i>{{ __('profile.read_only') }}</span></label>
                        <input id="profile-email" type="email" name="email" class="form-control" value="{{ $account->email }}" readonly autocomplete="email" aria-describedby="email-help">
                        <small id="email-help">{{ __('profile.email_help') }}</small>
                    </div>
                </div>
            </section>

            <section aria-labelledby="security-heading" class="account-section account-security">
                <div class="account-section-heading"><span class="account-icon"><i class="bi bi-key" aria-hidden="true"></i></span><div><h2 id="security-heading">{{ __('profile.password_title') }}</h2><p>{{ __('profile.password_help') }}</p></div></div>
                <div class="account-fields">
                    <div class="account-field">
                        <label for="profile-password">{{ __('profile.new_password') }}</label>
                        <input id="profile-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="6" aria-describedby="error-password">
                        <p id="error-password" class="error-message error-password" aria-live="polite"></p>
                    </div>
                    <div class="account-field">
                        <label for="profile-password-confirmation">{{ __('admin.profile.confirm_password') }}</label>
                        <input id="profile-password-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" aria-describedby="error-password_confirmation">
                        <p id="error-password_confirmation" class="error-message error-password_confirmation" aria-live="polite"></p>
                    </div>
                </div>
            </section>
            <footer class="account-footer"><span role="status" id="profile-status">{{ __('profile.save_hint') }}</span><button type="submit" class="btn btn-primary account-save"><i class="bi bi-check2" aria-hidden="true"></i><span>{{ __('profile.save') }}</span></button></footer>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.profileMessages = @json(__('profile.messages'));
</script>
<script src="{{ asset('assets/js/admin/profile.js') }}?v={{ filemtime(public_path('assets/js/admin/profile.js')) }}"></script>
@endpush
