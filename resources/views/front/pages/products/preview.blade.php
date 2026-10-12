@switch($visual)
    @case('content')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-purple">@include('front.partials.home-icon', ['icon' => 'spark'])</span><div><strong>{{ __('Create something worth sharing.') }}</strong><small>{{ __('From first draft to your next post.') }}</small></div></div>
        <img src="{{ asset('assets/images/home-theme/copilot-composer.png') }}" width="438" height="113" alt="{{ __('AI Copilot composer with Generate, Rewrite, Social ideas and Hashtags tools') }}">
        <img src="{{ asset('assets/images/home-theme/copilot-post.png') }}" width="509" height="131" alt="{{ __('Generated product post with a caption and social platform icons') }}">
        @break
    @case('ai')
        <div class="feature-ai-orb">@include('front.partials.home-icon', ['icon' => 'spark'])</div>
        <div class="feature-ai-prompt"><strong>{{ __('Your next idea starts here.') }}</strong><p>{{ __('Ideas · Captions · Hashtags') }}</p></div>
        <img src="{{ asset('assets/images/home-theme/copilot-composer.png') }}" width="438" height="113" alt="{{ __('AI Copilot composer with Generate, Rewrite, Social ideas and Hashtags tools') }}">
        @break
    @case('analytics')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-green">@include('front.partials.home-icon', ['icon' => 'chart'])</span><div><strong>{{ __('Analytics that drive growth.') }}</strong><small>{{ __('Understand the story behind your results.') }}</small></div></div>
        <img src="{{ asset('assets/images/home-theme/analytics.png') }}" width="420" height="226" alt="{{ __('Analytics showing 125K followers, 8.4 percent engagement and an audience growth chart') }}">
        @break
    @case('scheduling')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'calendar'])</span><div><strong>{{ __('Your next month, organized.') }}</strong><small>{{ __('Create today. Publish on your schedule.') }}</small></div></div>
        <img src="{{ asset('assets/images/home-theme/planner.png') }}" width="496" height="226" alt="{{ __('Content planner with a monthly calendar of Instagram, TikTok and LinkedIn posts') }}">
        @break
    @case('collaboration')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-blue">@include('front.partials.home-icon', ['icon' => 'team'])</span><div><strong>{{ __('A shared space for great work.') }}</strong><small>{{ __('Create · Review · Approve') }}</small></div></div>
        <img src="{{ asset('assets/images/home-theme/approvals.png') }}" width="516" height="155" alt="{{ __('Team workspace showing content approvals and review requests') }}">
        <div class="feature-team-row">@foreach(['sarah','james','emma'] as $person)<img src="{{ asset('assets/images/home-theme/'.$person.'.png') }}" width="48" height="48" alt="">@endforeach<span>{{ __('Keep everyone in sync') }}</span></div>
        @break
    @case('engagement')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-pink">@include('front.partials.home-icon', ['icon' => 'team'])</span><div><strong>{{ __('Conversations, connected.') }}</strong><small>{{ __('Illustrative workspace preview') }}</small></div></div>
        <div class="feature-inbox-tabs"><span>{{ __('Messages') }}</span><span>{{ __('Comments') }}</span></div>
        @foreach([['sarah','Instagram','Thanks for sharing!'],['james','Facebook','Could you tell me more?'],['emma','LinkedIn','Love this idea.']] as [$photo,$channel,$message])
        <div class="feature-message"><img src="{{ asset('assets/images/home-theme/'.$photo.'.png') }}" width="44" height="44" alt=""><div><strong>{{ $channel }}</strong><p>{{ __($message) }}</p></div><span class="feature-message-dot" aria-hidden="true"></span></div>
        @endforeach
        @break
    @case('integrations')
        <div class="feature-preview-heading"><span class="theme-icon theme-icon-purple">@include('front.partials.home-icon', ['icon' => 'team'])</span><div><strong>{{ __('Your workspace, connected.') }}</strong><small>{{ __('Social channels & services') }}</small></div></div>
        <div class="feature-connection-scene">
            <div class="feature-connection-ring feature-connection-ring-outer" aria-hidden="true"></div>
            <div class="feature-connection-ring feature-connection-ring-inner" aria-hidden="true"></div>
            <svg class="feature-connection-lines" viewBox="0 0 400 360" preserveAspectRatio="none" aria-hidden="true">
                <defs><linearGradient id="connection-gradient"><stop stop-color="#00bfff"/><stop offset="1" stop-color="#720aff"/></linearGradient></defs>
                <path d="M200 180 C140 180 140 72 80 72 M200 180 V36 M200 180 C260 180 260 72 320 72 M200 180 H360 M200 180 C260 180 260 288 320 288 M200 180 V324 M200 180 C140 180 140 288 80 288 M200 180 H40"/>
                <g><circle cx="136" cy="119" r="4"/><circle cx="200" cy="96" r="4"/><circle cx="264" cy="119" r="4"/><circle cx="300" cy="180" r="4"/><circle cx="264" cy="241" r="4"/><circle cx="200" cy="264" r="4"/><circle cx="136" cy="241" r="4"/><circle cx="100" cy="180" r="4"/></g>
            </svg>
            <div class="feature-integration-hub"><img src="{{ asset('assets/images/home-theme/apple-touch-icon.png') }}" width="80" height="80" alt="Socialeaz"><span aria-hidden="true">Socialeaz</span></div>
            @foreach(['Instagram','Facebook','LinkedIn','YouTube','TikTok','Snapchat','WhatsApp','Email Marketing'] as $platform)
                <span class="feature-platform-icon feature-platform-{{ str_replace(' ', '-', strtolower($platform)) }} feature-orbit-node feature-orbit-node-{{ $loop->iteration }}" role="img" aria-label="{{ __($platform) }}" title="{{ __($platform) }}">@include('front.partials.social-brand-icon', ['platform' => $platform])</span>
            @endforeach
            <span class="feature-connection-spark feature-connection-spark-one" aria-hidden="true">@include('front.partials.home-icon', ['icon' => 'spark'])</span>
            <span class="feature-connection-spark feature-connection-spark-two" aria-hidden="true">@include('front.partials.home-icon', ['icon' => 'spark'])</span>
        </div>
        <p class="feature-preview-note">{{ __('Explore available connections in your workspace.') }}</p>
        @break
@endswitch
