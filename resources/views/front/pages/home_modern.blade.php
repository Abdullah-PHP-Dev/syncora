@extends('layouts.apps')

@section('title', __('Socialeaz — Social media made eaz.'))
@section('meta_description', __('The all-in-one AI social media workspace for creators, teams and agencies. Create, collaborate and grow with Socialeaz.'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/home-billing-workflow.css') }}?v={{ filemtime(public_path('assets/css/home-billing-workflow.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/package-cards.css') }}?v={{ filemtime(public_path('assets/css/package-cards.css')) }}">
@endpush

@section('content')
<div class="theme-page">
    <section class="theme-hero" aria-labelledby="hero-title">
        <div class="theme-hero-copy">
            <p class="theme-eyebrow">{{ __('CREATE · COLLABORATE · GROW') }}</p>
            <h1 id="hero-title">{{ __('Social media') }}<br>{{ __('made') }} <span>{{ __('eaz.') }}</span></h1>
            <p class="theme-hero-lead">{{ __('The all-in-one AI social media workspace') }}<br class="theme-desktop-break"> {{ __('for creators, teams and agencies.') }}</p>
            <div class="theme-actions">
                <a href="{{ route('register') }}" class="theme-button theme-button-primary">{{ __('Start for free') }} <span>→</span></a>
                <a href="#copilot" class="theme-button theme-button-outline">@include('front.partials.home-icon', ['icon' => 'play']) {{ __('Watch demo') }}</a>
            </div>
            <div class="theme-proof">
                <img src="{{ asset('assets/images/home-theme/avatars.png') }}" width="137" height="45" alt="{{ __('Creators using Socialeaz') }}">
                <div><span class="theme-stars" aria-label="{{ __('5 stars') }}">★★★★★</span><p>{{ __('10K+ creators and teams') }}<br>{{ __('already growing with Socialeaz') }}</p></div>
            </div>
        </div>
        <div class="theme-hero-art">
            <img class="hero-illustration" src="{{ asset('assets/images/home-theme/hero.png') }}" width="546" height="309" alt="{{ __('Creator surrounded by social platforms, engagement and follower growth') }}" fetchpriority="high">
        </div>
    </section>

    <section class="theme-feature-strip" aria-label="{{ __('Everything in your workspace') }}">
        @foreach ([['spark', 'purple', 'AI Content Creation', 'Create amazing content', 'in seconds.'], ['calendar', 'blue', 'Post Scheduling', 'Plan and automate', 'across all platforms.'], ['chart', 'pink', 'Analytics & Insights', 'Track performance', 'and get insights.'], ['team', 'blue', 'Team Collaboration', 'Work together with', 'approvals and roles.']] as [$icon, $color, $title, $line1, $line2])
            <article class="theme-feature-pill">
                <span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span>
                <div><h2>{{ __($title) }}</h2><p>{{ __($line1) }}<br>{{ __($line2) }}</p></div>
            </article>
        @endforeach
    </section>

    <section class="theme-copilot" id="copilot" aria-labelledby="copilot-title">
        <div class="theme-copilot-copy">
            <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'spark']) {{ __('AI COPILOT') }}</span>
            <h2 id="copilot-title">{{ __('Your creative partner') }}<br>{{ __('for') }} <span>{{ __('social media.') }}</span></h2>
            <p>{{ __('Get captions, ideas, hashtags and') }}<br class="theme-desktop-break"> {{ __('full content in seconds with AI.') }}</p>
            <a href="{{ route('register') }}" class="theme-button theme-button-primary">{{ __('Try AI Copilot') }} <span>→</span></a>
        </div>
        <div class="theme-copilot-art">
            <span class="theme-copilot-note" aria-hidden="true">{{ __('Create amazing') }}<br>{{ __('content in seconds') }} <span>⤵</span></span>
            <img class="theme-composer-image" src="{{ asset('assets/images/home-theme/copilot-composer.png') }}" width="438" height="113" alt="{{ __('AI Copilot composer with Generate, Rewrite, Social ideas and Hashtags tools') }}" loading="lazy">
            <img class="theme-post-image" src="{{ asset('assets/images/home-theme/copilot-post.png') }}" width="509" height="131" alt="{{ __('Generated product post with a caption and social platform icons') }}" loading="lazy">
        </div>
    </section>

    <section class="theme-product-grid" id="product" aria-label="{{ __('Planning and analytics') }}">
        <article class="theme-product-card" id="planner">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'calendar'])</span>
                <div><h2>{{ __('Plan. Schedule. Stay consistent.') }}</h2><p>{{ __('Organize your content, schedule across all platforms') }}<br class="theme-desktop-break"> {{ __('and never miss a post again.') }}</p></div>
                <a href="{{ route('register') }}" class="theme-learn">{{ __('Learn more') }} <span>→</span></a>
            </div>
            <img src="{{ asset('assets/images/home-theme/planner.png') }}" width="496" height="226" alt="{{ __('Content planner with a monthly calendar of Instagram, TikTok and LinkedIn posts') }}" loading="lazy">
        </article>
        <article class="theme-product-card" id="analytics">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-green">@include('front.partials.home-icon', ['icon' => 'chart'])</span>
                <div><h2>{{ __('Analytics that drive growth.') }}</h2><p>{{ __('Track performance, understand your audience,') }}<br class="theme-desktop-break"> {{ __('and make better decisions.') }}</p></div>
                <a href="{{ route('register') }}" class="theme-learn">{{ __('Learn more') }} <span>→</span></a>
            </div>
            <img src="{{ asset('assets/images/home-theme/analytics.png') }}" width="420" height="226" alt="{{ __('Analytics showing 125K followers, 8.4 percent engagement and an audience growth chart') }}" loading="lazy">
        </article>
    </section>

    <section class="theme-collaboration" id="solutions" aria-labelledby="collaboration-title">
        <div class="theme-collaboration-copy">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'team'])</span>
                <div><h2 id="collaboration-title">{{ __('Built for creators, teams, and agencies.') }}</h2><p>{{ __('Work together, get approvals and manage all your social media') }}<br class="theme-desktop-break"> {{ __('in one place.') }}</p></div>
            </div>
            <ul class="theme-check-list">
                <li>{{ __('Invite team members and set roles') }}</li>
                <li>{{ __('Streamline approval workflows') }}</li>
                <li>{{ __('Manage client accounts') }}</li>
                <li>{{ __('Keep everyone in sync') }}</li>
            </ul>
            <a href="{{ route('register') }}" class="theme-learn">{{ __('Learn more') }} <span>→</span></a>
        </div>
        <img src="{{ asset('assets/images/home-theme/approvals.png') }}" width="516" height="155" alt="{{ __('Team workspace showing content approvals and review requests') }}" loading="lazy">
    </section>

    @include('front.pages.sections.billing-workflow-pricing')

    <section class="theme-testimonials" aria-labelledby="testimonials-title">
        <div class="theme-testimonial-heading">
            <span class="theme-heading-spark">@include('front.partials.home-icon', ['icon' => 'spark'])</span>
            <div><p class="theme-eyebrow">{{ __('WHAT OUR USERS SAY') }}</p><h2 id="testimonials-title">{{ __('Trusted by creators, brands and agencies worldwide.') }}</h2></div>
            <a href="{{ route('about') }}" class="theme-learn">{{ __('View more stories') }} <span>→</span></a>
        </div>
        <div class="theme-testimonial-grid">
            @foreach ([['sarah', 'Sarah Kim', 'Content Creator', 'Socialeaz has completely changed the way we manage our social media. The AI tools save us hours every week!'], ['james', 'James Carter', 'Marketing Agency', 'The best social media tool we’ve used. Super easy to collaborate with our team and the results speak for themselves.'], ['emma', 'Emma Rodriguez', 'Small Business Owner', 'AI Copilot is a game changer! It helps me create better content and grow my audience faster than ever.']] as [$photo, $name, $role, $quote])
                <figure class="theme-testimonial">
                    <img src="{{ asset('assets/images/home-theme/'.$photo.'.png') }}" width="72" height="72" alt="{{ $name }}" loading="lazy">
                    <div><blockquote>“{{ __($quote) }}”</blockquote><figcaption><strong>{{ $name }}</strong><small>{{ __($role) }}</small></figcaption></div>
                    <span class="theme-stars" aria-label="{{ __('5 stars') }}">★★★★★</span>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="theme-final-cta" id="final-cta">
        <div><h2>{{ __('Ready to grow your social media?') }}</h2><p>{{ __('Join thousands of creators, teams and agencies using Socialeaz to create, plan, publish and grow — all with AI.') }}</p></div>
        <div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-white">{{ __('Start for free') }} <span>→</span></a><a href="#copilot" class="theme-button theme-button-ghost">@include('front.partials.home-icon', ['icon' => 'play']) {{ __('Watch demo') }}</a></div>
    </section>
</div>
@endsection
