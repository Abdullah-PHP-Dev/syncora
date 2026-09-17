@extends('layouts.app')
@section('title', $plan->exists ? __('Edit plan') : __('Create plan'))
@section('content')
<h3>{{ $plan->exists ? __('Edit plan') : __('Create plan') }}</h3>
<form class="card" method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}">
@csrf @if($plan->exists) @method('PUT') @endif
<div class="card-body row g-4">
@foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)
<div class="col-md-6"><label class="form-label" for="name_{{ $locale }}">{{ __('Name') }} — {{ __($label) }}</label><input class="form-control" id="name_{{ $locale }}" name="name_{{ $locale }}" value="{{ old('name_'.$locale, $plan->{'name_'.$locale}) }}" required maxlength="255" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"></div>
@endforeach
<div class="col-md-6"><label class="form-label" for="slug">{{ __('Plan code') }}</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $plan->slug) }}" required maxlength="100" pattern="[A-Za-z0-9_-]+"></div>
<div class="col-md-6"><label class="form-label" for="currency">{{ __('Currency') }}</label><select class="form-select" id="currency" name="currency">@foreach(['SAR', 'USD', 'AED', 'EUR', 'GBP'] as $currency)<option @selected(old('currency', $plan->currency ?? 'SAR') === $currency)>{{ $currency }}</option>@endforeach</select></div>
@foreach(['price' => ['Monthly price', $plan->price ?? 0], 'yearly_price' => ['Yearly price', $plan->yearly_price], 'sort_order' => ['Display order', $plan->sort_order ?? 0], 'trial_days' => ['Trial days', data_get($plan->meta, 'trial_days', 15)]] as $key => [$label, $value])
<div class="col-md-3"><label class="form-label" for="{{ $key }}">{{ __($label) }}</label><input class="form-control" type="number" min="{{ $key === 'trial_days' ? 1 : 0 }}" step="{{ str_contains($key, 'price') ? '0.01' : '1' }}" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $value) }}" required></div>
@endforeach
@foreach(['en' => 'English', 'ar' => 'Arabic'] as $locale => $label)
<div class="col-md-6"><label class="form-label" for="description_{{ $locale }}">{{ __('Description') }} — {{ __($label) }}</label><textarea class="form-control" id="description_{{ $locale }}" name="description_{{ $locale }}" rows="3" maxlength="2000" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('description_'.$locale, data_get($plan->meta, 'description_'.$locale)) }}</textarea></div>
<div class="col-md-6"><label class="form-label" for="features_{{ $locale }}">{{ __('Features, one per line') }} — {{ __($label) }}</label><textarea class="form-control" id="features_{{ $locale }}" name="features_{{ $locale }}" rows="5" maxlength="5000" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ old('features_'.$locale, implode("
", data_get($plan->meta, 'display_features_'.$locale, []))) }}</textarea></div>
@endforeach
@foreach(['is_active' => ['Active', true], 'is_popular' => ['Recommended plan', false], 'is_free' => ['Free trial plan', false]] as $key => [$label, $default])
<div class="col-md-4"><input type="hidden" name="{{ $key }}" value="0"><label class="form-check-label"><input class="form-check-input me-2" type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $plan->$key ?? $default))>{{ __($label) }}</label></div>
@endforeach
<div><button class="btn btn-primary">{{ __('Save plan') }}</button> <a class="btn btn-outline-secondary" href="{{ route('plans.index') }}">{{ __('Cancel') }}</a></div>
</div></form>
@endsection
