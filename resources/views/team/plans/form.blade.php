@extends('layouts.app')
@section('title', $plan->exists ? __('Edit plan') : __('Create plan'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/admin-plans.css') }}?v={{ filemtime(public_path('assets/css/admin-plans.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/package-editor.css') }}?v={{ filemtime(public_path('assets/css/package-editor.css')) }}">
@endpush
@section('content')
<div class="plans-page package-editor">
    <a class="package-back" href="{{ route('plans.index') }}"><i class="bi bi-arrow-return-left" aria-hidden="true"></i>{{ __('Back to plans') }}</a>
    <header class="plans-heading"><div><span class="plans-eyebrow">{{ __('Package management') }}</span><h1>{{ $plan->exists ? __('Edit plan') : __('Create plan') }}</h1><p>{{ __('Bring pricing, benefits, and feature allowances together.') }}</p></div>@if($plan->exists)<span class="package-version">{{ __('Version') }} {{ $plan->version }}</span>@endif</header>
    <form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}">
        @csrf @if($plan->exists) @method('PUT') @endif
        <input type="hidden" name="version" value="{{ old('version', $plan->version ?? 0) }}">
        <div class="package-editor-grid">
            <div class="package-main">
                <section class="package-panel"><div class="package-section-title"><span>01</span><div><h2>{{ __('Plan identity') }}</h2><p>{{ __('Give your package a clear name and description in both languages.') }}</p></div></div>
                    <div class="package-fields">
                        @foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)
                        <div><label for="name_{{ $locale }}">{{ __('Name') }} · {{ __($label) }} <span>*</span></label><input class="form-control" id="name_{{ $locale }}" name="name_{{ $locale }}" value="{{ old('name_'.$locale, $plan->{'name_'.$locale}) }}" required maxlength="255" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" placeholder="{{ $locale === 'ar' ? 'مثال: الاحترافية' : 'e.g. Professional' }}"></div>
                        @endforeach
                        <div><label for="slug">{{ __('Plan code') }} <span>*</span></label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $plan->slug) }}" required maxlength="100" pattern="[A-Za-z0-9_-]+" dir="ltr" placeholder="professional"><small>{{ __('A unique code for this package.') }}</small></div>
                        <div><label for="sort_order">{{ __('Display order') }}</label><input class="form-control" type="number" min="0" max="9999" id="sort_order" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required><small>{{ __('Lower numbers appear first.') }}</small></div>
                        @foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)
                        <div><label for="description_{{ $locale }}">{{ __('Description') }} · {{ __($label) }}</label><textarea class="form-control" id="description_{{ $locale }}" name="description_{{ $locale }}" rows="3" maxlength="2000" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('description_'.$locale, data_get($plan->meta, 'description_'.$locale)) }}</textarea></div>
                        @endforeach
                    </div>
                </section>
                <section class="package-panel"><div class="package-section-title"><span>02</span><div><h2>{{ __('Pricing') }}</h2><p>{{ __('Set clear monthly and yearly prices for this package.') }}</p></div></div>
                    <div class="package-fields package-fields-three">
                        <div><label for="currency">{{ __('Currency') }}</label><select class="form-select" id="currency" name="currency">@foreach(['SAR', 'USD', 'AED', 'EUR', 'GBP'] as $currency)<option @selected(old('currency', $plan->currency ?? 'SAR') === $currency)>{{ $currency }}</option>@endforeach</select></div>
                        @foreach(['price' => ['Monthly price', $plan->price ?? 0], 'yearly_price' => ['Yearly price', $plan->yearly_price]] as $key => [$label, $value])
                        <div><label for="{{ $key }}">{{ __($label) }} <span>*</span></label><input class="form-control" type="number" min="0" max="9999999" step="0.01" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $value) }}" required></div>
                        @endforeach
                    </div>
                </section>
                <section class="package-panel"><div class="package-section-title"><span>03</span><div><h2>{{ __('Features & allowances') }}</h2><p>{{ __('Choose included features and configure how much this package allows.') }}</p></div></div>
                    <div class="package-stage-note"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('Settings are saved for this package. Seller access enforcement will follow in the next stage.') }}</div>
                    <div class="package-period"><label for="allowance_period">{{ __('Allowance period') }}</label><select class="form-select" id="allowance_period" name="allowance_period">@foreach(['monthly' => 'Per month', 'billing_cycle' => 'Per billing cycle', 'trial' => 'Total during trial'] as $period => $label)<option value="{{ $period }}" @selected(old('allowance_period', data_get($plan->meta, 'allowance_period', 'billing_cycle')) === $period)>{{ __($label) }}</option>@endforeach</select><small>{{ __('Applies to usage allowances, not capacity limits.') }}</small></div>
                    <div class="package-feature-list">
                    @forelse($features as $feature)
                        @php($value = old('entitlements.'.$feature->key, $plan->entitlement_settings[$feature->key] ?? ($feature->type === 'boolean' ? false : ($feature->type === 'benefit' ? '' : 0))))
                        <div class="package-feature-row">
                            <div><label for="entitlement_{{ $feature->key }}">{{ $feature->name }}</label><small>{{ $feature->description }}</small><span class="package-type">{{ __('feature_types.'.$feature->type) }}</span></div>
                            <div class="package-feature-control">
                                @if($feature->type === 'boolean')
                                    <input type="hidden" name="entitlements[{{ $feature->key }}]" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="entitlement_{{ $feature->key }}" name="entitlements[{{ $feature->key }}]" value="1" @checked($value)></div>
                                @elseif($feature->type === 'benefit')
                                    <input class="form-control" id="entitlement_{{ $feature->key }}" name="entitlements[{{ $feature->key }}]" value="{{ $value }}" maxlength="500" placeholder="{{ __('e.g. Priority support') }}">
                                @else
                                    <input class="form-control" type="number" id="entitlement_{{ $feature->key }}" name="entitlements[{{ $feature->key }}]" min="-1" max="1000000000" step="1" value="{{ $value }}" required><small>{{ __('-1 = unlimited · 0 = not included') }}</small>
                                @endif
                                @error('entitlements.'.$feature->key)<span class="package-field-error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @empty<p class="text-muted">{{ __('Create a feature in the catalog to configure package allowances.') }}</p>@endforelse
                    </div>
                    <a class="package-inline-link" href="{{ route('package-features.index') }}">{{ __('Manage feature catalog') }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </section>
                <section class="package-panel"><div class="package-section-title"><span>04</span><div><h2>{{ __('Package benefits') }}</h2><p>{{ __('Benefits shown on pricing cards. Enter one benefit per line in each language.') }}</p></div></div>
                    <div class="package-fields">@foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)<div><label for="features_{{ $locale }}">{{ __($label) }}</label><textarea class="form-control" id="features_{{ $locale }}" name="features_{{ $locale }}" rows="6" maxlength="5000" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('features_'.$locale, implode("\n", data_get($plan->meta, 'display_features_'.$locale, []))) }}</textarea></div>@endforeach</div>
                </section>
                @include('team.plans.history', ['changes' => $changes ?? collect()])
            </div>
            <aside class="package-side"><section class="package-panel"><h2>{{ __('Publishing') }}</h2><p class="package-muted">{{ __('Control how this package appears to merchants.') }}</p>
                @foreach(['is_active' => ['Active', 'Available for new subscriptions', true], 'is_popular' => ['Recommended plan', 'Highlighted in your pricing', false], 'is_free' => ['Free trial plan', 'Free plans must have zero prices.', false]] as $key => [$label, $help, $default])
                <div class="package-toggle-row"><div><label for="{{ $key }}">{{ __($label) }}</label><small>{{ __($help) }}</small></div><input type="hidden" name="{{ $key }}" value="0"><div class="form-check form-switch"><input class="form-check-input" role="switch" type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $plan->$key ?? $default))></div></div>
                @endforeach
                <label class="package-label" for="trial_days">{{ __('Trial days') }}</label><input class="form-control" type="number" id="trial_days" name="trial_days" min="1" max="365" value="{{ old('trial_days', data_get($plan->meta, 'trial_days', 15)) }}" required>
                <button class="plans-button plans-button-primary package-save" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>{{ __('Save plan') }}</button><a class="package-cancel" href="{{ route('plans.index') }}">{{ __('Cancel') }}</a>
            </section></aside>
        </div>
    </form>
</div>
@endsection
