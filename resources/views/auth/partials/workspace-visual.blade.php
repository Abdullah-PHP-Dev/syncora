<section class="auth-workspace-visual" aria-labelledby="auth-visual-title">
    <span class="theme-label">@include('front.partials.home-icon', ['icon' => 'spark']) {{ $mode === 'register' ? __('START YOUR FREE WORKSPACE') : __('THE AI SOCIAL MEDIA WORKSPACE') }}</span>
    <h1 id="auth-visual-title">{{ $mode === 'register' ? __('Turn your ideas') : __('Your social team,') }}<br><span>{{ $mode === 'register' ? __('into momentum.') : __('in one workspace.') }}</span></h1>
    <p>{{ $mode === 'register' ? __('Build campaigns, create content, manage channels and understand your audience from one intelligent workspace.') : __('Plan, create, collaborate, schedule and measure your social media — with AI doing the busy work.') }}</p>
    <img class="hero-illustration auth-workspace-image" src="{{ asset('assets/images/home-theme/auth-workspace.jpg') }}" width="1536" height="1024" alt="{{ __('Creator at a laptop with AI, calendar, team and secure workspace icons') }}" fetchpriority="high">
    <div class="auth-workspace-benefits">
        @foreach ([['spark', 'AI-powered content'], ['calendar', 'Plan everything'], ['team', 'Team Collaboration']] as [$icon, $label])
            <span>@include('front.partials.home-icon', ['icon' => $icon]) {{ __($label) }}</span>
        @endforeach
    </div>
</section>
