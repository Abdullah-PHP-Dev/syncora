<section class="se-bwp" id="billing-workflow-pricing" aria-label="{{ __('Billing, workflow and pricing') }}">
    <div class="se-bwp-wrap">
        {{-- WALLET & BILLING --}}
        <div class="se-bwp-wallet-grid">
            <div class="se-bwp-intro">
                <span class="se-bwp-pill">{{ __('WALLET & BILLING') }}</span>
                <h2>{{ __('Simple billing.') }}<br>{{ __('More freedom to create.') }}</h2>
                <p>{{ __('Top up your wallet, manage your plan, and keep your content engine running — all in one place.') }}</p>
                <span class="se-bwp-doodle se-bwp-doodle-burst" aria-hidden="true">⌁</span>
            </div>

            <div class="se-bwp-card se-bwp-wallet-card">
                <div class="se-bwp-card-head">
                    <div class="se-bwp-icon purple">▣</div>
                    <div>
                        <strong>{{ __('Your Wallet') }}</strong>
                        <small>{{ __('Use balance to pay for AI credits, publishing and more.') }}</small>
                    </div>
                </div>
                <div class="se-bwp-wallet-row">
                    <div>
                        <div class="se-bwp-wallet-amount">$42<sup>.50</sup></div>
                        <small>{{ __('Available balance · Example preview') }}</small>
                    </div>
                    <a href="{{ route('register') }}" class="se-bwp-gradient-btn">{{ __('Top Up Wallet') }} <span>＋</span></a>
                </div>
                <div class="se-bwp-info-row">
                    <span class="se-bwp-info-icon">▤</span>
                    <span>{{ __('Wallet credits can be used for AI content, extra publishing, and premium features.') }}</span>
                    <span>›</span>
                </div>
                <div class="se-bwp-hand-note se-bwp-topup-note">{{ __('Top up here') }}
                    <svg viewBox="0 0 120 85" aria-hidden="true"><path d="M7 5 C80 5 110 25 85 77"/><path d="M78 65 L86 79 L98 68"/></svg>
                </div>
            </div>

            @php
                $previewPlan = $plans->firstWhere('is_popular', true) ?? $plans->firstWhere('is_free', false) ?? $plans->first();
                $previewFeatures = $previewPlan ? $features->whereIn('type', ['capacity', 'quota'])->filter(fn ($feature) => isset($previewPlan->entitlement_settings[$feature->key]))->take(3) : collect();
            @endphp
            @if($previewPlan)
            <div class="se-bwp-card se-bwp-plan-card">
                <div class="se-bwp-plan-top">
                    <div class="se-bwp-card-head">
                        <div class="se-bwp-icon gold">♛</div>
                        <div>
                            <strong>{{ __('Example plan') }}</strong>
                            <small>{{ __('Explore a package from our current plans.') }}</small>
                        </div>
                    </div>
                    <span class="se-bwp-active">{{ $previewPlan->is_free ? __('Free trial') : __('Monthly') }}</span>
                </div>
                <div class="se-bwp-plan-row">
                    <div>
                        <h3>{{ $previewPlan->name }}</h3>
                        <strong>{{ number_format($previewPlan->price, 2) }} {{ $previewPlan->currency }} <small>{{ $previewPlan->is_free ? __(':days-day free trial', ['days' => data_get($previewPlan->meta, 'trial_days')]) : __('/ month') }}</small></strong>
                        <p>{{ __('Example monthly subscription') }}</p>
                    </div>
                    <a href="{{ route('pricing') }}" class="se-bwp-outline-btn">{{ __('Manage Plan') }}</a>
                </div>
                <div class="se-bwp-plan-stats">
                    @foreach($previewFeatures as $feature)
                    <div><b>{{ $presentation->value($previewPlan, $feature) }}</b><span>{{ $feature->name }}</span></div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- HOW IT WORKS --}}
        <div class="se-bwp-workflow-grid">
            <div class="se-bwp-workflow-copy">
                <span class="se-bwp-pill">{{ __('HOW IT WORKS') }}</span>
                <h2>{{ __('From idea to growth') }}</h2>
                <div class="se-bwp-title-line"></div>
                <p>{{ __('Turn your ideas into powerful content — in just a few clicks.') }}</p>
            </div>

            <div class="se-bwp-steps">
                <div class="se-bwp-step">
                    <div class="se-bwp-step-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 4-1 12-12-3-3L5 16l-1 4Z"/><path d="m14 7 3 3M13 20h7"/></svg></div>
                    <b>{{ __('1. Create') }}</b>
                    <p>{{ __('Generate content with AI') }}<br>{{ __('or start from scratch.') }}</p>
                </div>
                <svg class="se-bwp-connector" viewBox="0 0 120 45" aria-hidden="true"><path class="se-bwp-connector-line" d="M4 28 C40 3 80 45 116 18"/><path class="se-bwp-arrowhead" d="M110.40 22.20 L116 18 M111.20 31.60 L116 18 L101.60 18.80"/></svg>
                <div class="se-bwp-step">
                    <div class="se-bwp-step-icon pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h3m3 0h2"/></svg></div>
                    <b>{{ __('2. Schedule') }}</b>
                    <p>{{ __('Plan your posts across') }}<br>{{ __('all your social channels.') }}</p>
                </div>
                <svg class="se-bwp-connector" viewBox="0 0 120 45" aria-hidden="true"><path class="se-bwp-connector-line" d="M4 18 C40 45 80 3 116 28"/><path class="se-bwp-arrowhead" d="M110.25 24.01 L116 28 M101.58 27.73 L116 28 L110.71 14.58"/></svg>
                <div class="se-bwp-step">
                    <div class="se-bwp-step-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 3-7 18-4-7-7-4L21 3Z"/><path d="m10 14 6-6"/></svg></div>
                    <b>{{ __('3. Publish') }}</b>
                    <p>{{ __('Go live automatically') }}<br>{{ __('at the best times.') }}</p>
                </div>
                <svg class="se-bwp-connector" viewBox="0 0 120 45" aria-hidden="true"><path class="se-bwp-connector-line" d="M4 28 C40 3 80 45 116 18"/><path class="se-bwp-arrowhead" d="M110.40 22.20 L116 18 M111.20 31.60 L116 18 L101.60 18.80"/></svg>
                <div class="se-bwp-step">
                    <div class="se-bwp-step-icon violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3v18h17M8 17v-5m5 5V8m5 9V4"/></svg></div>
                    <b>{{ __('4. Analyze') }}</b>
                    <p>{{ __('Track performance') }}<br>{{ __('and see what works.') }}</p>
                </div>
                <svg class="se-bwp-connector" viewBox="0 0 120 45" aria-hidden="true"><path class="se-bwp-connector-line" d="M4 18 C40 45 80 3 116 28"/><path class="se-bwp-arrowhead" d="M110.25 24.01 L116 28 M101.58 27.73 L116 28 L110.71 14.58"/></svg>
                <div class="se-bwp-step">
                    <div class="se-bwp-step-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 17 6-6 4 4 8-10M15 5h6v6"/></svg></div>
                    <b>{{ __('5. Grow') }}</b>
                    <p>{{ __('Reach more people') }}<br>{{ __('and build your brand.') }}</p>
                </div>
            </div>

            <div class="se-bwp-hand-note se-bwp-growth-note">{{ __('From idea to growth') }}
                <svg viewBox="0 0 160 95" aria-hidden="true"><path d="M148 8 C146 55 130 72 88 76"/><path d="M99 66 L87 76 L100 83"/></svg>
            </div>
        </div>

        {{-- PRICING --}}
        <div class="se-bwp-pricing-grid" id="home-pricing" data-pricing-page>
            <div class="se-bwp-pricing-copy">
                <div class="se-home-pricing-heading">
                    <span class="se-bwp-pill">{{ __('PRICING') }}</span>
                    <h2>{{ __('Plans for every') }} <br>{{ __('creator’s journey') }}</h2>
                    <p>{{ $plans->contains('is_free', true) ? __('Start free and scale as you grow. Simple, transparent pricing — no hidden fees.') : __('Simple, transparent pricing. Choose the plan that fits your growth.') }}</p>
                </div>
                @if($plans->contains('is_free', false))
                <nav class="se-home-billing" aria-label="{{ __('Billing period') }}">
                    <a href="{{ route('home', ['billing' => 'monthly']) }}#home-pricing" data-billing="monthly" class="{{ !$yearly ? 'is-selected' : '' }}" @if(!$yearly) aria-current="true" @endif>{{ __('Monthly') }}</a>
                    <a href="{{ route('home', ['billing' => 'yearly']) }}#home-pricing" data-billing="yearly" class="{{ $yearly ? 'is-selected' : '' }}" @if($yearly) aria-current="true" @endif>{{ __('Yearly') }}@if($maxSaving > 0)<span class="se-home-billing-saving">{{ __('Save up to :percent%', ['percent' => $maxSaving]) }}</span>@endif</a>
                </nav>
                @endif
            </div>

            @forelse($plans as $plan)
                @php
                    $highlights = $presentation->highlights($plan, $features);
                    $saving = $presentation->yearlySaving($plan);
                    $palette = ['mint', 'blue', 'violet', 'amber'];
                    $planStyle = match ($plan->slug) { 'free_trial' => 'mint', 'basic', 'starter' => 'blue', 'pro' => 'violet', 'business', 'empire' => 'amber', default => $palette[$loop->index % 4] };
                    $planIcon = ['mint' => 'sprout', 'blue' => 'publish', 'violet' => 'spark', 'amber' => 'building'][$planStyle];
                    $canCheckout = auth()->check() && !auth()->user()->isTeamMember() && auth()->user()->hasRole('seller') && !$plan->is_free;
                    $planUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'monthly']) : (auth()->check() ? route('dashboard') : route('register'));
                    $yearlyUrl = $canCheckout ? route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'yearly']) : $planUrl;
                @endphp
                <article class="se-bwp-price-card se-package-card se-package-{{ $planStyle }} {{ $plan->is_popular ? 'featured' : '' }}" data-home-plan="{{ $plan->id }}">
                    @if($plan->is_popular)
                    <div class="se-bwp-popular">
                        {{ __('Recommended plan') }}
                    </div>
                    @endif
                    <div class="se-bwp-price-head"><span class="se-bwp-price-icon se-package-icon" aria-hidden="true">@include('front.partials.home-icon', ['icon' => $planIcon])</span><b>{{ $plan->name }}</b></div>
                    <p class="se-package-description">{{ $plan->description }}</p>
                    <div class="se-package-price-block" aria-live="polite" aria-atomic="true"><div class="se-bwp-price"><strong data-price data-monthly="{{ number_format($plan->price, 2) }}" data-yearly="{{ number_format($plan->is_free ? $plan->price : $plan->yearly_price, 2) }}">{{ number_format($yearly && !$plan->is_free ? $plan->yearly_price : $plan->price, 2) }}</strong> <span>{{ $plan->currency }}@unless($plan->is_free) <span data-period data-monthly="{{ __('/ month') }}" data-yearly="{{ __('/ year') }}">{{ $yearly ? __('/ year') : __('/ month') }}</span>@endunless</span></div>
                    <p class="se-bwp-trial-duration">@if($plan->is_free){{ __(':days-day free trial', ['days' => data_get($plan->meta, 'trial_days')]) }}@else<span data-billing-note data-monthly="{{ __('Billed monthly') }}" data-yearly="{{ __(':amount :currency / month, billed yearly', ['amount' => number_format($plan->yearly_price / 12, 2), 'currency' => $plan->currency]) }}">{{ $yearly ? __(':amount :currency / month, billed yearly', ['amount' => number_format($plan->yearly_price / 12, 2), 'currency' => $plan->currency]) : __('Billed monthly') }}</span>@endif</p></div>
                    @if($saving > 0)<span class="se-home-plan-saving" data-saving @if(!$yearly) hidden @endif>{{ __('Save :percent% with yearly billing', ['percent' => $saving]) }}</span>@endif
                    @include('front.partials.package-allowances')
                    <a href="{{ $yearly ? $yearlyUrl : $planUrl }}" data-plan-cta data-monthly="{{ $planUrl }}" data-yearly="{{ $yearlyUrl }}" class="se-bwp-price-btn {{ $plan->is_popular ? 'gradient' : 'outline' }}">{{ auth()->check() ? ($canCheckout ? __('Choose :plan', ['plan' => $plan->name]) : __('Open your workspace')) : ($plan->is_free ? __('Start free') : __('Get started')) }}</a>
                </article>
            @empty
                <div class="se-bwp-pricing-empty"><p>{{ __('Our plans are being updated. Please check back soon.') }}</p></div>
            @endforelse
            @if($plans->isNotEmpty())<div class="se-bwp-pricing-more"><a href="{{ route('pricing', ['billing' => $yearly ? 'yearly' : 'monthly']) }}#plan-comparison" data-plan-cta data-monthly="{{ route('pricing', ['billing' => 'monthly']) }}#plan-comparison" data-yearly="{{ route('pricing', ['billing' => 'yearly']) }}#plan-comparison" class="theme-button theme-button-outline">{{ __('Compare all features') }} <span aria-hidden="true">→</span></a></div>@endif

        </div>
    </div>
</section>
