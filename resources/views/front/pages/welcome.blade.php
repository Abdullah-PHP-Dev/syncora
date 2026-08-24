@extends('layouts.main')

@section('title', 'Socialeaz — The AI Social Media Workspace')

@section('meta_description')
    The AI-powered social media workspace for creators, teams and agencies.
@endsection

@section('content')


    <section class="hero-section">

        <div class="grid-bg opacity-75"></div>

        <div class="container position-relative">

            <div class="text-center pt-5 pb-4">

                <div
                        class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-white border shadow-sm small fw-bold"
                >

                <span
                        class="rounded-circle bg-purple"
                        style="width:8px;height:8px;"
                ></span>

                    THE AI SOCIAL MEDIA WORKSPACE

                </div>


                <h1
                        class="hero-title display-1 fw-900 tracking-tight mt-4 mx-auto"
                        style="max-width:950px;"
                >

                    Your entire social team.

                    <span class="gradient-text">
                    In one workspace.
                </span>

                </h1>


                <p
                        class="lead text-secondary mx-auto mt-4"
                        style="max-width:700px;line-height:1.7;"
                >
                    Plan, create, collaborate, schedule, publish and measure
                    your social media — with AI doing the busy work.
                </p>


                <div
                        class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4"
                >

                    <a
                            href="{{ route('pricing') }}"
                            class="btn btn-black btn-socialeaz shine px-4 py-3"
                    >
                        Start for free →
                    </a>

                    <a
                            href="{{ route('product') }}"
                            class="btn btn-outline-soft btn-socialeaz px-4 py-3"
                    >
                        See how it works
                    </a>

                </div>


                <div class="small text-secondary mt-3">
                    14-day free trial · No credit card required
                </div>


                {{-- =====================================================
                     PRODUCT PREVIEW
                ====================================================== --}}

                <div class="position-relative mt-5">

                    <div
                            class="position-absolute top-50 start-50 translate-middle rounded-circle"
                            style="
                        width:70%;
                        height:70%;
                        background:rgba(124,58,237,.12);
                        filter:blur(80px);
                    "
                    ></div>


                    <div class="product-preview position-relative">

                        <div class="product-preview-inner">

                            {{-- Browser bar --}}

                            <div class="browser-bar">

                                <div class="d-flex gap-2">

                                    <span class="browser-dot dot-red"></span>
                                    <span class="browser-dot dot-yellow"></span>
                                    <span class="browser-dot dot-green"></span>

                                </div>

                                <div class="small text-secondary d-none d-md-block">
                                    app.socialeaz.com / workspace
                                </div>

                                <div class="small text-success fw-bold">
                                    ● All systems live
                                </div>

                            </div>


                            <div class="row g-0 text-start">

                                {{-- Sidebar --}}

                                <aside class="col-lg-2 preview-sidebar">

                                    <div class="fw-900 small mb-4">
                                        Socialeaz
                                    </div>

                                    <div>

                                        <div class="preview-menu-item active">
                                            <i class="bi bi-house me-1"></i>
                                            Overview
                                        </div>

                                        <div class="preview-menu-item">
                                            <i class="bi bi-stars me-1"></i>
                                            AI Studio
                                        </div>

                                        <div class="preview-menu-item">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            Planner
                                        </div>

                                        <div class="preview-menu-item">
                                            <i class="bi bi-bar-chart me-1"></i>
                                            Analytics
                                        </div>

                                        <div class="preview-menu-item">
                                            <i class="bi bi-chat me-1"></i>
                                            Inbox
                                        </div>

                                    </div>

                                </aside>


                                {{-- Dashboard --}}

                                <main class="col-lg-10 p-4 p-lg-5">

                                    <div
                                            class="d-flex justify-content-between align-items-center"
                                    >

                                        <div>

                                            <div class="small text-secondary">
                                                Tuesday, August 18
                                            </div>

                                            <h3 class="h4 fw-900 mt-1 mb-0">
                                                Good morning 👋
                                            </h3>

                                        </div>

                                        <button
                                                class="btn btn-dark btn-sm d-none d-sm-block"
                                        >
                                            + Create post
                                        </button>

                                    </div>


                                    {{-- KPI Cards --}}

                                    <div class="row g-3 mt-3">

                                        <div class="col-md-4">

                                            <div class="preview-card">

                                                <div class="small text-secondary">
                                                    Published
                                                </div>

                                                <div class="fs-3 fw-900 mt-1">
                                                    248
                                                </div>

                                                <div class="small text-success fw-bold mt-2">
                                                    ↑ 18.4%
                                                </div>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="preview-card">

                                                <div class="small text-secondary">
                                                    Engagement
                                                </div>

                                                <div class="fs-3 fw-900 mt-1">
                                                    8.72%
                                                </div>

                                                <div class="small text-success fw-bold mt-2">
                                                    ↑ 12.1%
                                                </div>

                                            </div>

                                        </div>


                                        <div class="col-md-4">

                                            <div class="preview-card preview-card-black">

                                                <div class="small text-white-50">
                                                    AI Copilot
                                                </div>

                                                <div class="small fw-bold mt-2">
                                                    12 posts ready
                                                </div>

                                                <div
                                                        class="progress mt-3"
                                                        style="height:6px;"
                                                >

                                                    <div
                                                            class="progress-bar bg-purple"
                                                            style="width:80%;"
                                                    ></div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>


                                    {{-- Analytics --}}

                                    <div class="row g-3 mt-1">

                                        <div class="col-md-7">

                                            <div class="preview-card">

                                                <div
                                                        class="d-flex justify-content-between"
                                                >

                                                    <h4 class="small fw-bold mb-0">
                                                        Content performance
                                                    </h4>

                                                    <span class="small text-secondary">
                                                    Last 30 days
                                                </span>

                                                </div>


                                                <div
                                                        class="d-flex align-items-end gap-2 mt-4"
                                                        style="height:140px;"
                                                >

                                                    <div
                                                            class="chart-bar"
                                                            style="height:38%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:52%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:45%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:70%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:61%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:88%;"
                                                    ></div>

                                                    <div
                                                            class="chart-bar"
                                                            style="height:96%;"
                                                    ></div>

                                                </div>

                                            </div>

                                        </div>


                                        {{-- Next up --}}

                                        <div class="col-md-5">

                                            <div class="preview-card">

                                                <h4 class="small fw-bold">
                                                    Next up
                                                </h4>

                                                <div class="mt-3">

                                                    <div
                                                            class="d-flex gap-3 align-items-center mb-3"
                                                    >

                                                        <div
                                                                class="rounded-3 bg-danger-subtle"
                                                                style="width:36px;height:36px;"
                                                        ></div>

                                                        <div>

                                                            <div class="small fw-bold">
                                                                Product launch
                                                            </div>

                                                            <div
                                                                    class="text-secondary"
                                                                    style="font-size:10px;"
                                                            >
                                                                Instagram · 2:30 PM
                                                            </div>

                                                        </div>

                                                    </div>


                                                    <div
                                                            class="d-flex gap-3 align-items-center mb-3"
                                                    >

                                                        <div
                                                                class="rounded-3 bg-primary-subtle"
                                                                style="width:36px;height:36px;"
                                                        ></div>

                                                        <div>

                                                            <div class="small fw-bold">
                                                                Founder story
                                                            </div>

                                                            <div
                                                                    class="text-secondary"
                                                                    style="font-size:10px;"
                                                            >
                                                                LinkedIn · 4:00 PM
                                                            </div>

                                                        </div>

                                                    </div>


                                                    <div
                                                            class="d-flex gap-3 align-items-center"
                                                    >

                                                        <div
                                                                class="rounded-3 bg-secondary-subtle"
                                                                style="width:36px;height:36px;"
                                                        ></div>

                                                        <div>

                                                            <div class="small fw-bold">
                                                                Behind the scenes
                                                            </div>

                                                            <div
                                                                    class="text-secondary"
                                                                    style="font-size:10px;"
                                                            >
                                                                TikTok · 7:00 PM
                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </main>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         TRUST
    ========================================================= --}}

    <section class="py-5 border-top border-bottom">

        <div class="container">

            <p
                    class="text-center text-uppercase small fw-bold text-secondary mb-4"
                    style="letter-spacing:.22em;"
            >
                Built for creators, teams & agencies
            </p>

        </div>


        <div class="marquee-wrapper">

            <div
                    class="marquee d-flex align-items-center gap-5 text-secondary fw-900 fs-5"
                    style="opacity:.3;"
            >

                <span>GROWTHIFY</span>
                <span>NEXUS</span>
                <span>SAASIFY</span>
                <span>OMNI</span>
                <span>CREATOR LAB</span>
                <span>MEDIAFLOW</span>

                <span>GROWTHIFY</span>
                <span>NEXUS</span>
                <span>SAASIFY</span>
                <span>OMNI</span>
                <span>CREATOR LAB</span>
                <span>MEDIAFLOW</span>

            </div>

        </div>

    </section>


    {{-- =========================================================
         PRODUCT
    ========================================================= --}}

    <section
            class="py-5 py-lg-6"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div class="container">

            <div style="max-width:760px;">

            <span
                    class="text-purple fw-900 small text-uppercase"
                    style="letter-spacing:.2em;"
            >
                One workspace. Less chaos.
            </span>

                <h2 class="display-5 fw-900 tracking-tight mt-3">
                    Everything your social team needs to move faster.
                </h2>

                <p class="lead text-secondary mt-3">
                    Replace scattered spreadsheets, schedulers, AI tools and
                    analytics tabs with one connected workflow.
                </p>

            </div>


            <div class="row g-4 mt-4">

                {{-- AI --}}

                <div class="col-lg-7">

                    <div class="feature-card bg-soft p-4 p-lg-5 h-100">

                        <div class="icon-box icon-purple">
                            <i class="bi bi-stars"></i>
                        </div>

                        <h3 class="h3 fw-900 mt-4">
                            AI content engine
                        </h3>

                        <p class="text-secondary mt-3">
                            Turn a brief into platform-specific hooks,
                            captions, content pillars and campaigns while
                            keeping your brand voice consistent.
                        </p>


                        <div class="bg-white rounded-4 p-4 shadow-soft mt-4">

                            <div class="d-flex align-items-center gap-3">

                                <div
                                        class="icon-box icon-purple"
                                        style="width:36px;height:36px;"
                                >
                                    <i class="bi bi-stars"></i>
                                </div>

                                <div>

                                    <div class="small fw-bold">
                                        Copilot
                                    </div>

                                    <div
                                            class="text-secondary"
                                            style="font-size:11px;"
                                    >
                                        Generating your campaign
                                    </div>

                                </div>

                            </div>


                            <div class="small fw-semibold mt-4">
                                “Create a 30-day launch campaign for our
                                new product…”
                            </div>


                            <div class="d-flex flex-wrap gap-2 mt-3">

                            <span class="badge bg-purple-subtle text-purple">
                                30 posts
                            </span>

                                <span class="badge bg-light text-secondary">
                                5 platforms
                            </span>

                                <span class="badge bg-light text-secondary">
                                Brand voice
                            </span>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- Planner --}}

                <div class="col-lg-5">

                    <div
                            class="feature-card bg-black text-white p-4 p-lg-5 h-100 position-relative overflow-hidden"
                    >

                        <div
                                class="position-absolute"
                                style="
                            right:-80px;
                            top:-80px;
                            width:220px;
                            height:220px;
                            background:rgba(124,58,237,.3);
                            filter:blur(50px);
                            border-radius:50%;
                        "
                        ></div>

                        <div class="position-relative">

                            <div class="icon-box bg-white bg-opacity-10">
                                <i class="bi bi-calendar3"></i>
                            </div>

                            <h3 class="h3 fw-900 mt-4">
                                Visual planner
                            </h3>

                            <p class="text-white-50 mt-3">
                                See your entire month at a glance,
                                drag posts around and publish when you're ready.
                            </p>


                            <div class="row row-cols-7 g-1 mt-4">

                                @for($i = 1; $i <= 35; $i++)

                                    <div class="col">

                                        <div
                                                class="rounded-2"
                                                style="
                                            aspect-ratio:1;
                                            background:
                                            {{ $i % 5 === 0
                                                ? '#a78bfa'
                                                : ($i % 3 === 0
                                                    ? 'rgba(255,255,255,.20)'
                                                    : 'rgba(255,255,255,.05)') }};
                                        "
                                        ></div>

                                    </div>

                                @endfor

                            </div>

                        </div>

                    </div>

                </div>


                {{-- Analytics --}}

                <div class="col-lg-4">

                    <div class="feature-card bg-white shadow-soft p-4 h-100">

                        <div class="fs-3 text-purple">
                            <i class="bi bi-bar-chart"></i>
                        </div>

                        <h3 class="h4 fw-900 mt-3">
                            Analytics that matter
                        </h3>

                        <p class="text-secondary small mt-3">
                            Understand what's working across every channel
                            and turn insights into better content.
                        </p>

                        <div class="display-6 fw-900 mt-4">
                            +38.6%
                        </div>

                        <div class="small text-success fw-bold">
                            engagement growth
                        </div>

                    </div>

                </div>


                {{-- Inbox --}}

                <div class="col-lg-4">

                    <div class="feature-card bg-purple text-white p-4 h-100">

                        <div class="fs-3">
                            <i class="bi bi-chat"></i>
                        </div>

                        <h3 class="h4 fw-900 mt-3">
                            Unified inbox
                        </h3>

                        <p class="small text-white-50 mt-3">
                            Bring comments, mentions and DMs into one
                            clean conversation stream.
                        </p>

                        <div class="d-flex mt-4">

                            <div
                                    class="rounded-circle bg-white text-purple d-flex align-items-center justify-content-center fw-900"
                                    style="width:36px;height:36px;"
                            >
                                IG
                            </div>

                            <div
                                    class="rounded-circle bg-dark border border-purple d-flex align-items-center justify-content-center small"
                                    style="width:36px;height:36px;margin-left:-8px;"
                            >
                                TK
                            </div>

                            <div
                                    class="rounded-circle bg-primary border border-purple d-flex align-items-center justify-content-center small"
                                    style="width:36px;height:36px;margin-left:-8px;"
                            >
                                LI
                            </div>

                        </div>

                    </div>

                </div>


                {{-- Approvals --}}

                <div class="col-lg-4">

                    <div class="feature-card bg-soft p-4 h-100">

                        <div class="fs-3">
                            <i class="bi bi-check2-circle"></i>
                        </div>

                        <h3 class="h4 fw-900 mt-3">
                            Approvals without chaos
                        </h3>

                        <p class="small text-secondary mt-3">
                            Give clients and teammates a simple review
                            flow before anything goes live.
                        </p>

                        <div
                                class="d-inline-flex bg-white border rounded-3 px-3 py-2 small fw-bold mt-4"
                        >
                            ✓ Approved by Sarah
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         AI COPILOT
    ========================================================= --}}

    <section
            class="ai-section py-5"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div class="container">

            <div class="row align-items-center g-5">

                <div class="col-lg-6">

                <span
                        class="badge bg-white bg-opacity-10 text-light rounded-pill px-3 py-2"
                >
                    AI COPILOT
                </span>

                    <h2 class="display-5 fw-900 tracking-tight mt-4">
                        Stop starting from a blank page.
                    </h2>

                    <p class="lead text-white-50 mt-3">
                        Give Socialeaz your goal, product or website.
                        Copilot turns it into an actionable social plan
                        in minutes.
                    </p>


                    <div class="d-flex flex-wrap gap-2 mt-4">

                        <button
                                class="ai-tab active"
                                data-tab="strategy"
                                type="button"
                        >
                            Strategy
                        </button>

                        <button
                                class="ai-tab"
                                data-tab="content"
                                type="button"
                        >
                            Content
                        </button>

                        <button
                                class="ai-tab"
                                data-tab="insights"
                                type="button"
                        >
                            Insights
                        </button>

                    </div>

                </div>


                <div class="col-lg-6">

                    <div class="ai-card float">

                        <div
                                class="d-flex align-items-center gap-3 pb-4 border-bottom"
                        >

                            <div class="icon-box icon-purple">
                                <i class="bi bi-stars"></i>
                            </div>

                            <div>

                                <div class="fw-900 small">
                                    Socialeaz Copilot
                                </div>

                                <div
                                        class="text-secondary"
                                        style="font-size:11px;"
                                >
                                    AI assistant
                                </div>

                            </div>

                            <span
                                    class="rounded-circle bg-success ms-auto"
                                    style="width:10px;height:10px;"
                            ></span>

                        </div>


                        <div class="py-5">

                            <div
                                    class="ai-content"
                                    data-content="strategy"
                            >

                                <div class="small text-purple fw-900">
                                    CAMPAIGN STRATEGY
                                </div>

                                <h3 class="h3 fw-900 mt-2">
                                    30-day product launch
                                </h3>

                                <p class="small text-secondary mt-2">
                                    5 content pillars · 30 posts · 4 channels
                                </p>

                            </div>


                            <div
                                    class="ai-content d-none"
                                    data-content="content"
                            >

                                <div class="small text-purple fw-900">
                                    CONTENT GENERATED
                                </div>

                                <h3 class="h3 fw-900 mt-2">
                                    12 platform-ready posts
                                </h3>

                                <p class="small text-secondary mt-2">
                                    Hooks, captions, CTAs and hashtags included.
                                </p>

                            </div>


                            <div
                                    class="ai-content d-none"
                                    data-content="insights"
                            >

                                <div class="small text-purple fw-900">
                                    AI INSIGHT
                                </div>

                                <h3 class="h3 fw-900 mt-2">
                                    Short-form video is winning
                                </h3>

                                <p class="small text-secondary mt-2">
                                    Your video posts generate 2.4× more
                                    engagement than static posts.
                                </p>

                            </div>

                        </div>


                        <div class="bg-soft rounded-4 p-3 small fw-medium">

                            <i class="bi bi-stars text-purple me-1"></i>

                            <span id="copilotMessage">
                            Planning your next best actions…
                        </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         CHANNELS
    ========================================================= --}}

    <section
            class="py-5"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div class="container">

            <div
                    class="text-center mx-auto"
                    style="max-width:650px;"
            >

            <span
                    class="text-purple small fw-900 text-uppercase"
                    style="letter-spacing:.2em;"
            >
                Multi-channel
            </span>

                <h2 class="display-6 fw-900 tracking-tight mt-3">
                    One calendar. Every channel.
                </h2>

                <p class="text-secondary mt-3">
                    Plan once, adapt your content and publish across
                    the platforms where your audience lives.
                </p>

            </div>


            <div class="row g-3 mt-5">

                @foreach([
                    [
                        'name' => 'Instagram',
                        'icon' => 'bi-instagram',
                        'class' => 'channel-instagram'
                    ],
                    [
                        'name' => 'TikTok',
                        'icon' => 'bi-tiktok',
                        'class' => 'channel-tiktok'
                    ],
                    [
                        'name' => 'LinkedIn',
                        'icon' => 'bi-linkedin',
                        'class' => 'channel-linkedin'
                    ],
                    [
                        'name' => 'Facebook',
                        'icon' => 'bi-facebook',
                        'class' => 'channel-facebook'
                    ],
                    [
                        'name' => 'YouTube',
                        'icon' => 'bi-youtube',
                        'class' => 'channel-youtube'
                    ],
                    [
                        'name' => 'X',
                        'icon' => 'bi-twitter-x',
                        'class' => 'channel-x'
                    ]
                ] as $channel)

                    <div class="col-6 col-md-4 col-lg-2">

                        <div class="channel-card h-100">

                            <div class="channel-icon-wrap {{ $channel['class'] }}">

                                <div class="channel-icon">

                                    <i class="bi {{ $channel['icon'] }}"></i>

                                </div>

                                <span class="channel-status"></span>

                            </div>

                            <div class="fw-bold small mt-3">
                                {{ $channel['name'] }}
                            </div>

                            <div class="small text-secondary mt-1">
                                Connected
                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </section>


    {{-- =========================================================
         TOOLS
    ========================================================= --}}

    <section
            class="bg-soft border-top border-bottom py-5"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div class="container">

            <div
                    class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3"
            >

                <div>

                <span
                        class="text-purple small fw-900 text-uppercase"
                        style="letter-spacing:.2em;"
                >
                    Free tools
                </span>

                    <h2 class="display-6 fw-900 tracking-tight mt-3">
                        Useful before you even sign up.
                    </h2>

                </div>

                <a
                        href="{{ route('tools') }}"
                        class="fw-bold small text-dark"
                >
                    Explore all tools →
                </a>

            </div>


            <div class="row g-4 mt-4">

                @foreach([
                    [
                        'badge' => 'POPULAR',
                        'title' => 'AI Caption Generator',
                        'text' => 'Create scroll-stopping captions with the right tone, length and CTA.',
                        'action' => 'Try free →'
                    ],
                    [
                        'badge' => 'FREE',
                        'title' => 'Hashtag Organizer',
                        'text' => 'Build reusable hashtag sets and keep your content workflow organized.',
                        'action' => 'Try free →'
                    ],
                    [
                        'badge' => 'TEMPLATE',
                        'title' => 'Content Calendar',
                        'text' => 'Start with a practical monthly content planning template.',
                        'action' => 'Download →'
                    ]
                ] as $tool)

                    <div class="col-md-4">

                        <div class="bg-white rounded-4 p-4 border h-100">

                        <span class="badge bg-light text-dark">
                            {{ $tool['badge'] }}
                        </span>

                            <h3 class="h5 fw-900 mt-4">
                                {{ $tool['title'] }}
                            </h3>

                            <p class="small text-secondary mt-2">
                                {{ $tool['text'] }}
                            </p>

                            <a
                                    href="{{ route('tools') }}"
                                    class="small fw-bold text-purple d-inline-block mt-3"
                            >
                                {{ $tool['action'] }}
                            </a>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </section>


    {{-- =========================================================
         TESTIMONIALS
    ========================================================= --}}

    <section
            class="py-5"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div class="container">

            <div
                    class="text-center mx-auto"
                    style="max-width:650px;"
            >

            <span
                    class="text-purple small fw-900 text-uppercase"
                    style="letter-spacing:.2em;"
            >
                Customer stories
            </span>

                <h2 class="display-6 fw-900 tracking-tight mt-3">
                    Less busywork. More momentum.
                </h2>

            </div>


            <div class="row g-4 mt-5">

                @foreach([
                    [
                        'text' => 'Socialeaz replaced our scattered tools with one workflow. Our team finally knows what is happening at every stage.',
                        'name' => 'Michael Wang',
                        'role' => 'CEO, Roger Agency'
                    ],
                    [
                        'text' => 'The approval flow changed everything for our agency. Clients can review content without endless messages.',
                        'name' => 'Sarah Lane',
                        'role' => 'Social Lead, Growthify'
                    ],
                    [
                        'text' => 'Copilot gives us a strong starting point every morning. We spend more time improving ideas and less time creating them.',
                        'name' => 'Ahmed Khan',
                        'role' => 'Founder, SaaSify KSA'
                    ]
                ] as $quote)

                    <div class="col-md-4">

                        <article class="testimonial-card h-100">

                            <div class="text-purple fs-4">
                                ★★★★★
                            </div>

                            <p class="text-secondary mt-4">
                                “{{ $quote['text'] }}”
                            </p>

                            <div class="border-top mt-4 pt-4">

                                <div class="small fw-900">
                                    {{ $quote['name'] }}
                                </div>

                                <div class="small text-secondary mt-1">
                                    {{ $quote['role'] }}
                                </div>

                            </div>

                        </article>

                    </div>

                @endforeach

            </div>

        </div>

    </section>


    {{-- =========================================================
         PRICING
    ========================================================= --}}

    <section
            class="bg-soft border-top border-bottom"
            style="padding-top:100px;padding-bottom:100px;"
    >

        <div class="container">

            <div
                    class="text-center mx-auto"
                    style="max-width:700px;"
            >

            <span
                    class="text-purple small fw-900 text-uppercase"
                    style="letter-spacing:.2em;"
            >
                Simple pricing
            </span>

                <h2 class="display-5 fw-900 tracking-tight mt-3">
                    Start small. Scale when ready.
                </h2>

                <p class="text-secondary mt-3">
                    Everything you need to build a serious social operation.
                </p>

            </div>


            <div class="row g-4 mt-5 align-items-stretch">

                {{-- Bootstrap --}}

                <div class="col-lg-4">

                    <div class="pricing-card d-flex flex-column">

                        <div class="small fw-900">
                            Bootstrap
                        </div>

                        <div class="small text-secondary mt-1">
                            For creators
                        </div>

                        <div class="display-4 fw-900 mt-4">
                            $29
                            <span class="fs-6 text-secondary">
                            /mo
                        </span>
                        </div>


                        <ul class="list-unstyled mt-4 flex-grow-1">

                            <li class="mb-3 small">
                                ✓ 5 social channels
                            </li>

                            <li class="mb-3 small">
                                ✓ 1 workspace
                            </li>

                            <li class="mb-3 small">
                                ✓ Unlimited AI generation
                            </li>

                            <li class="mb-3 small">
                                ✓ Content planner
                            </li>

                        </ul>


                        <a
                                href="{{ route('pricing') }}"
                                class="btn btn-light btn-socialeaz mt-3"
                        >
                            Start free
                        </a>

                    </div>

                </div>


                {{-- Accelerate --}}

                <div class="col-lg-4">

                    <div
                            class="pricing-card featured position-relative d-flex flex-column"
                    >

                    <span class="popular-badge">
                        MOST POPULAR
                    </span>

                        <div class="small fw-900">
                            Accelerate
                        </div>

                        <div class="small text-white-50 mt-1">
                            For growing teams
                        </div>

                        <div class="display-4 fw-900 mt-4">
                            $49
                            <span class="fs-6 text-white-50">
                            /mo
                        </span>
                        </div>


                        <ul class="list-unstyled mt-4 flex-grow-1">

                            <li class="mb-3 small text-white-50">
                                ✓ 10 social channels
                            </li>

                            <li class="mb-3 small text-white-50">
                                ✓ 50 content categories
                            </li>

                            <li class="mb-3 small text-white-50">
                                ✓ Advanced analytics
                            </li>

                            <li class="mb-3 small text-white-50">
                                ✓ Bulk editor & approvals
                            </li>

                        </ul>


                        <a
                                href="{{ route('pricing') }}"
                                class="btn btn-light btn-socialeaz mt-3"
                        >
                            Start free trial
                        </a>

                    </div>

                </div>


                {{-- Agency --}}

                <div class="col-lg-4">

                    <div class="pricing-card d-flex flex-column">

                        <div class="small fw-900">
                            Pro Agency
                        </div>

                        <div class="small text-secondary mt-1">
                            For agencies
                        </div>

                        <div class="display-4 fw-900 mt-4">
                            $99
                            <span class="fs-6 text-secondary">
                            /mo
                        </span>
                        </div>


                        <ul class="list-unstyled mt-4 flex-grow-1">

                            <li class="mb-3 small">
                                ✓ 25 social channels
                            </li>

                            <li class="mb-3 small">
                                ✓ 5 workspaces
                            </li>

                            <li class="mb-3 small">
                                ✓ Unlimited categories
                            </li>

                            <li class="mb-3 small">
                                ✓ Branded PDF reports
                            </li>

                        </ul>


                        <a
                                href="{{ route('pricing') }}"
                                class="btn btn-light btn-socialeaz mt-3"
                        >
                            Start free
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>


    {{-- =========================================================
         FAQ
    ========================================================= --}}

    <section
            class="py-5"
            style="padding-top:100px!important;padding-bottom:100px!important;"
    >

        <div
                class="container"
                style="max-width:850px;"
        >

            <div class="text-center">

            <span
                    class="text-purple small fw-900 text-uppercase"
                    style="letter-spacing:.2em;"
            >
                FAQ
            </span>

                <h2 class="display-6 fw-900 tracking-tight mt-3">
                    Questions, answered.
                </h2>

            </div>


            <div class="mt-5">

                @foreach([
                    [
                        'question' => 'What is Socialeaz?',
                        'answer' => 'Socialeaz is an all-in-one social media workspace for creating, planning, publishing, analyzing and managing conversations.'
                    ],
                    [
                        'question' => 'Is there a free trial?',
                        'answer' => 'Yes. Start with a 14-day trial and explore the platform without a credit card.'
                    ],
                    [
                        'question' => 'Which platforms are supported?',
                        'answer' => 'Socialeaz supports major networks including Instagram, TikTok, Facebook, LinkedIn, YouTube and X, with additional integrations available.'
                    ],
                    [
                        'question' => 'Can agencies manage multiple clients?',
                        'answer' => 'Yes. Dedicated workspaces, collaboration and approval workflows are designed for agencies and distributed teams.'
                    ]
                ] as $faq)

                    <div class="faq-item mb-3">

                        <button
                                type="button"
                                class="faq-question"
                        >

                        <span>
                            {{ $faq['question'] }}
                        </span>

                            <span class="faq-icon">
                            +
                        </span>

                        </button>


                        <div class="faq-answer">
                            {{ $faq['answer'] }}
                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </section>


    {{-- =========================================================
         FINAL CTA
    ========================================================= --}}

    <section class="pb-5">

        <div class="container">

            <div class="cta-section text-center">

                <div class="cta-glow"></div>

                <div class="position-relative">

                <span
                        class="text-purple small fw-900 text-uppercase"
                        style="letter-spacing:.2em;"
                >
                    Ready when you are
                </span>

                    <h2
                            class="display-5 fw-900 tracking-tight mt-3"
                    >
                        Make social your growth engine.
                    </h2>

                    <p
                            class="text-white-50 mx-auto mt-3"
                            style="max-width:600px;"
                    >
                        Bring your ideas, your team and your channels.
                        Socialeaz handles the busywork.
                    </p>

                    <a
                            href="{{ route('pricing') }}"
                            class="btn btn-light btn-socialeaz px-4 py-3 mt-3"
                    >
                        Start your free trial →
                    </a>

                </div>

            </div>

        </div>

    </section>

@endsection