<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Socialeaz — Your social, in full bloom.</title>
    <meta name="description" content="Create, plan, publish and grow with Socialeaz, your AI social media workspace.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/home-yellow.css') }}?v=3">
</head>
<body>
<svg class="brand-filter-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><defs>
    <filter id="yellow-brand-filter" color-interpolation-filters="sRGB">
        <feColorMatrix type="saturate" values="0" />
        <feComponentTransfer>
            <feFuncR type="table" tableValues="0.04 0.86 1 1 1" />
            <feFuncG type="table" tableValues="0.05 0.62 0.84 0.95 1" />
            <feFuncB type="table" tableValues="0.10 0.04 0.21 0.74 1" />
        </feComponentTransfer>
    </filter>
</defs></svg>
<a class="skip-link" href="#main">Skip to content</a>
<header class="nav wrap">
    <a class="brand" href="{{ route('home.yellow') }}" aria-label="Socialeaz yellow home"><img class="brand-logo" src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="2172" height="724"></a>
    <nav aria-label="Primary navigation"><a href="#product">Product</a><a href="#copilot">AI Copilot</a><a href="#solutions">Solutions</a><a href="{{ route('pricing') }}">Pricing</a></nav>
    <div class="nav-actions"><a href="{{ route('login') }}">Log in</a><a class="button dark small" href="{{ route('register') }}">Get started <span>↗</span></a></div>
