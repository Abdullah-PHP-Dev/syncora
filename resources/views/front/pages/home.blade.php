@extends('layouts.front')

@section('content')

    <!-- ================= PREMIUM NAV FIX (optional visual spacer) ================= -->
    <div class="bg-white"></div>

    <!-- ================= HERO (STRIPE STYLE) ================= -->
    <section class="position-relative overflow-hidden"
             style="background: linear-gradient(120deg,#0d6efd,#6f42c1);">

        <div class="container py-6 py-lg-7 text-white">

            <div class="row align-items-center">

                <!-- LEFT -->
                <div class="col-lg-6">

                <span class="badge bg-light text-dark mb-3"
                      data-aos="fade-down">
                    🚀 New SaaS Marketplace Platform
                </span>

                    <h1 class="display-4 fw-bold"
                        id="hero-title"
                        data-aos="fade-right">
                        Grow your business with Social Bee
                    </h1>

                    <p class="lead text-white-75 mt-3"
                       data-aos="fade-right"
                       data-aos-delay="100">
                        Manage ads, CRM, services, and marketplace operations in one powerful SaaS system.
                    </p>

                    <div class="mt-4 d-flex gap-2 flex-wrap"
                         data-aos="fade-up"
                         data-aos-delay="200">

                        <a href="/register" class="btn btn-light btn-lg px-4">
                            Get Started Free
                        </a>

                        <a href="/pricing" class="btn btn-outline-light btn-lg px-4">
                            View Pricing
                        </a>

                    </div>

                </div>

                <!-- RIGHT -->
                <div class="col-lg-6 text-center mt-5 mt-lg-0">

                    <div class="position-relative text-center mt-5 mt-lg-0">

                        <!-- BACK CARD -->
                        <div class="position-absolute top-0 start-50 translate-middle-x bg-light rounded-4 shadow"
                             style="width: 90%; height: 280px; transform: rotate(-6deg); opacity: 0.5;">
                        </div>

                        <!-- FRONT CARD (MAIN MOCKUP) -->
                        <div class="position-relative bg-white rounded-4 shadow-lg p-4 mx-auto"
                             style="max-width: 420px;">

                            <div class="d-flex justify-content-between mb-3">
                                <span class="fw-bold text-dark">Dashboard</span>
                                <span class="badge bg-primary">Live</span>
                            </div>

                            <!-- STATS -->
                            <div class="row g-2">

                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted">Ads</small>
                                        <h5 class="mb-0">128</h5>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted">Leads</small>
                                        <h5 class="mb-0">54</h5>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted">Revenue</small>
                                        <h5 class="mb-0">$12K</h5>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="bg-light p-3 rounded">
                                        <small class="text-muted">Users</small>
                                        <h5 class="mb-0">1.2K</h5>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- FLOATING SHAPES (Stripe-like feel) -->
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>

    </section>
    <!-- ================= TRUST STRIP ================= -->
    <section class="py-4 bg-light border-bottom">

        <div class="container text-center">

            <small class="text-muted d-block mb-3">
                Trusted by modern startups and growing businesses
            </small>

            <div class="d-flex justify-content-center gap-5 flex-wrap fw-semibold text-secondary">

                <span>Stripe-like Agency</span>
                <span>Tech Startup</span>
                <span>Marketing Firm</span>
                <span>E-commerce Brand</span>
                <span>Enterprise SaaS</span>

            </div>

        </div>

    </section>


    <!-- ================= FEATURES (MODERN GRID) ================= -->
    <section class="py-6">

        <div class="container">

            <div class="text-center mb-5">
                <h2 class="fw-bold">Everything you need to run your marketplace</h2>
                <p class="text-muted">Built for scalability, speed, and modern business workflows</p>
            </div>

            <div class="row g-4">

                @foreach([
                    ['title' => 'Ads Engine', 'desc' => 'Create, manage, and boost marketplace listings.'],
                    ['title' => 'CRM System', 'desc' => 'Track leads, buyers, and conversations.'],
                    ['title' => 'Analytics', 'desc' => 'Understand growth with real-time insights.'],
                    ['title' => 'Wallet System', 'desc' => 'Secure payments & transaction control.'],
                    ['title' => 'Subscription Plans', 'desc' => 'Monetize your platform easily.'],
                    ['title' => 'Team Management', 'desc' => 'Control roles and permissions.'],
                ] as $feature)

                    <div class="col-md-4">

                        <div class="card border-0 shadow-sm h-100 p-4 hover-lift">

                            <h5 class="fw-bold">{{ $feature['title'] }}</h5>

                            <p class="text-muted mb-0">
                                {{ $feature['desc'] }}
                            </p>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </section>


    <!-- ================= PRODUCT STORY SECTION ================= -->
    <section class="py-6 bg-light border-top border-bottom">

        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-6">

                    <h2 class="fw-bold">
                        Built for modern digital marketplaces
                    </h2>

                    <p class="text-muted mt-3">
                        Syncora combines marketplace, CRM, analytics, and automation tools into one unified SaaS system.
                        Designed for scalability like Stripe and usability like SocialBee.
                    </p>

                    <ul class="text-muted">
                        <li>✔ Multi-user role system (Spatie)</li>
                        <li>✔ Scalable Laravel architecture</li>
                        <li>✔ Real-time marketplace management</li>
                        <li>✔ Modular CRM & Ads engine</li>
                    </ul>

                </div>

                <div class="col-lg-6">

                    <div class="bg-white p-4 rounded-4 shadow-sm border">
                        <div class="text-muted">Product Screenshot Placeholder</div>
                        <div class="bg-light p-5 rounded mt-3 border">
                            Dashboard UI Preview
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ================= PRICING (STRIPE STYLE) ================= -->
    <section class="py-6">

        <div class="container text-center">

            <h2 class="fw-bold">Simple, transparent pricing</h2>
            <p class="text-muted">Choose a plan that fits your business</p>

            <div class="row mt-5 g-4">

                <!-- FREE -->
                <div class="col-md-4">

                    <div class="card border-0 shadow-sm p-4 h-100">

                        <h5>Free</h5>
                        <h2 class="fw-bold">$0</h2>

                        <p class="text-muted">Basic marketplace access</p>

                        <button class="btn btn-outline-primary w-100">
                            Start Free
                        </button>

                    </div>

                </div>

                <!-- PRO -->
                <div class="col-md-4">

                    <div class="card border-primary shadow p-4 h-100">

                        <span class="badge bg-primary mb-2">Most Popular</span>

                        <h5>Pro</h5>
                        <h2 class="fw-bold">$19</h2>

                        <p class="text-muted">CRM + Ads Boost + Analytics</p>

                        <button class="btn btn-primary w-100">
                            Upgrade
                        </button>

                    </div>

                </div>

                <!-- ENTERPRISE -->
                <div class="col-md-4">

                    <div class="card border-0 shadow-sm p-4 h-100">

                        <h5>Enterprise</h5>
                        <h2 class="fw-bold">$49</h2>

                        <p class="text-muted">Full system access</p>

                        <button class="btn btn-dark w-100">
                            Contact Sales
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ================= FINAL CTA ================= -->
    <section class="py-6 text-white" style="background:#0d6efd;">

        <div class="container text-center">

            <h2 class="fw-bold">
                Start building your marketplace today
            </h2>

            <p class="text-white-75">
                Join Syncora and scale your business like never before
            </p>

            <a href="/register" class="btn btn-light btn-lg mt-3 px-4">
                Get Started Free
            </a>

        </div>

    </section>

@endsection