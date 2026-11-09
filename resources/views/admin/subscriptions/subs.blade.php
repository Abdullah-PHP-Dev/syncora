@extends('layouts.app')

@section('title', __('subscriptions.page.compare'))

@php
    $localized = fn (string $name) => LaravelLocalization::getLocalizedURL(app()->getLocale(), route($name), [], true);
    $plansPage = [
        // Localized, so feature names come back in the page's language.
        'plans' => $localized('admin.subscription.plans'),
        'usage' => Route::has('admin.subscription.usage') ? $localized('admin.subscription.usage') : null,
        'help' => Route::has('admin.help-center.index') ? $localized('admin.help-center.index') : null,
    ];
@endphp

@section('content')
    <div id="subscriptionApp">
        <subscription-plans
            :urls='@json($plansPage)'
            :messages='@json(__('subscriptions.page'))'
            locale="{{ app()->getLocale() }}"
        ></subscription-plans>
    </div>
@endsection
