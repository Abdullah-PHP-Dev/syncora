@extends('layouts.app')

@section('content')

    <style>
        .pricing-wrapper {
            padding: 60px 0;
            background: #f8f9fb;
        }

        .pricing-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .pricing-title h2 {
            font-weight: 700;
        }

        .pricing-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background: #fff;
            position: relative;
            overflow: hidden;
        }

        .pricing-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .pricing-header {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .price {
            font-size: 42px;
            font-weight: 700;
        }

        .currency {
            font-size: 16px;
            color: #888;
        }

        .pricing-features {
            padding: 20px 30px;
        }

        .pricing-features li {
            padding: 8px 0;
            list-style: none;
            font-size: 14px;
            color: #555;
        }

        .pricing-features li i {
            color: #28c76f;
            margin-right: 6px;
        }

        .pricing-footer {
            padding: 20px;
            text-align: center;
        }

        .btn-subscribe {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .popular-badge {
            position: absolute;
            top: 15px;
            right: -40px;
            background: #7367f0;
            color: #fff;
            padding: 5px 50px;
            transform: rotate(45deg);
            font-size: 12px;
        }
    </style>

    <div class="pricing-wrapper">

        <div class="container">

            {{-- HEADER --}}
            <div class="pricing-title">
                <h2>Choose Your Plan</h2>
                <p class="text-muted">Simple pricing. No hidden fees.</p>
            </div>

            <div class="row">

                @foreach($packages as $package)

                    @php
                        $features = $package->features;
                    @endphp

                    <div class="col-md-4 mb-4">

                        <div class="pricing-card">

                            {{-- Popular Badge --}}
                            @if($package->bundle_key == 'premium')
                                <div class="popular-badge">POPULAR</div>
                            @endif

                            {{-- HEADER --}}
                            <div class="pricing-header">

                                <h5>{{ $package->name_en }}</h5>
                                <small class="text-muted">{{ $package->name_ar }}</small>

                                <div class="mt-3">
                                    <span class="currency">{{ $package->currency }}</span>
                                    <span class="price">{{ $package->price }}</span>
                                    <span class="text-muted">/ {{ $package->billing_cycle }}</span>
                                </div>

                            </div>

                            {{-- FEATURES --}}
                            <ul class="pricing-features">

                                <li>
                                    <i class="fas fa-check"></i>
                                    Users: {{ data_get($features, 'limits.users') }}
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Products: {{ data_get($features, 'limits.products') }}
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Storage: {{ data_get($features, 'limits.storage_gb') }} GB
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Ads Manager:
                                    {{ data_get($features, 'features.ads_manager') ? 'Yes' : 'No' }}
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Scheduler:
                                    {{ data_get($features, 'features.post_scheduler') ? 'Yes' : 'No' }}
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Analytics:
                                    {{ data_get($features, 'features.analytics_dashboard') ? 'Yes' : 'No' }}
                                </li>

                            </ul>

                            {{-- FOOTER --}}
                            <div class="pricing-footer">

                                <form method="POST" action="/subscription/checkout">
                                    @csrf

                                    <input type="hidden" name="package_id" value="{{ $package->id }}">

                                    <button class="btn btn-primary btn-subscribe btn-block">
                                        Subscribe Now
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </div>

@endsection