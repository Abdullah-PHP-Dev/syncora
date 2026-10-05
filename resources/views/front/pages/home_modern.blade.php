@extends('layouts.apps')

@section('title', 'Socialeaz — Social media made eaz.')
@section('meta_description', 'The all-in-one AI social media workspace for creators, teams and agencies. Create, collaborate and grow with Socialeaz.')

@section('content')
<div class="theme-page">
    <section class="theme-hero" aria-labelledby="hero-title">
        <div class="theme-hero-copy">
            <p class="theme-eyebrow">CREATE · COLLABORATE · GROW</p>
            <h1 id="hero-title">Social media<br>made <span>eaz.</span></h1>
            <p class="theme-hero-lead">The all-in-one AI social media workspace<br class="theme-desktop-break"> for creators, teams and agencies.</p>
            <div class="theme-actions">
                <a href="{{ route('register') }}" class="theme-button theme-button-primary">Start for free <span>→</span></a>
                <a href="#copilot" class="theme-button theme-button-outline">@include('front.partials.home-icon', ['icon' => 'play']) Watch demo</a>
            </div>
            <div class="theme-proof">
                <img src="{{ asset('assets/images/home-theme/avatars.png') }}" width="137" height="45" alt="Creators using Socialeaz">
                <div><span class="theme-stars" aria-label="5 stars">★★★★★</span><p>10K+ creators and teams<br>already growing with Socialeaz</p></div>
            </div>
        </div>
        <div class="theme-hero-art">
            <img src="{{ asset('assets/images/home-theme/hero.png') }}" width="546" height="309" alt="Creator surrounded by social platforms, engagement and follower growth" fetchpriority="high">
        </div>
    </section>

    <section class="theme-feature-strip" aria-label="Everything in your workspace">
        @foreach ([['spark', 'purple', 'AI Content Creation', 'Create amazing content', 'in seconds.'], ['calendar', 'blue', 'Post Scheduling', 'Plan and automate', 'across all platforms.'], ['chart', 'pink', 'Analytics & Insights', 'Track performance', 'and get insights.'], ['team', 'blue', 'Team Collaboration', 'Work together with', 'approvals and roles.']] as [$icon, $color, $title, $line1, $line2])
            <article class="theme-feature-pill">
                <span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span>
                <div><h2>{{ $title }}</h2><p>{{ $line1 }}<br>{{ $line2 }}</p></div>
            </article>
        @endforeach
    </section>

    <section class="theme-copilot" id="copilot" aria-labelledby="copilot-title">
        <div class="theme-copilot-copy">
            <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'spark']) AI COPILOT</span>
            <h2 id="copilot-title">Your creative partner<br>for <span>social media.</span></h2>
            <p>Get captions, ideas, hashtags and<br class="theme-desktop-break"> full content in seconds with AI.</p>
            <a href="{{ route('register') }}" class="theme-button theme-button-primary">Try AI Copilot <span>→</span></a>
        </div>
        <div class="theme-copilot-art">
            <span class="theme-copilot-note" aria-hidden="true">Create amazing<br>content in seconds <span>⤵</span></span>
            <img class="theme-composer-image" src="{{ asset('assets/images/home-theme/copilot-composer.png') }}" width="438" height="113" alt="AI Copilot composer with Generate, Rewrite, Social ideas and Hashtags tools" loading="lazy">
            <img class="theme-post-image" src="{{ asset('assets/images/home-theme/copilot-post.png') }}" width="509" height="131" alt="Generated product post with a caption and social platform icons" loading="lazy">
        </div>
    </section>

    <section class="theme-product-grid" id="product" aria-label="Planning and analytics">
        <article class="theme-product-card" id="planner">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'calendar'])</span>
                <div><h2>Plan. Schedule. Stay consistent.</h2><p>Organize your content, schedule across all platforms<br class="theme-desktop-break"> and never miss a post again.</p></div>
                <a href="{{ route('register') }}" class="theme-learn">Learn more <span>→</span></a>
            </div>
            <img src="{{ asset('assets/images/home-theme/planner.png') }}" width="496" height="226" alt="Content planner with a monthly calendar of Instagram, TikTok and LinkedIn posts" loading="lazy">
        </article>
        <article class="theme-product-card" id="analytics">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-green">@include('front.partials.home-icon', ['icon' => 'chart'])</span>
                <div><h2>Analytics that drive growth.</h2><p>Track performance, understand your audience,<br class="theme-desktop-break"> and make better decisions.</p></div>
                <a href="{{ route('register') }}" class="theme-learn">Learn more <span>→</span></a>
            </div>
            <img src="{{ asset('assets/images/home-theme/analytics.png') }}" width="420" height="226" alt="Analytics showing 125K followers, 8.4 percent engagement and an audience growth chart" loading="lazy">
        </article>
    </section>

    <section class="theme-collaboration" id="solutions" aria-labelledby="collaboration-title">
        <div class="theme-collaboration-copy">
            <div class="theme-card-heading">
                <span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'team'])</span>
                <div><h2 id="collaboration-title">Built for creators, teams, and agencies.</h2><p>Work together, get approvals and manage all your social media<br class="theme-desktop-break"> in one place.</p></div>
            </div>
            <ul class="theme-check-list">
                <li>Invite team members and set roles</li>
                <li>Streamline approval workflows</li>
                <li>Manage client accounts</li>
                <li>Keep everyone in sync</li>
            </ul>
            <a href="{{ route('register') }}" class="theme-learn">Learn more <span>→</span></a>
        </div>
        <img src="{{ asset('assets/images/home-theme/approvals.png') }}" width="516" height="155" alt="Team workspace showing content approvals and review requests" loading="lazy">
    </section>

    <section class="theme-testimonials" aria-labelledby="testimonials-title">
        <div class="theme-testimonial-heading">
            <span class="theme-heading-spark">@include('front.partials.home-icon', ['icon' => 'spark'])</span>
            <div><p class="theme-eyebrow">WHAT OUR USERS SAY</p><h2 id="testimonials-title">Trusted by creators, brands and agencies worldwide.</h2></div>
            <a href="{{ route('about') }}" class="theme-learn">View more stories <span>→</span></a>
        </div>
        <div class="theme-testimonial-grid">
            @foreach ([['sarah', 'Sarah Kim', 'Content Creator', 'Socialeaz has completely changed the way we manage our social media. The AI tools save us hours every week!'], ['james', 'James Carter', 'Marketing Agency', 'The best social media tool we’ve used. Super easy to collaborate with our team and the results speak for themselves.'], ['emma', 'Emma Rodriguez', 'Small Business Owner', 'AI Copilot is a game changer! It helps me create better content and grow my audience faster than ever.']] as [$photo, $name, $role, $quote])
                <figure class="theme-testimonial">
                    <img src="{{ asset('assets/images/home-theme/'.$photo.'.png') }}" width="72" height="72" alt="{{ $name }}" loading="lazy">
                    <div><blockquote>“{{ $quote }}”</blockquote><figcaption><strong>{{ $name }}</strong><small>{{ $role }}</small></figcaption></div>
                    <span class="theme-stars" aria-label="5 stars">★★★★★</span>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="theme-final-cta" id="final-cta">
        <div><h2>Ready to grow your social media?</h2><p>Join thousands of creators, teams and agencies using Socialeaz to create, plan, publish and grow — all with AI.</p></div>
        <div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-white">Start for free <span>→</span></a><a href="#copilot" class="theme-button theme-button-ghost">@include('front.partials.home-icon', ['icon' => 'play']) Watch demo</a></div>
    </section>
</div>
@endsection
