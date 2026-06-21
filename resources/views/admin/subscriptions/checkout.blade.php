@extends('layouts.app')

@section('content')

    <style>
        .checkout-wrapper {
            background: #f8f9fb;
            padding: 50px 0;
        }

        .card-shadow {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }

        .summary-box {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
        }

        .price-big {
            font-size: 32px;
            font-weight: 700;
        }

        .badge-plan {
            background: #7367f0;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .payment-method {
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.2s;
        }

        .payment-method:hover {
            border-color: #7367f0;
            background: #f6f5ff;
        }

        .btn-pay {
            background: #7367f0;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-pay:hover {
            background: #5f54e6;
            color: #fff;
        }
    </style>

    <div class="checkout-wrapper">

        <div class="container">

            <div class="row justify-content-center">

                {{-- LEFT SIDE --}}
                <div class="col-md-7">

                    <div class="card card-shadow">

                        <div class="card-body">

                            <h4 class="mb-3">Payment Method</h4>

                            <p class="text-muted">
                                Choose how you want to pay for your subscription
                            </p>

                            {{-- Payment Methods --}}
                            <label class="payment-method d-block">
                                <input type="radio" name="payment" checked>
                                <strong class="ml-2">Credit / Debit Card</strong>
                                <span class="text-muted d-block ml-4">Visa, Mastercard</span>
                            </label>

                            <label class="payment-method d-block">
                                <input type="radio" name="payment">
                                <strong class="ml-2">Apple Pay</strong>
                            </label>

                            <label class="payment-method d-block">
                                <input type="radio" name="payment">
                                <strong class="ml-2">Bank Transfer</strong>
                            </label>

                            <hr>

                            <h4>Billing Details</h4>

                            <div class="row">
                                <div class="col-md-6">
                                    <input class="form-control mb-3" placeholder="Full Name">
                                </div>
                                <div class="col-md-6">
                                    <input class="form-control mb-3" placeholder="Email">
                                </div>
                            </div>

                            <input class="form-control mb-3" placeholder="Phone Number">

                        </div>

                    </div>

                </div>

                {{-- RIGHT SIDE --}}
                <div class="col-md-5">

                    <div class="summary-box">

                        <h4>Order Summary</h4>

                        <div class="mt-3">

                        <span class="badge-plan">
                            {{ $package->name_en }}
                        </span>

                            <h2 class="price-big mt-3">
                                {{ $package->price }} {{ $package->currency }}
                                <small class="text-muted">/ {{ $package->billing_cycle }}</small>
                            </h2>

                        </div>

                        <hr>

                        {{-- FEATURES --}}
                        <ul class="list-unstyled">

                            <li>✔ Users: {{ data_get($package->features, 'limits.users') }}</li>
                            <li>✔ Products: {{ data_get($package->features, 'limits.products') }}</li>
                            <li>✔ Storage: {{ data_get($package->features, 'limits.storage_gb') }} GB</li>

                            <li>✔ Ads Manager:
                                {{ data_get($package->features, 'features.ads_manager') ? 'Included' : 'Not included' }}
                            </li>

                        </ul>

                        <hr>

                        <form method="POST" action="/subscription/activate">
                            @csrf

                            <input type="hidden" name="package_id" value="{{ $package->id }}">

                            <button class="btn btn-pay btn-block">
                                Pay & Activate Subscription
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

@endsection