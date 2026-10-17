@extends('layouts.app')

@section('title', __('subscription_checkout.heading'))

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush


@section('content')

    <subscription-checkout
            bundle-id="{{ request('bundle_id', request('plan_id')) }}"
            cycle="{{ in_array(request('cycle'), ['monthly','yearly']) ? request('cycle') : 'monthly' }}"
            checkout-url="{{ route('admin.subscription.checkout.process') }}"
            coupon-url="{{ route('admin.subscription.coupon') }}"
            plans-url="{{ route('admin.subscription.plans') }}"
            checkout-key="{{ (string) \Illuminate\Support\Str::uuid() }}"
            :coupon-messages='@json(__('coupons.checkout'))'
            :messages='@json(__('subscription_checkout'))'
            :vat-rate='@json(app(\App\Services\SubscriptionPriceService::class)->vatRate())'
            back-url="{{ route('admin.subscription.select') }}"
            locale="{{ app()->getLocale() }}"
            csrf-token="{{ csrf_token() }}"
            card-image="{{ asset('assets/img/payment/card.png') }}"
            tabby-image="{{ asset('assets/img/payment/tabby-01.png') }}"
            tamara-image="{{ asset('assets/img/payment/tammara.png') }}"
    ></subscription-checkout>

@endsection
