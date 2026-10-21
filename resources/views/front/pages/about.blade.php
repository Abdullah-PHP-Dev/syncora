@extends('layouts.apps')
@section('title', __('About Us') . ' — Socialeaz')
@section('meta_description', __('Meet Socialeaz: one workspace for social content, collaboration and growth.'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/about.css') }}?v={{ filemtime(public_path('assets/css/about.css')) }}">
@endpush
@section('content')
<div class="theme-page about-page">
    <section class="about-hero" aria-labelledby="about-title">
        <div class="about-hero-copy"><span class="theme-label">@include('front.partials.home-icon', ['icon' => 'spark']) {{ __('ABOUT SOCIALEAZ') }}</span><h1 id="about-title">{{ __('One workspace.') }}<br><span>{{ __('So much possibility.') }}</span></h1><p>{{ __('We bring the moving parts of social media together, so your ideas, your people and your next big move have a place to grow.') }}</p><div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-primary">{{ __('Start for free') }} <span aria-hidden="true">→</span></a><a href="#our-story" class="theme-button theme-button-outline">{{ __('Get to know us') }} <span aria-hidden="true">↓</span></a></div></div>
        <div class="about-hero-art"><img class="hero-illustration" src="{{ asset('assets/images/home-theme/about-creators.webp') }}" width="1536" height="1024" alt="{{ __('Illustration of creators collaborating around a laptop with social workspace tools') }}" fetchpriority="high"></div>
    </section>
    <div class="about-principles-strip" aria-label="{{ __('The Socialeaz approach') }}">@foreach([['spark','purple','Create with confidence'],['calendar','blue','Stay consistent'],['team','pink','Work together'],['chart','green','Learn as you grow']] as [$icon,$color,$label])<div><span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span><strong>{{ __($label) }}</strong></div>@endforeach</div>

    <section class="about-strengths" aria-labelledby="strengths-title">
        <div class="about-section-heading"><p class="theme-eyebrow">{{ __('WHAT SOCIALEAZ BRINGS TO YOUR DAY') }}</p><h2 id="strengths-title">{{ __('The tools connect.') }}<br>{{ __('Your work flows.') }}</h2><p>{{ __('From the first idea to the conversation after publishing, your workflow stays in one place.') }}</p></div>
        <div class="about-strength-grid">
            @foreach([
                ['spark','purple','ai-assistant','AI that starts with your ideas','Find a starting point for captions, ideas and hashtags, then make the result your own.'],
                ['calendar','blue','scheduling-publishing','A clearer content calendar','Plan upcoming posts and choose when they go live across connected accounts.'],
                ['link','cyan','integrations','Your channels, connected','Bring supported platforms and services into the workspace you use every day.'],
                ['team','pink','engagement','Conversations with context','Keep messages and comments close to your content, where your team can respond.'],
                ['chart','green','analytics','Insights for your next move','Understand audience and content performance, then build on what works.'],
                ['shield','amber','collaboration','Room for your whole team','Coordinate roles, content review and publishing as your brands and team grow.'],
            ] as [$icon,$color,$slug,$title,$description])
            <article class="about-strength-card"><span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span><h3>{{ __($title) }}</h3><p>{{ __($description) }}</p><a href="{{ route('product.'.$slug) }}">{{ __('Explore this feature') }} <span aria-hidden="true">↗</span></a></article>
            @endforeach
        </div>
    </section>

    <section class="about-audiences" aria-labelledby="audiences-title">
        <div class="about-section-heading"><p class="theme-eyebrow">{{ __('WHO WE BUILD FOR') }}</p><h2 id="audiences-title">{{ __('Different ambitions.') }}<br>{{ __('A shared need for simplicity.') }}</h2></div>
        <div class="about-audience-grid">
            @foreach([
                ['spark','purple','Creators & small businesses','More time for the work you love.','Turn your ideas into posts, keep your channels active and build your social presence at your own pace.',['Create amazing content','Post Scheduling','Analytics & Insights'],'content-creation'],
                ['team','blue','Agencies & social teams','A place to keep everyone aligned.','Bring brand accounts, content plans and collaborators together as you manage more work.',['Team Collaboration','Manage client accounts','Streamline approval workflows'],'collaboration'],
                ['chart','green','Brands & organizations','Make every update count.','Plan your communication, stay connected to your audience and understand how your content performs.',['Plan everything','Engagement','Analytics'],'analytics'],
            ] as [$icon,$color,$title,$subtitle,$description,$bullets,$slug])
            <article class="about-audience-card about-audience-{{ $color }}"><span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span><h3>{{ __($title) }}</h3><strong>{{ __($subtitle) }}</strong><p>{{ __($description) }}</p><ul>@foreach($bullets as $bullet)<li>{{ __($bullet) }}</li>@endforeach</ul><a href="{{ route('product.'.$slug) }}">{{ __('Find your workflow') }} <span aria-hidden="true">→</span></a></article>
            @endforeach
        </div>
    </section>

    <section class="about-story" id="our-story" aria-labelledby="story-title">
        <div class="about-story-art"><div class="about-story-mark"><img src="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}" width="100" height="100" alt="Socialeaz"></div><p>{{ __('Social media') }}<br><strong>{{ __('made eaz.') }}</strong></p><div class="about-story-tags"><span>{{ __('Create') }}</span><span>{{ __('Collaborate') }}</span><span>{{ __('Grow') }}</span></div></div>
        <div class="about-story-copy"><p class="theme-eyebrow">{{ __('WHY SOCIALEAZ EXISTS') }}</p><h2 id="story-title">{{ __('Your ideas deserve more of your time.') }}</h2><p>{{ __('A good social presence takes creativity, consistency and conversation. The tools behind it should help those things work together.') }}</p><p>{{ __('That is the idea behind Socialeaz: bring creation, planning, publishing, engagement and teamwork into a connected workspace, with AI there to help when you need it.') }}</p><p>{{ __('Our focus is practical: clearer workflows, room for your own voice and tools that support your next step.') }}</p><a href="{{ route('contact') }}" class="theme-learn">{{ __('Talk to our team') }} <span aria-hidden="true">→</span></a></div>
    </section>

    <section class="about-values" aria-labelledby="values-title"><div class="about-section-heading"><p class="theme-eyebrow">{{ __('WHAT MATTERS TO US') }}</p><h2 id="values-title">{{ __('A few principles.') }}<br>{{ __('In everything we build.') }}</h2></div><div class="about-value-grid">
        @foreach([['spark','purple','Make room for creativity','Let your ideas lead. Use AI for support while keeping your voice and judgment in the work.'],['team','blue','Build for collaboration','Help people stay aligned through shared content, clear roles and connected workflows.'],['shield','green','Keep control with you','Review your content, manage your connections and decide how your workspace is used.'],['chart','pink','Keep learning','Use feedback and results to make your next piece of content, and your next decision, better.']] as [$icon,$color,$title,$description])<article><span class="theme-icon theme-icon-{{ $color }}">@include('front.partials.home-icon', ['icon' => $icon])</span><h3>{{ __($title) }}</h3><p>{{ __($description) }}</p></article>@endforeach
    </div></section>

    <section class="about-faq" aria-labelledby="about-faq-title"><div class="about-section-heading"><p class="theme-eyebrow">{{ __('A LITTLE MORE ABOUT US') }}</p><h2 id="about-faq-title">{{ __('Your questions, answered.') }}</h2><p>{{ __('Want to know more? Our team is a message away.') }}</p><a href="{{ route('contact') }}" class="theme-learn">{{ __('Contact') }} <span aria-hidden="true">→</span></a></div><div>
        @foreach([['What is Socialeaz?','Socialeaz is an AI-supported social media workspace for creating, scheduling and publishing content, managing conversations, reviewing performance and collaborating with your team.'],['Can I use Socialeaz with my team?','Yes. Explore collaboration features and compare the team-member limits of each subscription on the pricing page.'],['Does Socialeaz support English and Arabic?','Yes. You can switch between English and Arabic from the header, with a right-to-left layout for Arabic.'],['How do I choose a plan?','Compare current prices, features and usage limits on our pricing page. Contact us if you need help choosing.']] as [$question,$answer])<details @if($loop->first) open @endif><summary>{{ __($question) }}<span aria-hidden="true">+</span></summary><p>{{ __($answer) }}</p></details>@endforeach
    </div></section>

    <section class="about-contact" aria-labelledby="about-contact-title"><div><span class="theme-label">@include('front.partials.home-icon', ['icon' => 'team']) {{ __('LET’S TALK') }}</span><h2 id="about-contact-title">{{ __('Good conversations start here.') }}</h2><p>{{ __('Questions, feedback or a bigger plan for your team? Tell us what you have in mind.') }}</p></div><div class="about-contact-actions"><a href="mailto:support@socialeaz.com" dir="ltr">support@socialeaz.com</a><a href="{{ route('contact') }}" class="theme-button theme-button-primary">{{ __('Contact & support') }} <span aria-hidden="true">→</span></a></div></section>
    <section class="theme-final-cta"><div><h2>{{ __('Your next idea has a home.') }}</h2><p>{{ __('Bring your ideas, channels and team together with Socialeaz.') }}</p></div><div class="theme-actions"><a href="{{ route('register') }}" class="theme-button theme-button-white">{{ __('Start for free') }} <span aria-hidden="true">→</span></a><a href="{{ route('pricing') }}" class="theme-button theme-button-ghost">{{ __('Compare plans') }}</a></div></section>
</div>
@endsection
