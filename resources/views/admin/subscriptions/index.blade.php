@extends('layouts.app')

@push('styles')

    <link rel="stylesheet" href="{{ asset('assets/css/subscription.css') }}" />
@endpush


@section('content')

    @php

        /*
        |--------------------------------------------------------------------------
        | Current Subscription
        |--------------------------------------------------------------------------
        */

        $currentSubscription = auth()->user()->subscription ?? null;

        $currentBundleId = $currentSubscription
            ? $currentSubscription->bundle_id
            : null;

    @endphp


    <div class="subscription-page">

        <div class="container">

            {{-- ==========================================================
                 HEADER
            =========================================================== --}}

            <div class="subscription-header">

                <div class="eyebrow">

                    <i class="bi bi-stars"></i>

                    Simple & Flexible Pricing

                </div>


                <h1>

                    Choose the plan that
                    <span>fits your business</span>

                </h1>


                <p>

                    Start small, scale when you need and switch your billing
                    cycle anytime. Choose the plan that gives your business
                    everything it needs to grow.

                </p>

            </div>


            {{-- ==========================================================
                 CURRENT SUBSCRIPTION
            =========================================================== --}}

            @if($currentSubscription)

                <div class="current-subscription">

                    <div class="current-subscription-content">

                        <div class="current-plan">

                            <div class="current-plan-icon">

                                <i class="bi bi-box-seam"></i>

                            </div>


                            <div>

                                <div class="current-plan-label">

                                    Your Current Subscription

                                </div>


                                <div class="current-plan-name">

                                    {{ $currentSubscription->bundle->name
                                        ?? $currentSubscription->bundle_name
                                        ?? 'Current Plan' }}

                                </div>

                            </div>

                        </div>


                        <div class="subscription-dates">

                            <div class="date-item">

                            <span class="date-label">

                                Started

                            </span>


                                <span class="date-value">

                                {{ \Carbon\Carbon::parse(
                                    $currentSubscription->start_date
                                )->format('d M Y') }}

                            </span>

                            </div>


                            <div class="date-item">

                            <span class="date-label">

                                Renews / Ends

                            </span>


                                <span class="date-value">

                                {{ \Carbon\Carbon::parse(
                                    $currentSubscription->end_date
                                )->format('d M Y') }}

                            </span>

                            </div>


                            <span class="subscription-status">

                            {{ ucfirst($currentSubscription->status) }}

                        </span>

                        </div>

                    </div>

                </div>

            @endif


            {{-- ==========================================================
                 BILLING
            =========================================================== --}}

            <div class="billing-section">

                <div class="billing-title">

                    Choose your billing cycle

                </div>


                <div class="billing-wrapper">

                    <div class="billing-container">

                        <div class="billing-option">

                            <input
                                    type="radio"
                                    name="billing_cycle"
                                    id="billing_monthly"
                                    value="monthly"
                                    checked
                            >

                            <label for="billing_monthly">

                                <i class="bi bi-calendar3 mr-1"></i>

                                Monthly

                            </label>

                        </div>


                        <div class="billing-option">

                            <input
                                    type="radio"
                                    name="billing_cycle"
                                    id="billing_yearly"
                                    value="yearly"
                            >

                            <label for="billing_yearly">

                                <i class="bi bi-calendar-check mr-1"></i>

                                Yearly

                                <span class="billing-save">

                                BEST VALUE

                            </span>

                            </label>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ==========================================================
                 PACKAGES
            =========================================================== --}}

            <div class="row">

                @foreach($packages as $index => $package)

                    @php

                        /*
                        |--------------------------------------------------------------------------
                        | Prices
                        |--------------------------------------------------------------------------
                        */

                        $monthlyPrice = (float) $package->price;

                        $yearlyPrice = (float) data_get(
                            $package->meta,
                            'yearly_price',
                            $monthlyPrice * 12
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Actual Plan IDs
                        |--------------------------------------------------------------------------
                        |
                        | KEEPING YOUR EXISTING PLAN IDs
                        |
                        */

                        $monthlyPlanId = $package->monthly_plan_id
                            ?? $package->id;

                        $yearlyPlanId = $package->yearly_plan_id
                            ?? $package->id;


                        /*
                        |--------------------------------------------------------------------------
                        | Features
                        |--------------------------------------------------------------------------
                        */

                        $features = is_array($package->features)
                            ? $package->features
                            : json_decode(
                                $package->features ?? '{}',
                                true
                            );

                        $features = is_array($features)
                            ? $features
                            : [];


                        $users = data_get(
                            $features,
                            'limits.users'
                        );

                        $products = data_get(
                            $features,
                            'limits.products'
                        );

                        $storage = data_get(
                            $features,
                            'limits.storage_gb'
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Current Plan
                        |--------------------------------------------------------------------------
                        */

                        $isCurrent = $currentBundleId == $package->id;


                        /*
                        |--------------------------------------------------------------------------
                        | Recommended Plan
                        |--------------------------------------------------------------------------
                        */

                        $isRecommended = $index === 1;


                        /*
                        |--------------------------------------------------------------------------
                        | YOUR EXISTING ROUTES - NOT CHANGED
                        |--------------------------------------------------------------------------
                        */

                        $monthlyCheckoutUrl = route(
                            'admin.subscription.checkout',
                            [
                                'plan_id' => $monthlyPlanId,
                                'cycle'   => 'monthly',
                            ]
                        );

                        $yearlyCheckoutUrl = route(
                            'admin.subscription.checkout',
                            [
                                'plan_id' => $yearlyPlanId,
                                'cycle'   => 'yearly',
                            ]
                        );

                    @endphp


                    <div class="col-lg-4 col-md-6 mb-4">

                        <div
                                class="
                            package-card
                            {{ $isCurrent ? 'current' : '' }}
                            {{ $isRecommended && !$isCurrent ? 'recommended' : '' }}
                        "
                                data-monthly="{{ $monthlyPrice }}"
                                data-yearly="{{ $yearlyPrice }}"
                        >


                            {{-- ==================================================
                                 BADGE
                            =================================================== --}}

                            @if($isCurrent)

                                <div class="plan-badge current-badge">

                                    <i class="bi bi-check-circle"></i>

                                    Current Plan

                                </div>

                            @elseif($isRecommended)

                                <div class="plan-badge popular-badge">

                                    <i class="bi bi-stars"></i>

                                    Most Popular

                                </div>

                            @endif


                            {{-- ==================================================
                                 ICON
                            =================================================== --}}

                            <div class="package-icon">

                                @if($index === 0)

                                    <i class="bi bi-rocket"></i>

                                @elseif($index === 1)

                                    <i class="bi bi-stars"></i>

                                @else

                                    <i class="bi bi-building"></i>

                                @endif

                            </div>


                            {{-- ==================================================
                                 NAME
                            =================================================== --}}

                            <div class="package-name">

                                {{ $package->name_en }}

                            </div>


                            {{-- ==================================================
                                 DESCRIPTION
                            =================================================== --}}

                            <div class="package-description">

                                {{ $package->description_en
                                    ?? 'Everything you need to grow your business.' }}

                            </div>


                            {{-- ==================================================
                                 PRICE
                            =================================================== --}}

                            <div class="package-price">

                                <strong class="package-price-value">

                                    {{ number_format(
                                        $monthlyPrice,
                                        2
                                    ) }}

                                </strong>


                                <small>

                                    {{ $package->currency ?? 'SAR' }}

                                </small>


                                <span class="period">

                                / month

                            </span>

                            </div>


                            <div
                                    class="annual-note"
                                    style="visibility: hidden;"
                            >

                                <i class="bi bi-check-circle-fill mr-1"></i>

                                Save with annual billing

                            </div>


                            {{-- ==================================================
                                 FEATURES
                            =================================================== --}}

                            <div class="package-features">

                                <div class="feature-heading">

                                    What's included

                                </div>


                                @if($users !== null)

                                    <div class="package-feature">

                                        <i class="bi bi-check"></i>

                                        <span>

                                        {{ $users == -1
                                            ? 'Unlimited'
                                            : $users }}

                                        users

                                    </span>

                                    </div>

                                @endif


                                @if($products !== null)

                                    <div class="package-feature">

                                        <i class="bi bi-check"></i>

                                        <span>

                                        {{ $products == -1
                                            ? 'Unlimited'
                                            : number_format($products) }}

                                        products

                                    </span>

                                    </div>

                                @endif


                                @if($storage !== null)

                                    <div class="package-feature">

                                        <i class="bi bi-check"></i>

                                        <span>

                                        {{ $storage == -1
                                            ? 'Unlimited'
                                            : $storage . ' GB' }}

                                        storage

                                    </span>

                                    </div>

                                @endif


                                @if(data_get(
                                    $features,
                                    'features.ads_manager'
                                ))

                                    <div class="package-feature">

                                        <i class="bi bi-check"></i>

                                        <span>

                                        Ads Manager

                                    </span>

                                    </div>

                                @endif

                            </div>


                            {{-- ==================================================
                                 CTA
                            =================================================== --}}

                            <a
                                    href="{{ $monthlyCheckoutUrl }}"
                                    class="btn-select-plan package-checkout-link"
                                    data-monthly-url="{{ $monthlyCheckoutUrl }}"
                                    data-yearly-url="{{ $yearlyCheckoutUrl }}"
                            >
                                @if($isCurrent)
                                    <i class="bi bi-check-circle"></i>

                                    <span>
            Current Plan
        </span>
                                @else
                                    <span>
            Continue with this plan
        </span>

                                    <i class="bi bi-arrow-right"></i>
                                @endif
                            </a>

                        </div>

                    </div>

                @endforeach

            </div>


            {{-- ==========================================================
                 TRUST
            =========================================================== --}}

            <div class="subscription-trust">

            <span class="trust-item">

                <i class="bi bi-shield-check"></i>

                Secure checkout

            </span>


                <span class="trust-item">

                <i class="bi bi-credit-card"></i>

                Multiple payment options

            </span>


                <span class="trust-item">

                <i class="bi bi-arrow-repeat"></i>

                Flexible billing

            </span>

            </div>

        </div>

    </div>


    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const billingOptions = document.querySelectorAll(
                'input[name="billing_cycle"]'
            );

            const packageCards = document.querySelectorAll(
                '.package-card'
            );


            function formatPrice(price) {

                return price.toLocaleString(undefined, {

                    minimumFractionDigits: 2,

                    maximumFractionDigits: 2

                });

            }


            function updateBillingCycle(cycle) {

                packageCards.forEach(function (card) {

                    const monthly = parseFloat(
                        card.dataset.monthly
                    );

                    const yearly = parseFloat(
                        card.dataset.yearly
                    );


                    const priceElement = card.querySelector(
                        '.package-price-value'
                    );

                    const periodElement = card.querySelector(
                        '.period'
                    );

                    const annualNote = card.querySelector(
                        '.annual-note'
                    );

                    const checkoutLink = card.querySelector(
                        '.package-checkout-link'
                    );


                    if (!priceElement) {

                        return;

                    }


                    /* =====================================================
                       YEARLY
                    ====================================================== */

                    if (cycle === 'yearly') {

                        priceElement.textContent =
                            formatPrice(yearly);


                        if (periodElement) {

                            periodElement.textContent =
                                '/ year';

                        }


                        if (annualNote) {

                            annualNote.style.visibility =
                                'visible';

                        }


                        if (checkoutLink) {

                            checkoutLink.href =
                                checkoutLink.dataset.yearlyUrl;

                        }

                    }


                    /* =====================================================
                       MONTHLY
                    ====================================================== */

                    else {

                        priceElement.textContent =
                            formatPrice(monthly);


                        if (periodElement) {

                            periodElement.textContent =
                                '/ month';

                        }


                        if (annualNote) {

                            annualNote.style.visibility =
                                'hidden';

                        }


                        if (checkoutLink) {

                            checkoutLink.href =
                                checkoutLink.dataset.monthlyUrl;

                        }

                    }

                });

            }


            billingOptions.forEach(function (option) {

                option.addEventListener('change', function () {

                    updateBillingCycle(
                        this.value
                    );

                });

            });


            /*
             |--------------------------------------------------------------------------
             | Initial State
             |--------------------------------------------------------------------------
             */

            updateBillingCycle('monthly');

        });

    </script>

@endsection