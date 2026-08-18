@extends('layouts.app')
@push('styles')
    <style>
        .pricing-wrapper {
            padding: 80px 0;
            background: #f8f9fc;
            min-height: calc(100vh - 70px);
        }

        .pricing-title {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 55px;
        }

        .pricing-title .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 50px;
            background: #f0edff;
            color: #6c5ce7;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            margin-bottom: 16px;
        }

        .pricing-title h2 {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1.5px;
            color: #171725;
            margin-bottom: 14px;
        }

        .pricing-title p {
            font-size: 17px;
            color: #6b7280;
            margin: 0;
        }

        .pricing-card {
            height: 100%;
            border: 1px solid #e9e9f2;
            border-radius: 20px;
            background: #fff;
            position: relative;
            overflow: hidden;
            transition: all .3s ease;
            box-shadow: 0 5px 25px rgba(30, 30, 60, .05);
            display: flex;
            flex-direction: column;
        }

        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(30, 30, 60, .12);
            border-color: #ddd9ff;
        }

        .pricing-card.popular {
            border: 2px solid #7367f0;
            box-shadow: 0 15px 40px rgba(115, 103, 240, .16);
        }

        .popular-badge {
            position: absolute;
            top: 18px;
            right: 18px;
            background: linear-gradient(135deg, #7367f0, #8b5cf6);
            color: #fff;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            z-index: 2;
        }

        .pricing-header {
            padding: 32px 28px 25px;
            border-bottom: 1px solid #f0f0f5;
        }

        .pricing-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .pricing-card-free_trial .pricing-icon {
            background: #f1efff;
            color: #7367f0;
        }

        .pricing-card-starter .pricing-icon {
            background: #eef8ff;
            color: #1683d8;
        }

        .pricing-card-pro .pricing-icon {
            background: #fff5e8;
            color: #f59e0b;
        }

        .pricing-card-empire .pricing-icon {
            background: #f4edff;
            color: #8b5cf6;
        }

        .pricing-card-unicorn .pricing-icon {
            background: #fff0f5;
            color: #e83e8c;
        }

        .pricing-header h5 {
            font-size: 22px;
            font-weight: 800;
            color: #171725;
            margin-bottom: 5px;
        }

        .pricing-name-ar {
            font-size: 13px;
            color: #9ca3af;
        }

        .pricing-description {
            font-size: 13px;
            line-height: 1.6;
            color: #6b7280;
            margin-top: 15px;
            min-height: 42px;
        }

        .price-wrapper {
            margin-top: 25px;
            display: flex;
            align-items: baseline;
            gap: 5px;
        }

        .currency {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
        }

        .price {
            font-size: 42px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -1.5px;
            color: #171725;
        }

        .price-period {
            font-size: 13px;
            color: #9ca3af;
        }

        .discount-price {
            font-size: 13px;
            color: #9ca3af;
            text-decoration: line-through;
            margin-top: 8px;
        }

        .pricing-features {
            padding: 28px;
            margin: 0;
            flex: 1;
        }

        .pricing-features-title {
            font-size: 12px;
            font-weight: 800;
            color: #171725;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 15px;
        }

        .pricing-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            list-style: none;
            font-size: 14px;
            color: #4b5563;
        }

        .pricing-features li i {
            color: #28c76f;
            font-size: 15px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .pricing-features li.disabled {
            color: #a1a1aa;
        }

        .pricing-features li.disabled i {
            color: #cbd0d8;
        }

        .pricing-limit {
            margin-left: auto;
            font-weight: 700;
            color: #171725;
            white-space: nowrap;
            font-size: 13px;
        }

        .pricing-footer {
            padding: 0 28px 30px;
            margin-top: auto;
        }

        .btn-subscribe {
            width: 100%;
            border: 0;
            border-radius: 11px;
            padding: 13px 20px;
            font-weight: 700;
            font-size: 14px;
            transition: all .25s ease;
        }

        .btn-subscribe:hover {
            transform: translateY(-1px);
        }

        .btn-pricing-primary {
            background: #7367f0;
            color: #fff;
        }

        .btn-pricing-primary:hover {
            background: #6255e8;
            color: #fff;
        }

        .btn-pricing-outline {
            background: #f5f5f8;
            color: #29293d;
        }

        .btn-pricing-outline:hover {
            background: #ececf3;
            color: #171725;
        }

        .pricing-note {
            text-align: center;
            margin-top: 45px;
            color: #9ca3af;
            font-size: 13px;
        }

        .pricing-popular-glow {
            position: absolute;
            top: -100px;
            left: 50%;
            width: 200px;
            height: 200px;
            transform: translateX(-50%);
            background: rgba(115, 103, 240, .08);
            filter: blur(60px);
            pointer-events: none;
        }

        @media (max-width: 1399px) {
            .pricing-wrapper .container {
                max-width: 1320px;
            }

            .pricing-header,
            .pricing-features {
                padding-left: 22px;
                padding-right: 22px;
            }

            .pricing-footer {
                padding-left: 22px;
                padding-right: 22px;
            }

            .pricing-title h2 {
                font-size: 38px;
            }

            .price {
                font-size: 36px;
            }
        }

        @media (max-width: 1199px) {
            .pricing-wrapper {
                padding: 65px 0;
            }

            .pricing-title h2 {
                font-size: 36px;
            }
        }

        @media (max-width: 991px) {
            .pricing-title h2 {
                font-size: 34px;
            }

            .pricing-title p {
                font-size: 16px;
            }
        }

        @media (max-width: 767px) {
            .pricing-wrapper {
                padding: 55px 0;
            }

            .pricing-title {
                margin-bottom: 35px;
            }

            .pricing-title h2 {
                font-size: 30px;
                letter-spacing: -1px;
            }

            .pricing-title p {
                font-size: 15px;
            }

            .pricing-card {
                border-radius: 18px;
            }

            .pricing-header {
                padding: 28px 24px 22px;
            }

            .pricing-features {
                padding: 24px;
            }

            .pricing-footer {
                padding: 0 24px 25px;
            }
        }
    </style>
@endpush
@section('content')

    <div class="container">

        {{-- HEADER --}}
        <div class="pricing-title">

            <div class="eyebrow">
                <i class="bi bi-stars"></i>
                SIMPLE &amp; TRANSPARENT PRICING
            </div>

            <h2>
                Choose the plan that fits your team
            </h2>

            <p>
                Start free and scale your social media workspace as your business grows.
            </p>

        </div>

        {{-- PACKAGES --}}
        <div class="row g-4 justify-content-center">

            @foreach($packages as $package)

                @php
                    /*
                    |--------------------------------------------------------------------------
                    | Decode JSON
                    |--------------------------------------------------------------------------
                    */

                    $features = is_array($package->features)
                        ? $package->features
                        : json_decode($package->features ?? '{}', true);

                    $limits = is_array($package->limits)
                        ? $package->limits
                        : json_decode($package->limits ?? '{}', true);

                    $meta = is_array($package->meta)
                        ? $package->meta
                        : json_decode($package->meta ?? '{}', true);

                    $features = is_array($features) ? $features : [];
                    $limits = is_array($limits) ? $limits : [];
                    $meta = is_array($meta) ? $meta : [];

                    /*
                    |--------------------------------------------------------------------------
                    | Package status
                    |--------------------------------------------------------------------------
                    */

                    $isPopular = (bool) $package->is_popular;
                    $isFree = (bool) $package->is_free;

                    /*
                    |--------------------------------------------------------------------------
                    | Pricing
                    |--------------------------------------------------------------------------
                    */

                    $hasDiscount = $package->discount_price !== null
                        && (float) $package->discount_price > 0
                        && (float) $package->discount_price < (float) $package->price;

                    $displayPrice = $hasDiscount
                        ? $package->discount_price
                        : $package->price;

                    /*
                    |--------------------------------------------------------------------------
                    | Limits
                    |--------------------------------------------------------------------------
                    */

                    $maxPosts = data_get($limits, 'posts_monthly');
                    $maxAds = data_get($limits, 'ad_campaigns_monthly');
                    $maxChannels = data_get($limits, 'channels');
                    $aiGenerations = data_get($limits, 'ai_generations_monthly');
                    $teamMembers = data_get($limits, 'team_members');

                    /*
                    |--------------------------------------------------------------------------
                    | Backward compatibility with your existing limits JSON
                    |--------------------------------------------------------------------------
                    */

                    if ($maxPosts === null) {
                        $maxPosts = data_get($limits, 'monthly');
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Unlimited helper
                    |--------------------------------------------------------------------------
                    */

                    $unlimited = function ($value) {
                        return $value === -1 || $value === null;
                    };

                    /*
                    |--------------------------------------------------------------------------
                    | Package descriptions
                    |--------------------------------------------------------------------------
                    */

                    $description = match ($package->slug) {
                        'free_trial' => 'Explore Socialeaz with essential social media tools.',
                        'starter' => 'Everything you need to manage your social presence.',
                        'pro' => 'Advanced tools for growing social media teams.',
                        'empire' => 'Powerful social management for larger teams.',
                        'unicorn' => 'The complete Socialeaz experience for ambitious teams.',
                        default => 'Powerful tools to grow your social media.',
                    };

                    /*
                    |--------------------------------------------------------------------------
                    | Package icon
                    |--------------------------------------------------------------------------
                    */

                    $icon = match ($package->slug) {
                        'free_trial' => 'bi-gift',
                        'starter' => 'bi-rocket-takeoff',
                        'pro' => 'bi-stars',
                        'empire' => 'bi-building',
                        'unicorn' => 'bi-gem',
                        default => 'bi-box',
                    };
                @endphp

                <div class="col-12 col-md-6 col-xl-4 col-xxl-{{ count($packages) >= 5 ? '3' : '4' }}">

                    <div class="pricing-card pricing-card-{{ $package->slug }} {{ $isPopular ? 'popular' : '' }}">

                        @if($isPopular)
                            <div class="pricing-popular-glow"></div>

                            <div class="popular-badge">
                                MOST POPULAR
                            </div>
                        @endif

                        {{-- HEADER --}}
                        <div class="pricing-header">

                            <div class="pricing-icon">
                                <i class="bi {{ $icon }}"></i>
                            </div>

                            <h5>
                                {{ $package->name_en }}
                            </h5>

                            @if($package->name_ar)
                                <div class="pricing-name-ar">
                                    {{ $package->name_ar }}
                                </div>
                            @endif

                            <div class="pricing-description">
                                {{ $description }}
                            </div>

                            {{-- PRICE --}}
                            <div class="price-wrapper">

                                <span class="currency">
                                    {{ $package->currency }}
                                </span>

                                <span class="price">
                                    {{ number_format($displayPrice, 0) }}
                                </span>

                                @if(!$isFree)
                                    <span class="price-period">
                                        / month
                                    </span>
                                @else
                                    <span class="price-period">
                                        / trial
                                    </span>
                                @endif

                            </div>

                            @if($hasDiscount)

                                <div class="discount-price">
                                    {{ $package->currency }}
                                    {{ number_format($package->price, 0) }}
                                </div>

                            @endif

                        </div>

                        {{-- FEATURES --}}
                        <ul class="pricing-features">

                            <div class="pricing-features-title">
                                What's included
                            </div>

                            {{-- POSTS --}}
                            <li>

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Social posts
                                </span>

                                @if($maxPosts !== null)

                                    <span class="pricing-limit">
                                        @if($unlimited($maxPosts))
                                            Unlimited
                                        @else
                                            {{ number_format($maxPosts) }}/mo
                                        @endif
                                    </span>

                                @endif

                            </li>

                            {{-- SOCIAL ADS --}}
                            @php
                                $socialAds = (bool) data_get($features, 'social_ads', false);
                            @endphp

                            <li class="{{ $socialAds ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $socialAds ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Social Ads
                                </span>

                                @if($socialAds && $maxAds !== null)

                                    <span class="pricing-limit">

                                        @if($unlimited($maxAds))
                                            Unlimited
                                        @else
                                            {{ number_format($maxAds) }}
                                        @endif

                                    </span>

                                @endif

                            </li>

                            {{-- SOCIAL CHANNELS --}}
                            <li>

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Social channels
                                </span>

                                @if($maxChannels !== null)

                                    <span class="pricing-limit">

                                        @if($unlimited($maxChannels))
                                            Unlimited
                                        @else
                                            {{ number_format($maxChannels) }}
                                        @endif

                                    </span>

                                @endif

                            </li>

                            {{-- AI COPILOT --}}
                            @php
                                $aiCopilot = (bool) data_get($features, 'ai_copilot', false);
                            @endphp

                            <li class="{{ $aiCopilot ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $aiCopilot ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    AI Copilot
                                </span>

                                @if($aiCopilot && $aiGenerations !== null)

                                    <span class="pricing-limit">

                                        @if($unlimited($aiGenerations))
                                            Unlimited
                                        @else
                                            {{ number_format($aiGenerations) }}
                                        @endif

                                    </span>

                                @endif

                            </li>

                            {{-- ANALYTICS --}}
                            @php
                                $analytics = (bool) data_get($features, 'analytics', false);
                            @endphp

                            <li class="{{ $analytics ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $analytics ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Analytics
                                </span>

                            </li>

                            {{-- CONTENT CALENDAR --}}
                            @php
                                $contentCalendar = (bool) data_get($features, 'content_calendar', false);
                            @endphp

                            <li class="{{ $contentCalendar ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $contentCalendar ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Content calendar
                                </span>

                            </li>

                            {{-- TEAM COLLABORATION --}}
                            @php
                                $teamCollaboration = (bool) data_get($features, 'team_collaboration', false);
                            @endphp

                            <li class="{{ $teamCollaboration ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $teamCollaboration ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Team members
                                </span>

                                @if($teamCollaboration && $teamMembers !== null)

                                    <span class="pricing-limit">

                                        @if($unlimited($teamMembers))
                                            Unlimited
                                        @else
                                            {{ number_format($teamMembers) }}
                                        @endif

                                    </span>

                                @endif

                            </li>

                            {{-- ADVANCED ANALYTICS --}}
                            @php
                                $advancedAnalytics = (bool) data_get($features, 'advanced_analytics', false);
                            @endphp

                            <li class="{{ $advancedAnalytics ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $advancedAnalytics ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Advanced analytics
                                </span>

                            </li>

                            {{-- WHITE LABEL --}}
                            @php
                                $whiteLabel = (bool) data_get($features, 'white_label', false);
                            @endphp

                            <li class="{{ $whiteLabel ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $whiteLabel ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    White label
                                </span>

                            </li>

                            {{-- API ACCESS --}}
                            @php
                                $apiAccess = (bool) data_get($features, 'api_access', false);
                            @endphp

                            <li class="{{ $apiAccess ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $apiAccess ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    API access
                                </span>

                            </li>

                            {{-- SHOPIFY --}}
                            @php
                                $shopify = (bool) data_get($features, 'shopify', false);
                            @endphp

                            <li class="{{ $shopify ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $shopify ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    Shopify integration
                                </span>

                            </li>

                            {{-- WOOCOMMERCE --}}
                            @php
                                $woocommerce = (bool) data_get($features, 'woocommerce', false);
                            @endphp

                            <li class="{{ $woocommerce ? '' : 'disabled' }}">

                                <i class="bi bi-{{ $woocommerce ? 'check' : 'x' }}-circle-fill"></i>

                                <span>
                                    WooCommerce integration
                                </span>

                            </li>

                        </ul>

                        {{-- FOOTER --}}
                        <div class="pricing-footer">

                            <form
                                    method="POST"
                                    action="{{ url('/subscription/checkout') }}"
                            >

                                @csrf

                                <input
                                        type="hidden"
                                        name="package_id"
                                        value="{{ $package->id }}"
                                >

                                <button
                                        type="submit"
                                        class="btn btn-subscribe {{ $isPopular ? 'btn-pricing-primary' : 'btn-pricing-outline' }}"
                                >

                                    @if($isFree)
                                        Start Free
                                    @else
                                        Choose {{ $package->name_en }}
                                    @endif

                                    <i class="bi bi-arrow-right ms-1"></i>

                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

        {{-- NOTE --}}
        <div class="pricing-note">

            <i class="bi bi-shield-check me-1"></i>

            No hidden fees · Cancel anytime · Secure payments

        </div>

    </div>

@endsection