</header>
<main id="main">
    <section class="hero wrap" aria-labelledby="hero-title">
        <div class="hero-copy"><p class="eyebrow"><span class="status-dot"></span> YOUR IDEAS. A LITTLE AI. ENDLESS POSSIBILITY.</p><h1 id="hero-title">Social media.<br>More creative.<br><span>More you.</span></h1><p class="lead">Your ideas deserve to shine. Create, plan and grow with an AI-powered workspace that brings all your social media together.</p><div class="actions"><a class="button dark" href="{{ route('register') }}">Start for free <span>↗</span></a><a class="button secondary" href="#copilot"><span class="play-icon">▶</span> Explore AI Copilot</a></div><div class="proof"><img src="{{ asset('assets/images/home-theme/avatars.png') }}" width="137" height="45" alt="Socialeaz creators"><p><span class="proof-stars" aria-hidden="true">★★★★★</span><br><strong>Big ideas. Good company.</strong><br>Built for creators, teams & agencies.</p></div></div>
        <div class="hero-visual">
            <div class="visual-orbit orbit-one" aria-hidden="true"></div><div class="visual-orbit orbit-two" aria-hidden="true"></div>
            <span class="hero-spark spark-one" aria-hidden="true">✦</span><span class="hero-spark spark-two" aria-hidden="true">✧</span>
            <div class="floating-tag"><span class="status-dot"></span> Your next big idea starts here</div>
            <div class="visual-image"><img src="{{ asset('assets/images/home-theme/hero.png') }}" width="1672" height="941" alt="Creator surrounded by social platforms and engagement insights" fetchpriority="high"></div>
            <div class="idea-card"><span class="icon">@include('front.partials.home-icon', ['icon' => 'spark'])</span><div><span class="mini-label">YOUR CREATIVE SIDEKICK</span><strong>Goodbye, blank canvas.</strong><p>Say hello to your AI Copilot.</p></div><span class="card-check">✓</span></div>
            <div class="channel-badge"><span class="stacked-icons"><b>◎</b><b>♪</b><b>in</b></span><div><strong>One space. Every channel.</strong><small>Everything works better together.</small></div></div>
        </div>
    </section>
    <div class="quick-features wrap" aria-label="Workspace features">
        @foreach ([['spark', 'AI Content Creation', 'Your imagination, amplified.'], ['calendar', 'Smart Scheduling', 'Always one step ahead.'], ['chart', 'Clearer Insights', 'Make every move count.'], ['team', 'Team Collaboration', 'Great things happen together.']] as [$icon, $title, $copy])
        <a href="{{ $icon === 'team' ? '#solutions' : '#product' }}"><span class="icon">@include('front.partials.home-icon', ['icon' => $icon])</span><div><strong>{{ $title }}</strong><p>{{ $copy }}</p></div></a>
        @endforeach
    </div>
    <div class="platform-strip"><div class="wrap"><p>ONE WORKSPACE.<br><strong>ALL YOUR CHANNELS.</strong></p><div class="platforms"><span><b class="social-icon instagram">◎</b> Instagram</span><span><b class="social-icon tiktok">♪</b> TikTok</span><span><b class="social-icon linkedin">in</b> LinkedIn</span><span><b class="social-icon facebook">f</b> Facebook</span><span><b class="social-icon youtube">▶</b> YouTube</span><span><b class="social-icon x">𝕏</b></span></div></div></div>
    <section class="section wrap" id="product" aria-labelledby="product-title"><div class="section-heading"><div><p class="eyebrow">ONE WORKSPACE. SO MANY POSSIBILITIES.</p><h2 id="product-title">A little less effort.<br>A lot more <span class="highlight">possibility.</span></h2></div><p>Everything you need to show up consistently,<br>connect with your audience, and keep growing.</p></div><div class="features">
        @foreach ([['spark', '01', 'Find your creative spark.', 'Turn a blank page into captions, ideas and hashtags with your AI creative partner.', '#copilot'], ['calendar', '02', 'Get ahead. Stay consistent.', 'Plan your calendar and schedule content across your social channels in one place.', '#planner'], ['chart', '03', 'See what makes an impact.', 'Understand your audience and use performance insights to shape your next move.', '#analytics']] as [$icon, $number, $title, $copy, $link])
        <a class="feature" href="{{ $link }}"><div class="feature-top"><span class="icon">@include('front.partials.home-icon', ['icon' => $icon])</span><span>{{ $number }}</span></div><h3>{{ $title }}</h3><p>{{ $copy }}</p><span class="feature-arrow" aria-hidden="true">↗</span></a>
        @endforeach
    </div></section>
    <section class="copilot wrap" id="copilot" aria-labelledby="copilot-title"><div><p class="eyebrow">✦ YOUR NEW CREATIVE SIDEKICK</p><h2 id="copilot-title">Your creative partner.<br><span class="highlight">Always inspired.</span></h2><p>Stuck on what to say? Let AI Copilot help you find the words. Generate captions, brainstorm fresh ideas, and make every post sound like you.</p><a class="button dark" href="{{ route('register') }}">Meet your AI Copilot <span>↗</span></a></div><div class="copilot-preview"><div class="window-bar"><span></span><span></span><span></span><small>Socialeaz AI Studio</small><span class="studio-status">✦ AI powered</span></div><div class="preview-heading"><span class="icon">✦</span><strong>From “what if” to ready to post.</strong></div><img src="{{ asset('assets/images/home-theme/copilot-composer.png') }}" width="438" height="113" alt="AI Copilot tools for generating captions and social ideas" loading="lazy"><img src="{{ asset('assets/images/home-theme/copilot-post.png') }}" width="509" height="131" alt="An AI generated social media post ready for review" loading="lazy"><span class="preview-note">A head start for your next great idea. ✧</span></div></section>
    <section class="section wrap tools" aria-label="Planning and analytics"><article id="planner"><div class="tool-badge">@include('front.partials.home-icon', ['icon' => 'calendar']) CONTENT PLANNER</div><p class="eyebrow">A LITTLE PLANNING GOES A LONG WAY</p><h2>Your calendar.<br>Under control.</h2><p>Give every idea a place. Plan ahead, schedule your posts, and keep your content moving.</p><img src="{{ asset('assets/images/home-theme/planner.png') }}" width="496" height="226" alt="Monthly content calendar with scheduled social posts" loading="lazy"></article><article id="analytics"><div class="tool-badge">@include('front.partials.home-icon', ['icon' => 'chart']) ANALYTICS & INSIGHTS</div><p class="eyebrow">MAKE YOUR NEXT MOVE COUNT</p><h2>Turn insights<br>into momentum.</h2><p>See what’s working, learn about your audience, and build on the content they love.</p><img src="{{ asset('assets/images/home-theme/analytics.png') }}" width="420" height="226" alt="Social media analytics and audience growth chart" loading="lazy"></article></section>
    <section class="team wrap" id="solutions"><div><p class="eyebrow">BETTER, TOGETHER</p><h2>One team.<br>On the same page.</h2><p>Bring your creators, clients and collaborators together. Share feedback, manage approvals, and keep every account in sync.</p><ul class="team-checks"><li>Seamless feedback & approvals</li><li>Everyone connected, every account in sync</li><li>A workspace that grows with your team</li></ul><a class="text-link" href="{{ route('register') }}">Find your flow <span>↗</span></a></div><img src="{{ asset('assets/images/home-theme/approvals.png') }}" width="516" height="155" alt="Team workspace with content approvals and review requests" loading="lazy"></section>
    <section class="cta wrap"><div class="wrap"><p class="eyebrow">✦ YOUR NEXT CHAPTER STARTS HERE</p><h2>Your ideas are ready.<br><span>Let’s make them shine.</span></h2><p class="cta-lead">A fresh start for your social media. All in one beautiful workspace.</p><a class="button dark" href="{{ route('register') }}">Let’s make it happen <span>↗</span></a></div><span class="cta-spark" aria-hidden="true">✳</span></section>
</main>
<footer class="wrap"><a class="brand" href="{{ route('home.yellow') }}"><img class="brand-logo" src="{{ asset('assets/images/home-theme/logo.png') }}" alt="Socialeaz" width="2172" height="724"></a><p>Social media made eaz.</p><nav aria-label="Footer navigation"><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('contact') }}">Contact</a></nav><small>© {{ date('Y') }} Socialeaz</small></footer>
</body>
</html>
