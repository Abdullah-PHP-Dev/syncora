@extends('layouts.app')

@section('title', 'Connected Channels')

<style>
    .channels-hero {
        background: linear-gradient(135deg, #4338ca 0%, #6d28d9 45%, #9333ea 100%);
        border-radius: 20px;
        padding: 32px 36px;
        color: #fff;
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
    }

    .channels-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle at 85% 20%, rgba(255,255,255,.14) 0%, transparent 45%),
                           radial-gradient(circle at 15% 90%, rgba(255,255,255,.10) 0%, transparent 40%);
        pointer-events: none;
    }

    .channels-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .channels-hero h4 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .channels-hero p {
        color: rgba(255,255,255,.82);
        margin-bottom: 0;
        max-width: 520px;
    }

    .btn-hero {
        background: rgba(255,255,255,.16);
        border: 1px solid rgba(255,255,255,.35);
        color: #fff;
        backdrop-filter: blur(6px);
        transition: all .2s ease;
    }

    .btn-hero:hover {
        background: rgba(255,255,255,.28);
        color: #fff;
        transform: translateY(-1px);
    }

    .stat-strip {
        display: flex;
        gap: 16px;
        position: relative;
        z-index: 1;
        margin-top: 26px;
        flex-wrap: wrap;
    }

    .stat-pill {
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 14px;
        padding: 14px 20px;
        min-width: 150px;
        backdrop-filter: blur(6px);
    }

    .stat-pill .stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
        color: #fff;
    }

    .stat-pill .stat-label {
        font-size: .78rem;
        color: rgba(255,255,255,.75);
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .section-title {
        font-weight: 700;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .section-subtitle {
        color: #8a93a6;
        font-size: .85rem;
        margin-bottom: 20px;
    }

    .connected-list-card {
        border: 1px solid #eef1f5;
        border-radius: 16px;
        box-shadow: 0 2px 14px rgba(20, 20, 43, .04);
        margin-bottom: 32px;
    }

    .connected-channel-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 22px;
        border-bottom: 1px solid #f3f5f9;
        transition: background .15s ease;
    }

    .connected-channel-row:hover {
        background: #fafbfe;
    }

    .connected-channel-row:last-child {
        border-bottom: none;
    }

    .channel-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px #eef1f5;
    }

    .channel-platform-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 22px;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(0,0,0,.12);
    }

    .channel-platform-icon.facebook { background: linear-gradient(135deg,#1877F2,#0d5bc4); }
    .channel-platform-icon.instagram { background: linear-gradient(135deg,#f58529,#dd2a7b 45%,#8134af 75%,#515bd4); }
    .channel-platform-icon.whatsapp { background: linear-gradient(135deg,#25D366,#128c7e); }
    .channel-platform-icon.telegram { background: linear-gradient(135deg,#41c1ea,#229ED9); }
    .channel-platform-icon.x { background: linear-gradient(135deg,#2b2b2b,#000); }
    .channel-platform-icon.line { background: linear-gradient(135deg,#06d755,#00B900); }
    .channel-platform-icon.zalo { background: linear-gradient(135deg,#4ab3f4,#0068ff); }
    .channel-platform-icon.discord { background: linear-gradient(135deg,#7289da,#5865F2); }
    .channel-platform-icon.slack { background: linear-gradient(135deg,#36C5F0,#4A154B); }
    .channel-platform-icon.teams { background: linear-gradient(135deg,#5B5FC7,#4452a6); }
    .channel-platform-icon.google_chat { background: linear-gradient(135deg,#4285F4,#34A853); }
    .channel-platform-icon.matrix { background: linear-gradient(135deg,#0DBD8B,#0a8f68); }
    .channel-platform-icon.tiktok { background: linear-gradient(135deg,#000,#69C9D0 50%,#EE1D52); }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }

    .status-dot.active { background: #2fce6b; box-shadow: 0 0 0 3px rgba(47,206,107,.18); }
    .status-dot.inactive { background: #b5bacb; }

    .btn-disconnect {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #f1d4d4;
        color: #dc3545;
        background: #fff;
        transition: all .15s ease;
    }

    .btn-disconnect:hover {
        background: #dc3545;
        color: #fff;
        border-color: #dc3545;
    }

    .empty-channels {
        text-align: center;
        padding: 48px 24px;
        color: #8a93a6;
    }

    .empty-channels i {
        font-size: 48px;
        color: #d7dbe6;
        margin-bottom: 14px;
        display: block;
    }

    .platform-card {
        position: relative;
        border: 1px solid #eef1f5;
        border-radius: 18px;
        padding: 26px 22px;
        text-align: center;
        background: #fff;
        height: 100%;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .platform-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 30px rgba(20, 20, 43, .08);
        border-color: transparent;
    }

    .platform-connected-badge {
        position: absolute;
        top: 14px;
        right: 14px;
        font-size: .68rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        background: rgba(47,206,107,.12);
        color: #1e9e51;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .platform-card .channel-platform-icon {
        width: 56px;
        height: 56px;
        font-size: 26px;
        margin: 0 auto 16px;
    }

    .platform-card h6 {
        font-weight: 700;
        margin-bottom: 8px;
    }

    .platform-card p {
        font-size: .82rem;
        line-height: 1.5;
        min-height: 62px;
    }

    .platform-card .btn {
        border-radius: 10px;
        font-weight: 600;
        padding: 8px 20px;
    }

    .modal-content {
        border-radius: 16px;
        border: none;
        overflow: hidden;
    }

    .modal-header {
        border-bottom: 1px solid #f3f5f9;
        padding: 20px 24px;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        border-top: none;
        padding: 0 24px 24px;
    }

    .modal-icon-badge {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
        margin-right: 10px;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">

        <div class="channels-hero">
            <div class="channels-hero-content">
                <div>
                    <h4><i class="bx bx-plug-2"></i> Messaging Channels</h4>
                    <p>Connect your customer-facing accounts once, then reply to every conversation from a single unified inbox - no more switching between apps.</p>
                </div>
                <a href="{{ route('admin.chats.dashboard') }}" class="btn btn-hero">
                    <i class="bx bx-message-dots"></i> Go to Inbox
                </a>
            </div>

            @php
                $channelsByPlatform = $channels->groupBy('platform');
                $activeCount = $channels->where('status', true)->count();
            @endphp

            <div class="stat-strip">
                <div class="stat-pill">
                    <div class="stat-value">{{ $channels->count() }}</div>
                    <div class="stat-label">Connected Channels</div>
                </div>
                <div class="stat-pill">
                    <div class="stat-value">{{ $activeCount }}</div>
                    <div class="stat-label">Active Now</div>
                </div>
                <div class="stat-pill">
                    <div class="stat-value">{{ $channelsByPlatform->count() }}/12</div>
                    <div class="stat-label">Platforms In Use</div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
        @endif

        <div class="section-title"><i class="bx bx-list-check text-primary"></i> Your Connected Channels</div>
        <div class="section-subtitle">Every account currently wired into your inbox.</div>

        <div class="connected-list-card">
            @if ($channels->isNotEmpty())
                @foreach ($channels as $channel)
                    <div class="connected-channel-row">
                        @php
                            $noBrandGlyph = in_array($channel->platform, ['line', 'zalo', 'google_chat', 'matrix']);
                            $platformIconClass = $channel->platform === 'x' ? 'bxl-twitter' : ($noBrandGlyph ? 'bx-message-rounded-dots' : 'bxl-' . $channel->platform);
                        @endphp
                        @if ($channel->avatar_url)
                            {{-- Falls back to the platform icon (hidden by default) if the avatar URL fails to load --}}
                            <img src="{{ $channel->avatar_url }}" class="channel-avatar" alt="{{ $channel->name }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="channel-platform-icon {{ $channel->platform }}" style="display:none">
                                <i class="bx {{ $platformIconClass }}"></i>
                            </div>
                        @else
                            <div class="channel-platform-icon {{ $channel->platform }}">
                                <i class="bx {{ $platformIconClass }}"></i>
                            </div>
                        @endif
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $channel->name }}</div>
                            <small class="text-muted text-capitalize">{{ str_replace('_', ' ', $channel->platform) }} @if($channel->username) &middot; {{ $channel->username }} @endif</small>
                        </div>
                        <span class="d-none d-sm-inline-flex align-items-center text-muted small">
                            <span class="status-dot {{ $channel->status ? 'active' : 'inactive' }}"></span>
                            {{ $channel->status ? 'Active' : 'Inactive' }}
                        </span>
                        <form action="{{ route('admin.messaging.channels.destroy', ['channel' => $channel->id]) }}" method="POST" onsubmit="return confirm('Disconnect this channel? Existing conversations are kept, but it will stop sending/receiving.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-disconnect" title="Disconnect">
                                <i class="bx bx-unlink"></i>
                            </button>
                        </form>
                    </div>
                @endforeach
            @else
                <div class="empty-channels">
                    <i class="bx bx-plug-2"></i>
                    <div class="fw-semibold text-body">No channels connected yet</div>
                    <div>Pick a platform below to start receiving and replying to customer messages.</div>
                </div>
            @endif
        </div>

        <div class="section-title"><i class="bx bx-grid-alt text-primary"></i> Add a Platform</div>
        <div class="section-subtitle">Connect as many accounts as you need across any of these platforms.</div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('facebook'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('facebook')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon facebook mx-auto"><i class="bx bxl-facebook"></i></div>
                    <h6>Facebook Messenger</h6>
                    <p class="text-muted">Connects every Page you manage for Messenger conversations.</p>
                    <a href="{{ route('admin.social-accounts.redirect', ['platform' => 'facebook']) }}" class="btn btn-primary btn-sm">Connect Facebook</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('instagram'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('instagram')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon instagram mx-auto"><i class="bx bxl-instagram"></i></div>
                    <h6>Instagram Direct</h6>
                    <p class="text-muted">Connects your Instagram professional account directly - sign in with Instagram, no Facebook Page needed.</p>
                    <a href="{{ route('admin.messaging.auth.instagram.redirect') }}" class="btn btn-sm" style="background:linear-gradient(135deg,#f58529,#dd2a7b 45%,#8134af 75%,#515bd4);color:#fff;">Connect Instagram</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('x'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('x')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon x mx-auto"><i class="bx bxl-twitter"></i></div>
                    <h6>X (Twitter) DMs</h6>
                    <p class="text-muted">New messages are checked roughly every minute (X's real-time DM webhooks require an Enterprise tier).</p>
                    <a href="{{ route('admin.messaging.auth.x.redirect') }}" class="btn btn-dark btn-sm">Connect X Account</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('telegram'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('telegram')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon telegram mx-auto"><i class="bx bxl-telegram"></i></div>
                    <h6>Telegram Bot</h6>
                    <p class="text-muted">Create a bot with <a href="https://t.me/BotFather" target="_blank">@BotFather</a>, then paste its token below.</p>
                    <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#telegramModal">Connect Telegram Bot</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('whatsapp'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('whatsapp')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon whatsapp mx-auto"><i class="bx bxl-whatsapp"></i></div>
                    <h6>WhatsApp Business</h6>
                    <p class="text-muted">Paste the Phone Number ID and permanent access token from your Meta Business System User.</p>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#whatsappModal">Connect WhatsApp Number</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('line'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('line')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon line mx-auto"><i class="bx bx-message-rounded-dots"></i></div>
                    <h6>LINE</h6>
                    <p class="text-muted">Create a Messaging API channel in the <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a>, then paste its Channel Secret and Access Token.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#00B900" data-bs-toggle="modal" data-bs-target="#lineModal">Connect LINE Channel</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('zalo'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('zalo')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon zalo mx-auto"><i class="bx bx-message-rounded-dots"></i></div>
                    <h6>Zalo</h6>
                    <p class="text-muted">Vietnam's dominant messenger. Link an Official Account in the <a href="https://developers.zalo.me/" target="_blank">Zalo Developers Console</a>, then connect it below.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#0068ff" data-bs-toggle="modal" data-bs-target="#zaloModal">Connect Zalo OA</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('discord'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('discord')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon discord mx-auto"><i class="bx bxl-discord"></i></div>
                    <h6>Discord</h6>
                    <p class="text-muted">Create a bot in the <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>, paste its token below, then start the listener process to receive DMs.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#5865F2" data-bs-toggle="modal" data-bs-target="#discordModal">Connect Discord Bot</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('slack'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('slack')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon slack mx-auto"><i class="bx bxl-slack"></i></div>
                    <h6>Slack</h6>
                    <p class="text-muted">Let customers or teams DM your Slack app directly - one click installs it into their workspace.</p>
                    <a href="{{ route('admin.messaging.auth.slack.redirect') }}" class="btn btn-sm text-white" style="background:#4A154B">Connect with Slack</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('teams'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('teams')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon teams mx-auto"><i class="bx bxl-microsoft-teams"></i></div>
                    <h6>Microsoft Teams</h6>
                    <p class="text-muted">Register a bot as an <a href="https://portal.azure.com" target="_blank">Azure Bot</a> resource, paste its App ID and Password below, then set the Messaging endpoint you'll be shown.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#5B5FC7" data-bs-toggle="modal" data-bs-target="#teamsModal">Connect Teams Bot</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('google_chat') || $channelsByPlatform->has('google_chat_user'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('google_chat', collect())->count() + $channelsByPlatform->get('google_chat_user', collect())->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon google_chat mx-auto"><i class="bx bx-message-rounded-dots"></i></div>
                    <h6>Google Chat</h6>
                    <p class="text-muted">Build a Chat app on a <a href="https://console.cloud.google.com" target="_blank">Google Cloud</a> project, paste its service account key below, then set the App URL you'll be shown.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#4285F4" data-bs-toggle="modal" data-bs-target="#googleChatModal">Connect Google Chat</button>
                    <div class="mt-2">
                        <a href="{{ route('admin.messaging.auth.google_chat.redirect') }}" class="small">Or connect with your Google account</a>
                        <p class="text-muted mb-0" style="font-size:.75rem;">Post/read in spaces you already belong to - won't receive customer DMs (that needs the Chat app above).</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('matrix'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('matrix')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon matrix mx-auto"><i class="bx bx-message-rounded-dots"></i></div>
                    <h6>Matrix</h6>
                    <p class="text-muted">The open, federated chat protocol. Connect any account's homeserver URL and access token - matrix.org or self-hosted.</p>
                    <button type="button" class="btn btn-sm text-white" style="background:#0DBD8B" data-bs-toggle="modal" data-bs-target="#matrixModal">Connect Matrix Account</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="platform-card">
                    @if ($channelsByPlatform->has('tiktok'))
                        <span class="platform-connected-badge"><i class="bx bx-check"></i> {{ $channelsByPlatform->get('tiktok')->count() }} connected</span>
                    @endif
                    <div class="channel-platform-icon tiktok mx-auto"><i class="bx bxl-tiktok"></i></div>
                    <h6>TikTok Messenger</h6>
                    <p class="text-muted">Connects a TikTok Business Account for the Business Messaging API. Requires TikTok to have granted this app the Business Messaging permission - a manual approval step separate from Ads API access.</p>
                    <a href="{{ route('admin.messaging.auth.tiktok.redirect') }}" class="btn btn-sm text-white" style="background:#000">Connect TikTok</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Telegram Modal -->
    <div class="modal fade" id="telegramModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.telegram.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#229ED9"><i class="bx bxl-telegram"></i></span> Connect Telegram Bot</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bot Token *</label>
                            <input type="text" name="bot_token" class="form-control" placeholder="123456789:AA..." required>
                            @error('bot_token')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Connect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- WhatsApp Modal -->
    <x-whatsapp-connect-modal id="whatsappModal" />

    <!-- LINE Modal -->
    <div class="modal fade" id="lineModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.line.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#00B900"><i class="bx bx-message-rounded-dots"></i></span> Connect LINE Channel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">After connecting, you'll be shown a webhook URL - paste it into the same channel's Messaging API settings in the LINE Developers Console to start receiving messages.</p>
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Secret *</label>
                            <input type="text" name="channel_secret" class="form-control" required>
                            @error('channel_secret')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Access Token *</label>
                            <input type="text" name="access_token" class="form-control" required>
                            @error('access_token')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-sm text-white w-100" style="background:#00B900">Connect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zalo Modal -->
    <div class="modal fade" id="zaloModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.auth.zalo.redirect') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#0068ff"><i class="bx bx-message-rounded-dots"></i></span> Connect Zalo OA</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">This starts a Zalo login to link your Official Account. First, paste the <strong>OA Secret Key</strong> shown when you link this OA to your app in the Zalo Developers Console - it's needed to verify incoming messages and can't be fetched automatically.</p>
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">OA Secret Key *</label>
                            <input type="text" name="oa_secret_key" class="form-control" required>
                            @error('oa_secret_key')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-sm text-white w-100" style="background:#0068ff">Continue to Zalo Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Discord Modal -->
    <div class="modal fade" id="discordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.discord.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#5865F2"><i class="bx bxl-discord"></i></span> Connect Discord Bot</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Create an application in the <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>, add a Bot to it, enable the <strong>Message Content</strong> privileged intent, and paste its token below. Discord has no webhook delivery for bot DMs - after connecting, you'll need to run a small background process to actually receive messages.</p>
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bot Token *</label>
                            <input type="text" name="bot_token" class="form-control" required>
                            @error('bot_token')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer flex-column align-items-stretch gap-2">
                        <button type="submit" class="btn btn-sm text-white w-100" style="background:#5865F2">Connect</button>
                    </div>
                </form>
                <div class="px-3 pb-3">
                    <hr class="my-2">
                    <p class="text-muted small mb-2">Already connected the bot above? Add it to a Discord server through a proper consent screen instead of building an invite link by hand - a customer still needs to share <em>some</em> server with the bot before it can DM them.</p>
                    <a href="{{ route('admin.messaging.auth.discord.redirect') }}" class="btn btn-sm btn-outline-secondary w-100">Authorize Bot to a Server</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Teams Modal -->
    <div class="modal fade" id="teamsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.teams.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#5B5FC7"><i class="bx bxl-microsoft-teams"></i></span> Connect Teams Bot</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Register an <a href="https://portal.azure.com" target="_blank">Azure Bot</a> resource with a Teams channel enabled, then paste its Microsoft App ID and App Password (client secret) below. After connecting, you'll be shown a Messaging endpoint URL - paste it into that same Azure Bot resource's Configuration tab.</p>
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Microsoft App ID *</label>
                            <input type="text" name="app_id" class="form-control" required>
                            @error('app_id')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">App Password (Client Secret) *</label>
                            <input type="text" name="app_password" class="form-control" required>
                            @error('app_password')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-sm text-white w-100" style="background:#5B5FC7">Connect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Google Chat Modal -->
    <x-google-chat-connect-modal id="googleChatModal" />

    <!-- Matrix Modal -->
    <div class="modal fade" id="matrixModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.matrix.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#0DBD8B"><i class="bx bx-message-rounded-dots"></i></span> Connect Matrix Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Use a dedicated account on any homeserver (matrix.org or self-hosted) - log in once to get an access token, then paste it below. After connecting, you'll need to run a small background process to receive messages, since Matrix has no webhook delivery for a regular account. Note: rooms your client encrypts by default currently can't be read by this bot.</p>
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Homeserver URL *</label>
                            <input type="text" name="homeserver_url" class="form-control" placeholder="https://matrix.org" required>
                            @error('homeserver_url')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Access Token *</label>
                            <input type="text" name="access_token" class="form-control" required>
                            @error('access_token')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-sm text-white w-100" style="background:#0DBD8B">Connect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
