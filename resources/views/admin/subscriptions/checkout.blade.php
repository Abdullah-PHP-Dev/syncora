@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/subscription.css') }}" />
@endpush


@section('content')

    <subscription-checkout
            bundle-id="{{ request('bundle_id', request('plan_id')) }}"
            cycle="{{ in_array(request('cycle'), ['monthly','yearly']) ? request('cycle') : 'monthly' }}"
            checkout-url="{{ route('admin.subscription.checkout.process') }}"
            coupon-url="{{ route('admin.subscription.checkout.process') }}"
            csrf-token="{{ csrf_token() }}"
            card-image="{{ asset('assets/img/payment/card.png') }}"
            tabby-image="{{ asset('assets/img/payment/tabby-01.png') }}"
            tamara-image="{{ asset('assets/img/payment/tammara.png') }}"
    ></subscription-checkout>

@endsection

