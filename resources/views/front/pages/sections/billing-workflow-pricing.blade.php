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

            <div class="se-bwp-card se-bwp-plan-card">
                <div class="se-bwp-plan-top">
                    <div class="se-bwp-card-head">
                        <div class="se-bwp-icon gold">♛</div>
                        <div>
                            <strong>{{ __('Current Plan') }}</strong>
                            <small>{{ __('You’re all set! Here’s your active subscription.') }}</small>
                        </div>
                    </div>
                    <span class="se-bwp-active">{{ __('Active') }}</span>
                </div>
                <div class="se-bwp-plan-row">
                    <div>
                        <h3>{{ __('Pro Plan') }}</h3>
                        <strong>$19 <small>{{ __('/ month') }}</small></strong>
                        <p>{{ __('Example monthly subscription') }}</p>
                    </div>
                    <a href="{{ route('pricing') }}" class="se-bwp-outline-btn">{{ __('Manage Plan') }}</a>
                </div>
                <div class="se-bwp-plan-stats">
                    <div><b>500</b><span>{{ __('AI credits / month') }}</span></div>
                    <div><b>20</b><span>{{ __('Social accounts') }}</span></div>
                    <div><b>{{ __('Unlimited') }}</b><span>{{ __('Scheduling') }}</span></div>
                </div>
            </div>
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
        <div class="se-bwp-pricing-grid">
            <div class="se-bwp-pricing-copy">
                <span class="se-bwp-pill">{{ __('PRICING') }}</span>
                <h2>{{ __('Plans for every') }}<br>{{ __('creator’s journey') }}</h2>
                <p>{{ __('Start free and scale as you grow. Simple, transparent pricing — no hidden fees.') }}</p>
                <div class="se-bwp-hand-note se-bwp-everything-note">{{ __('Everything you need') }}<br>{{ __('to grow on social') }}
                    <svg viewBox="0 0 150 80" aria-hidden="true"><path d="M8 52 C55 60 95 60 140 42"/><path d="M128 35 L140 42 L130 51"/></svg>
                </div>
            </div>

            <div class="se-bwp-price-card">
                <div class="se-bwp-price-head"><span class="se-bwp-price-icon blue">➤</span><div><b>{{ __('Free') }}</b><small>{{ __('Perfect for getting started') }}</small></div></div>
                <div class="se-bwp-price"><strong>$0</strong> <span>{{ __('forever') }}</span></div>
                <ul>
                    <li>{{ __('AI content generation (limited)') }}</li>
                    <li>{{ __('Up to 3 social accounts') }}</li>
                    <li>{{ __('10 scheduled posts / month') }}</li>
                    <li>{{ __('Basic analytics') }}</li>
                    <li>{{ __('Standard support') }}</li>
                </ul>
                <a href="{{ route('register') }}" class="se-bwp-price-btn outline">{{ __('Get Started Free') }}</a>
            </div>

            <div class="se-bwp-price-card featured">
                <div class="se-bwp-popular">{{ __('Most Popular') }}</div>
                <div class="se-bwp-price-head"><span class="se-bwp-price-icon purple">♛</span><div><b>{{ __('Pro') }}</b><small>{{ __('For creators and small teams') }}</small></div></div>
                <div class="se-bwp-price"><strong>$19</strong> <span>{{ __('/ month') }}</span></div>
                <ul>
                    <li>{{ __('500 AI credits / month') }}</li>
                    <li>{{ __('Up to 20 social accounts') }}</li>
                    <li>{{ __('Unlimited scheduling') }}</li>
                    <li>{{ __('Advanced analytics') }}</li>
                    <li>{{ __('Team collaboration (up to 3 members)') }}</li>
                    <li>{{ __('Priority support') }}</li>
                </ul>
                <a href="{{ route('pricing') }}" class="se-bwp-price-btn gradient">{{ __('Start Pro Plan →') }}</a>
                <div class="se-bwp-hand-note se-bwp-popular-note">{{ __('Most popular plan') }}
                    <svg viewBox="0 0 125 70" aria-hidden="true"><path d="M8 8 C58 9 90 18 100 54"/><path d="M92 45 L100 56 L109 45"/></svg>
                </div>
            </div>

            <div class="se-bwp-price-card">
                <div class="se-bwp-price-head"><span class="se-bwp-price-icon blue">▥</span><div><b>{{ __('Enterprise') }}</b><small>{{ __('For agencies and large teams') }}</small></div></div>
                <div class="se-bwp-price"><strong class="custom">{{ __('Custom pricing') }}</strong></div>
                <ul>
                    <li>{{ __('Everything in Pro') }}</li>
                    <li>{{ __('Unlimited social accounts') }}</li>
                    <li>{{ __('Custom AI credits') }}</li>
                    <li>{{ __('Advanced team management') }}</li>
                    <li>{{ __('White-label options') }}</li>
                    <li>{{ __('Dedicated account manager') }}</li>
                    <li>{{ __('Custom integrations') }}</li>
                </ul>
                <a href="{{ route('help') }}" class="se-bwp-price-btn outline">{{ __('Contact Sales') }}</a>
            </div>
        </div>
    </div>
</section>
