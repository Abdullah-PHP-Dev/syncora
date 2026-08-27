@extends('layouts.main')

@section('title', 'Terms & Conditions — Socialeaz')

@section('meta_description', 'The terms that govern your access to and use of Socialeaz.')

@section('content')

    <section
            class="py-5"
            style="background:var(--socialeaz-bg-soft);border-bottom:1px solid var(--socialeaz-border);"
    >
        <div class="container text-center py-4">

            <div
                    class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-white border shadow-sm small fw-bold mb-4"
            >
                <span class="rounded-circle bg-purple" style="width:8px;height:8px;"></span>
                LEGAL
            </div>

            <h1 class="display-5 fw-900 tracking-tight mb-3">Terms &amp; Conditions</h1>

            <p class="text-muted mb-0">Last updated: August 1, 2026</p>

        </div>
    </section>

    <section class="py-5">
        <div class="container" style="max-width:820px;">

            <div class="legal-content">

                <p class="lead">
                    These Terms &amp; Conditions (&ldquo;Terms&rdquo;) govern your access to and use of
                    {{ config('app.name') }} (the &ldquo;Service&rdquo;), operated by us (&ldquo;we&rdquo;, &ldquo;us&rdquo;, &ldquo;our&rdquo;).
                    By creating an account or otherwise using the Service, you agree to be
                    bound by these Terms. If you do not agree, please do not use the Service.
                </p>

                <h2><span class="legal-num">1</span>The Service</h2>
                <p>
                    {{ config('app.name') }} lets you manage social media advertising, posts,
                    comments, and direct-message conversations across connected third-party
                    platforms (including but not limited to Facebook, Instagram, WhatsApp,
                    TikTok, Google, YouTube, Snapchat, X, LinkedIn, Telegram, Discord, Slack,
                    Microsoft Teams, Google Chat, Matrix, LINE, and Zalo) from a single
                    dashboard.
                </p>

                <h2><span class="legal-num">2</span>Accounts</h2>
                <p>
                    You must provide accurate, current information when creating an account
                    and keep your login credentials confidential. You are responsible for all
                    activity that happens under your account. Notify us immediately if you
                    suspect unauthorized access.
                </p>

                <h2><span class="legal-num">3</span>Connected Third-Party Platforms</h2>
                <p>
                    When you connect a third-party account (for example, via Facebook Login,
                    Google OAuth, or a bot/API token you provide), you authorize us to access
                    and act on that account on your behalf strictly to provide the Service &mdash;
                    for example, publishing posts you create, sending replies you write, and
                    retrieving messages/comments so they appear in your inbox. You are
                    responsible for complying with each connected platform's own terms of
                    service and API policies. We are not responsible for actions taken by a
                    third-party platform against your account there (such as a suspension),
                    and we may lose the ability to provide part of the Service if a platform
                    changes or revokes API access.
                </p>

                <h2><span class="legal-num">4</span>Subscriptions &amp; Billing</h2>
                <p>
                    Certain features require a paid subscription. Subscription fees are
                    billed in advance on a recurring basis until cancelled. Except where
                    required by law, fees are non-refundable. We may change subscription
                    pricing with reasonable advance notice; continued use of the Service
                    after a price change takes effect constitutes acceptance of the new
                    price.
                </p>

                <h2><span class="legal-num">5</span>Acceptable Use</h2>
                <p>You agree not to use the Service to:</p>
                <ul>
                    <li>Send spam, unsolicited bulk messages, or content that violates a connected platform's own policies;</li>
                    <li>Upload or transmit content that is unlawful, defamatory, or infringes someone else's intellectual property or privacy rights;</li>
                    <li>Attempt to gain unauthorized access to the Service, other accounts, or connected platform accounts;</li>
                    <li>Reverse-engineer, resell, or use the Service to build a competing product without our written consent.</li>
                </ul>
                <p>
                    We may suspend or terminate accounts that violate this section, with or
                    without notice.
                </p>

                <h2><span class="legal-num">6</span>Your Content</h2>
                <p>
                    You retain ownership of the content you create, upload, or send through
                    the Service (posts, replies, media, etc). You grant us a limited license
                    to store, process, and transmit that content solely as necessary to
                    operate the Service &mdash; for example, publishing a scheduled post to the
                    platform you selected, or delivering a reply through the correct
                    messaging channel.
                </p>

                <h2><span class="legal-num">7</span>Availability &amp; Changes</h2>
                <p>
                    We aim to keep the Service available and reliable but do not guarantee
                    uninterrupted access &mdash; third-party platform outages, maintenance, or API
                    changes outside our control can affect availability. We may add, change,
                    or remove features at any time.
                </p>

                <h2><span class="legal-num">8</span>Termination</h2>
                <p>
                    You may stop using the Service and close your account at any time. We may
                    suspend or terminate your access if you violate these Terms, fail to pay
                    applicable fees, or if required by law. Upon termination, your right to
                    use the Service ends immediately; some data may be retained as described
                    in our <a href="{{ route('privacy') }}">Privacy Policy</a>.
                </p>

                <h2><span class="legal-num">9</span>Disclaimer &amp; Limitation of Liability</h2>
                <p>
                    The Service is provided &ldquo;as is&rdquo; and &ldquo;as available&rdquo; without warranties of
                    any kind, express or implied. To the maximum extent permitted by law, we
                    are not liable for indirect, incidental, or consequential damages, or for
                    any loss of data, revenue, or business arising from your use of the
                    Service or from a connected third-party platform's actions.
                </p>

                <h2><span class="legal-num">10</span>Changes to These Terms</h2>
                <p>
                    We may update these Terms from time to time. Material changes will be
                    communicated by posting an updated version on this page with a new
                    &ldquo;Last updated&rdquo; date. Continued use of the Service after changes take
                    effect constitutes acceptance of the revised Terms.
                </p>

                <h2><span class="legal-num">11</span>Contact</h2>
                <p>
                    Questions about these Terms can be sent to
                    <a href="mailto:{{ config('mail.from.address', 'support@socialeaz.com') }}">{{ config('mail.from.address', 'support@socialeaz.com') }}</a>.
                </p>

            </div>

        </div>
    </section>

@endsection

@push('styles')
    <style>
        .legal-content h2 {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-top: 3rem;
            margin-bottom: 1rem;

            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: -0.01em;
            color: var(--socialeaz-text);
        }

        .legal-content .legal-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;

            width: 28px;
            height: 28px;

            border-radius: 8px;

            background: var(--socialeaz-purple-soft);
            color: var(--socialeaz-purple);

            font-size: 13px;
            font-weight: 900;
        }

        .legal-content p,
        .legal-content li {
            color: var(--socialeaz-muted);
            line-height: 1.75;
        }

        .legal-content p.lead {
            font-size: 1.05rem;
            color: var(--socialeaz-text);
        }

        .legal-content strong {
            color: var(--socialeaz-text);
        }

        .legal-content ul {
            padding-left: 1.25rem;
        }

        .legal-content li {
            margin-bottom: 0.5rem;
        }

        .legal-content a {
            color: var(--socialeaz-purple);
            font-weight: 600;
            text-decoration: none;
        }

        .legal-content a:hover {
            text-decoration: underline;
        }
    </style>
@endpush
