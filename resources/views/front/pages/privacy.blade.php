@extends('layouts.main')

@section('title', 'Privacy Policy — Socialeaz')

@section('meta_description', 'How Socialeaz collects, uses, and protects your data across every connected platform.')

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

            <h1 class="display-5 fw-900 tracking-tight mb-3">Privacy Policy</h1>

            <p class="text-muted mb-0">Last updated: August 1, 2026</p>

        </div>
    </section>

    <section class="py-5">
        <div class="container" style="max-width:820px;">

            <div class="legal-content">

                <p class="lead">
                    This Privacy Policy explains what information {{ config('app.name') }}
                    (the &ldquo;Service&rdquo;) collects, how we use it, and the choices you have. By
                    using the Service, you agree to the collection and use of information as
                    described here.
                </p>

                <h2><span class="legal-num">1</span>Information We Collect</h2>
                <p><strong>Account information</strong> &mdash; name, email address, and password (stored hashed) when you register.</p>
                <p><strong>Connected platform data</strong> &mdash; when you connect a third-party account (Facebook, Instagram, WhatsApp, TikTok, Google/YouTube, Snapchat, X, LinkedIn, Telegram, Discord, Slack, Microsoft Teams, Google Chat, Matrix, LINE, Zalo, etc), we receive and store what's needed to operate the Service on your behalf &mdash; access tokens, page/account identifiers, and the content of posts, comments, ads, and messages you send or receive through that connection.</p>
                <p><strong>Billing information</strong> &mdash; if you subscribe to a paid plan, payment details are collected and processed by our payment provider; we do not store full card numbers ourselves.</p>
                <p><strong>Usage data</strong> &mdash; technical information such as IP address, browser type, and pages visited, collected automatically to keep the Service secure and to improve it.</p>

                <h2><span class="legal-num">2</span>How We Use Information</h2>
                <ul>
                    <li>To provide the Service &mdash; publishing posts, sending and receiving messages/comments, and running ad campaigns through the platforms you connect;</li>
                    <li>To maintain your account, process subscription billing, and provide customer support;</li>
                    <li>To detect, prevent, and address fraud, abuse, and security issues;</li>
                    <li>To communicate with you about the Service, including important updates to these policies;</li>
                    <li>To improve and develop the Service.</li>
                </ul>
                <p>We do not sell your personal information.</p>

                <h2><span class="legal-num">3</span>Sharing of Information</h2>
                <p>We share information only in these situations:</p>
                <ul>
                    <li><strong>With the platforms you connect</strong> &mdash; to send the messages, posts, or ads you create, as directed by you;</li>
                    <li><strong>With service providers</strong> &mdash; such as our hosting, storage, and payment processors, bound by confidentiality obligations, solely to help operate the Service;</li>
                    <li><strong>For legal reasons</strong> &mdash; if required to comply with a legal obligation, or to protect the rights, safety, or property of {{ config('app.name') }}, our users, or the public;</li>
                    <li><strong>With your consent</strong> &mdash; for any other purpose disclosed to you at the time of collection.</li>
                </ul>

                <h2><span class="legal-num">4</span>Data Retention</h2>
                <p>
                    We retain your information for as long as your account is active or as
                    needed to provide the Service. If you close your account, we delete or
                    anonymize your data within a reasonable period, except where we must
                    retain it to comply with legal obligations, resolve disputes, or enforce
                    our agreements.
                </p>

                <h2><span class="legal-num">5</span>Security</h2>
                <p>
                    We use reasonable administrative, technical, and physical safeguards to
                    protect your information, including encrypting access tokens and
                    passwords at rest and in transit. No method of transmission or storage is
                    100% secure, and we cannot guarantee absolute security.
                </p>

                <h2><span class="legal-num">6</span>Cookies</h2>
                <p>
                    We use cookies and similar technologies to keep you signed in, remember
                    your preferences (such as language), and understand how the Service is
                    used. You can control cookies through your browser settings, though
                    disabling them may affect parts of the Service.
                </p>

                <h2><span class="legal-num">7</span>Your Rights</h2>
                <p>
                    Depending on where you live, you may have the right to access, correct,
                    export, or delete your personal information, and to withdraw consent for
                    a connected platform at any time by disconnecting it from your account
                    settings. To exercise these rights, contact us using the details below.
                </p>

                <h2><span class="legal-num">8</span>Children's Privacy</h2>
                <p>
                    The Service is not directed to children under 16, and we do not
                    knowingly collect personal information from them. If you believe a child
                    has provided us with personal information, please contact us so we can
                    delete it.
                </p>

                <h2><span class="legal-num">9</span>Changes to This Policy</h2>
                <p>
                    We may update this Privacy Policy from time to time. Material changes
                    will be communicated by posting an updated version on this page with a
                    new &ldquo;Last updated&rdquo; date.
                </p>

                <h2><span class="legal-num">10</span>Contact</h2>
                <p>
                    Questions about this Privacy Policy can be sent to
                    <a href="mailto:{{ config('mail.from.address', 'support@example.com') }}">{{ config('mail.from.address', 'support@example.com') }}</a>.
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
