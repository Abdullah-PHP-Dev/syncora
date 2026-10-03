@extends('layouts.app')

@section('title', 'Connected Channels')

@php
    $channelsByPlatform = $channels->groupBy('platform');
    $activeCount = $channels->where('status', true)->count();
    $inactiveCount = $channels->count() - $activeCount;

    // Every platform the inbox supports. `href` = one-click sign-in,
    // `modal` = credentials form below. `count` keys are the channel
    // platforms that belong to the card.
    $platforms = [
        ['key' => 'facebook', 'name' => 'Facebook Messenger', 'icon' => 'bxl-facebook', 'color' => '#1877F2', 'group' => 'social', 'setup' => 'oauth',
         'desc' => 'Connect every Page you manage and reply to Messenger conversations.',
         'href' => route('admin.social-accounts.redirect', ['platform' => 'facebook'])],
        ['key' => 'instagram', 'name' => 'Instagram Direct', 'icon' => 'bxl-instagram', 'color' => '#dd2a7b', 'group' => 'social', 'setup' => 'oauth',
         'desc' => 'Sign in with your Instagram professional account - no Facebook Page needed.',
         'href' => route('admin.messaging.auth.instagram.redirect')],
        ['key' => 'x', 'name' => 'X Direct Messages', 'icon' => 'bxl-x-logo', 'color' => '#0f1419', 'group' => 'social', 'setup' => 'oauth',
         'desc' => 'Real-time DMs through X webhooks, including end-to-end encrypted X Chat.',
         'href' => route('admin.messaging.auth.x.redirect')],
        ['key' => 'tiktok', 'name' => 'TikTok Messenger', 'icon' => 'bxl-tiktok', 'color' => '#111111', 'group' => 'social', 'setup' => 'oauth',
         'desc' => 'TikTok Business Messaging for Business Accounts. Requires TikTok\'s Business Messaging approval.',
         'href' => route('admin.messaging.auth.tiktok.redirect')],
        ['key' => 'whatsapp', 'name' => 'WhatsApp Business', 'icon' => 'bxl-whatsapp', 'color' => '#25D366', 'group' => 'messaging', 'setup' => 'token',
         'desc' => 'Use the Phone Number ID and permanent token from your Meta Business System User.',
         'modal' => 'whatsappModal'],
        ['key' => 'telegram', 'name' => 'Telegram Bot', 'icon' => 'bxl-telegram', 'color' => '#229ED9', 'group' => 'messaging', 'setup' => 'token',
         'desc' => 'Create a bot with <a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> and paste its token.',
         'modal' => 'telegramModal'],
        ['key' => 'line', 'name' => 'LINE', 'icon' => 'bx-message-rounded-dots', 'color' => '#06C755', 'group' => 'messaging', 'setup' => 'token',
         'desc' => 'Messaging API channel from the <a href="https://developers.line.biz/console/" target="_blank" rel="noopener">LINE Developers Console</a>.',
         'modal' => 'lineModal'],
        ['key' => 'zalo', 'name' => 'Zalo', 'icon' => 'bx-message-rounded-dots', 'color' => '#0068ff', 'group' => 'messaging', 'setup' => 'oauth',
         'desc' => 'Link a Zalo Official Account from the <a href="https://developers.zalo.me/" target="_blank" rel="noopener">Zalo Developers Console</a>.',
         'modal' => 'zaloModal'],
        ['key' => 'slack', 'name' => 'Slack', 'icon' => 'bxl-slack', 'color' => '#4A154B', 'group' => 'team', 'setup' => 'oauth',
         'desc' => 'Customers or teams DM your Slack app - one click installs it into the workspace.',
         'href' => route('admin.messaging.auth.slack.redirect')],
        ['key' => 'teams', 'name' => 'Microsoft Teams', 'icon' => 'bxl-microsoft-teams', 'color' => '#5B5FC7', 'group' => 'team', 'setup' => 'token',
         'desc' => 'Register an <a href="https://portal.azure.com" target="_blank" rel="noopener">Azure Bot</a>, then paste its App ID and password.',
         'modal' => 'teamsModal'],
        ['key' => 'google_chat', 'name' => 'Google Chat', 'icon' => 'bx-message-rounded-dots', 'color' => '#1a73e8', 'group' => 'team', 'setup' => 'token',
         'desc' => 'A Chat app on your <a href="https://console.cloud.google.com" target="_blank" rel="noopener">Google Cloud</a> project, with its service account key.',
         'modal' => 'googleChatModal', 'count' => ['google_chat', 'google_chat_user'],
         'extra' => ['href' => route('admin.messaging.auth.google_chat.redirect'), 'label' => 'Or sign in with Google', 'hint' => 'Spaces you belong to only - no customer DMs.']],
        ['key' => 'discord', 'name' => 'Discord', 'icon' => 'bxl-discord', 'color' => '#5865F2', 'group' => 'team', 'setup' => 'token',
         'desc' => 'A bot from the <a href="https://discord.com/developers/applications" target="_blank" rel="noopener">Developer Portal</a>; runs a listener process for DMs.',
         'modal' => 'discordModal'],
        ['key' => 'matrix', 'name' => 'Matrix', 'icon' => 'bx-message-rounded-dots', 'color' => '#0DBD8B', 'group' => 'team', 'setup' => 'token',
         'desc' => 'Any homeserver - matrix.org or self-hosted - with an account access token.',
         'modal' => 'matrixModal'],
    ];

    $groups = ['social' => 'Social', 'messaging' => 'Messaging apps', 'team' => 'Team & community'];
    $platformMeta = collect($platforms)->keyBy('key');
    $platformMeta->put('google_chat_user', $platformMeta['google_chat']);

    foreach ($platforms as &$platform) {
        $platform['connected'] = collect($platform['count'] ?? [$platform['key']])->sum(fn ($key) => $channelsByPlatform->get($key, collect())->count());
    }
    unset($platform);

    $xChatByAccount = \App\Models\Messaging\XChatCredential::whereIn('social_account_id', $channels->where('platform', 'x')->pluck('social_account_id'))
        ->get()->keyBy('social_account_id');
@endphp

@push('styles')
<style>
    .chn { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; --ok: #079455; --ok-soft: #ecfdf3; --warn: #b54708; --warn-soft: #fffaeb; --danger: #d92d20; --radius: 16px; color: var(--ink2); }

    /* ---------- Header ---------- */
    .chn-head { position: relative; overflow: hidden; background: #fff; border: 1px solid var(--ln); border-radius: 20px; padding: 28px 28px 0; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .chn-head::before { content: ''; position: absolute; inset: 0 0 auto auto; width: 520px; height: 260px; background: radial-gradient(closest-side, rgba(109, 74, 255, .12), transparent), radial-gradient(closest-side at 80% 30%, rgba(143, 107, 255, .10), transparent); pointer-events: none; }
    .chn-head-top { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
    .chn-head-title { display: flex; gap: 16px; align-items: flex-start; }
    .chn-head-icon { width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; font-size: 26px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 20px rgba(109, 74, 255, .28); flex-shrink: 0; }
    .chn-eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 4px; }
    .chn-head h4 { color: var(--ink); font-weight: 700; font-size: 1.45rem; margin: 0 0 6px; letter-spacing: -.01em; }
    .chn-head p { margin: 0; max-width: 560px; font-size: .9rem; line-height: 1.55; }
    .chn-head-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .chn-stats { position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 24px -28px 0; border-top: 1px solid var(--ln-soft); }
    .chn-stat { padding: 18px 28px; border-right: 1px solid var(--ln-soft); }
    .chn-stat:last-child { border-right: none; }
    .chn-stat-label { display: flex; align-items: center; gap: 6px; font-size: .75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .chn-stat-value { font-size: 1.6rem; font-weight: 700; color: var(--ink); line-height: 1.1; }
    .chn-stat-value small { font-size: .9rem; font-weight: 600; color: var(--muted); }

    /* ---------- Buttons ---------- */
    .chn-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 40px; padding: 0 1rem; border-radius: 10px; font-size: .85rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; transition: background .15s, border-color .15s, color .15s, box-shadow .15s, transform .15s; }
    .chn-btn i { font-size: 1.05rem; }
    .chn-btn-sm { height: 34px; padding: 0 .8rem; font-size: .8rem; border-radius: 9px; }
    .chn-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
    .chn-btn-brand:hover { color: #fff; box-shadow: 0 6px 16px rgba(109, 74, 255, .35); transform: translateY(-1px); }
    .chn-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink); }
    .chn-btn-outline:hover { border-color: #cfd4de; background: #fafbfd; color: var(--ink); }
    .chn-btn-ok { background: var(--ok-soft); border-color: #abefc6; color: var(--ok); }
    .chn-btn-ok:hover { background: #dcfae6; color: var(--ok); }
    .chn-btn-warn { background: var(--warn-soft); border-color: #fedf89; color: var(--warn); }
    .chn-btn-warn:hover { background: #fef0c7; color: var(--warn); }
    .chn-icon-btn { width: 34px; height: 34px; border-radius: 9px; display: inline-grid; place-items: center; border: 1px solid var(--ln); background: #fff; color: var(--muted); font-size: 1.05rem; transition: all .15s; }
    .chn-icon-btn:hover { border-color: #fda29b; background: #fef3f2; color: var(--danger); }

    /* ---------- Section headers ---------- */
    .chn-section { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
    .chn-section h5 { color: var(--ink); font-weight: 700; font-size: 1.05rem; margin: 0 0 2px; }
    .chn-section p { margin: 0; font-size: .84rem; color: var(--muted); }
    .chn-search { position: relative; }
    .chn-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.05rem; pointer-events: none; }
    .chn-search input { height: 38px; width: 240px; max-width: 100%; border: 1px solid var(--ln); border-radius: 10px; padding: 0 12px 0 36px; font-size: .85rem; color: var(--ink); background: #fff; outline: none; transition: border-color .15s, box-shadow .15s; }
    .chn-search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }

    /* ---------- Connected accounts ---------- */
    .chn-card { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); box-shadow: 0 1px 2px rgba(16, 24, 40, .04); margin-bottom: 32px; overflow: hidden; }
    .chn-row { display: flex; align-items: center; gap: 14px; padding: 14px 20px; border-bottom: 1px solid var(--ln-soft); transition: background .15s; }
    .chn-row:last-child { border-bottom: none; }
    .chn-row:hover { background: #fafbfd; }
    .chn-avatar { position: relative; width: 44px; height: 44px; flex-shrink: 0; }
    .chn-avatar img, .chn-avatar .chn-logo { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
    .chn-avatar img { box-shadow: 0 0 0 1px var(--ln); }
    .chn-avatar .chn-badge { position: absolute; right: -3px; bottom: -3px; width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; color: #fff; background: var(--pc); border: 2px solid #fff; }
    .chn-logo { display: grid; place-items: center; color: #fff; font-size: 22px; background: var(--pc); }
    .chn-row-main { flex: 1; min-width: 0; }
    .chn-row-name { color: var(--ink); font-weight: 600; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chn-row-sub { display: flex; align-items: center; gap: 8px; font-size: .78rem; color: var(--muted); flex-wrap: wrap; }
    .chn-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: var(--ln-soft); color: var(--ink2); font-weight: 600; font-size: .7rem; }
    .chn-status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: .74rem; font-weight: 600; }
    .chn-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .chn-status.is-on { background: var(--ok-soft); color: var(--ok); }
    .chn-status.is-off { background: var(--ln-soft); color: var(--muted); }
    .chn-row-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .chn-row-actions form { margin: 0; }
    .chn-empty { text-align: center; padding: 48px 24px; }
    .chn-empty-icon { width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 18px; display: grid; place-items: center; font-size: 30px; color: var(--brand); background: var(--brand-soft); }
    .chn-empty h6 { color: var(--ink); font-weight: 700; margin-bottom: 4px; }
    .chn-no-match { display: none; padding: 28px; text-align: center; font-size: .85rem; color: var(--muted); }

    /* ---------- Platform catalog ---------- */
    .chn-tabs { display: inline-flex; gap: 4px; padding: 4px; background: var(--ln-soft); border-radius: 12px; flex-wrap: wrap; }
    .chn-tab { border: none; background: transparent; height: 32px; padding: 0 14px; border-radius: 9px; font-size: .8rem; font-weight: 600; color: var(--ink2); transition: all .15s; }
    .chn-tab:hover { color: var(--ink); }
    .chn-tab.is-active { background: #fff; color: var(--ink); box-shadow: 0 1px 3px rgba(16, 24, 40, .1); }
    .chn-tab span { color: var(--muted); font-weight: 500; margin-left: 2px; }

    .chn-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; margin-bottom: 32px; }
    .chn-platform { position: relative; display: flex; flex-direction: column; background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); padding: 20px; transition: border-color .2s, box-shadow .2s, transform .2s; }
    .chn-platform::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; border-radius: var(--radius) var(--radius) 0 0; background: var(--pc); opacity: 0; transition: opacity .2s; }
    .chn-platform:hover { border-color: transparent; box-shadow: 0 12px 28px rgba(16, 24, 40, .09); transform: translateY(-2px); }
    .chn-platform:hover::before { opacity: 1; }
    .chn-platform-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .chn-platform .chn-logo { width: 46px; height: 46px; border-radius: 13px; font-size: 24px; flex-shrink: 0; box-shadow: 0 6px 14px color-mix(in srgb, var(--pc) 30%, transparent); }
    .chn-platform h6 { color: var(--ink); font-weight: 700; font-size: .95rem; margin: 0 0 3px; }
    .chn-setup { display: inline-flex; align-items: center; gap: 4px; font-size: .7rem; font-weight: 600; color: var(--muted); }
    .chn-setup i { font-size: .85rem; }
    .chn-platform p { font-size: .82rem; line-height: 1.55; margin: 0 0 16px; flex: 1; }
    .chn-platform p a { color: var(--brand); text-decoration: none; font-weight: 500; }
    .chn-platform p a:hover { text-decoration: underline; }
    .chn-platform-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .chn-connected { display: inline-flex; align-items: center; gap: 4px; font-size: .74rem; font-weight: 600; color: var(--ok); }
    .chn-connect { background: #fff; border: 1px solid var(--ln); color: var(--ink); }
    .chn-platform:hover .chn-connect { background: var(--pc); border-color: var(--pc); color: #fff; }
    .chn-extra { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--ln); font-size: .76rem; }
    .chn-extra a { font-weight: 600; color: var(--brand); text-decoration: none; }
    .chn-extra span { display: block; color: var(--muted); margin-top: 2px; }

    .chn-alert { display: flex; align-items: center; gap: 10px; border-radius: 12px; padding: 12px 16px; font-size: .86rem; font-weight: 500; margin-bottom: 20px; border: 1px solid; }
    .chn-alert i { font-size: 1.2rem; }
    .chn-alert-ok { background: var(--ok-soft); border-color: #abefc6; color: var(--ok); }
    .chn-alert-err { background: #fef3f2; border-color: #fecdca; color: var(--danger); }

    /* ---------- Modals (this page) ---------- */
    .chn-page .modal-content { border-radius: 18px; border: none; overflow: hidden; box-shadow: 0 24px 48px rgba(16, 24, 40, .18); }
    .chn-page .modal-header { border-bottom: 1px solid #f1f3f7; padding: 20px 24px; }
    .chn-page .modal-title { font-weight: 700; color: #161b2b; font-size: 1.05rem; }
    .chn-page .modal-body { padding: 22px 24px; }
    .chn-page .modal-body .form-label { font-weight: 600; font-size: .82rem; color: #344054; }
    .chn-page .modal-body .form-control { border-radius: 10px; border-color: #e7e9f0; min-height: 42px; font-size: .88rem; }
    .chn-page .modal-body .form-control:focus { border-color: #6d4aff; box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
    .chn-page .modal-footer { border-top: none; padding: 0 24px 24px; }
    .chn-page .modal-footer .btn { height: 44px; border-radius: 11px; font-weight: 600; }
    .chn-page .modal-icon-badge { width: 40px; height: 40px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 19px; margin-right: 12px; }

    @media (max-width: 991.98px) {
        .chn-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .chn-stat:nth-child(2) { border-right: none; }
        .chn-stat:nth-child(-n+2) { border-bottom: 1px solid var(--ln-soft); }
    }
    @media (max-width: 575.98px) {
        .chn-head { padding: 20px 16px 0; }
        .chn-stats { margin: 20px -16px 0; }
        .chn-stat { padding: 14px 16px; }
        .chn-row { flex-wrap: wrap; padding: 14px 16px; }
        .chn-row-actions { width: 100%; justify-content: flex-end; }
        .chn-search, .chn-search input { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="col-xxl-12 mb-0 chn chn-page">

    {{-- Header --}}
    <div class="chn-head">
        <div class="chn-head-top">
            <div class="chn-head-title">
                <div class="chn-head-icon"><i class="bx bx-plug"></i></div>
                <div>
                    <div class="chn-eyebrow">Unified Inbox</div>
                    <h4>Messaging Channels</h4>
                    <p>Connect your customer-facing accounts once, then answer every conversation from one inbox - with AI Copilot on every channel.</p>
                </div>
            </div>
            <div class="chn-head-actions">
                <a href="#chn-catalog" class="chn-btn chn-btn-outline"><i class="bx bx-plus"></i> Add channel</a>
                <a href="{{ route('admin.chats.dashboard') }}" class="chn-btn chn-btn-brand"><i class="bx bx-message-square-dots"></i> Open Inbox</a>
            </div>
        </div>

        <div class="chn-stats">
            <div class="chn-stat">
                <div class="chn-stat-label"><i class="bx bx-link-alt"></i> Connected</div>
                <div class="chn-stat-value">{{ $channels->count() }}</div>
            </div>
            <div class="chn-stat">
                <div class="chn-stat-label"><i class="bx bx-pulse"></i> Active</div>
                <div class="chn-stat-value">{{ $activeCount }}</div>
            </div>
            <div class="chn-stat">
                <div class="chn-stat-label"><i class="bx bx-error-circle"></i> Need attention</div>
                <div class="chn-stat-value" @if($inactiveCount) style="color: var(--warn)" @endif>{{ $inactiveCount }}</div>
            </div>
            <div class="chn-stat">
                <div class="chn-stat-label"><i class="bx bx-grid-alt"></i> Platforms in use</div>
                <div class="chn-stat-value">{{ collect($platforms)->where('connected', '>', 0)->count() }}<small> / {{ count($platforms) }}</small></div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="chn-alert chn-alert-ok"><i class="bx bx-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="chn-alert chn-alert-err"><i class="bx bx-error-circle"></i> {{ session('error') }}</div>
    @endif

    {{-- Connected accounts --}}
    <div class="chn-section">
        <div>
            <h5>Connected accounts</h5>
            <p>Every account currently wired into your inbox.</p>
        </div>
        @if ($channels->count() > 4)
            <label class="chn-search mb-0">
                <i class="bx bx-search"></i>
                <input type="search" placeholder="Search accounts…" data-chn-filter="#chn-connected-list" aria-label="Search connected accounts">
            </label>
        @endif
    </div>

    <div class="chn-card" id="chn-connected-list">
        @forelse ($channels as $channel)
            @php
                $meta = $platformMeta->get($channel->platform, ['name' => \Illuminate\Support\Str::headline($channel->platform), 'icon' => 'bx-message-rounded-dots', 'color' => '#6d4aff']);
                $xChat = $channel->platform === 'x' ? $xChatByAccount->get($channel->social_account_id) : null;
                $displayName = $channel->name ?: ($channel->username ?: $meta['name'] . ' account');
            @endphp
            <div class="chn-row" style="--pc: {{ $meta['color'] }}" data-search="{{ strtolower($channel->name . ' ' . $channel->username . ' ' . $meta['name']) }}">
                <div class="chn-avatar">
                    @if ($channel->avatar_url)
                        {{-- Falls back to the platform logo if the avatar fails to load --}}
                        <img src="{{ $channel->avatar_url }}" alt="{{ $displayName }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid'; this.nextElementSibling.nextElementSibling.remove();">
                        <div class="chn-logo" style="display:none"><i class="bx {{ $meta['icon'] }}"></i></div>
                        <span class="chn-badge"><i class="bx {{ $meta['icon'] }}"></i></span>
                    @else
                        <div class="chn-logo"><i class="bx {{ $meta['icon'] }}"></i></div>
                    @endif
                </div>

                <div class="chn-row-main">
                    <div class="chn-row-name">{{ $displayName }}</div>
                    <div class="chn-row-sub">
                        <span class="chn-chip">{{ $meta['name'] }}</span>
                        @if ($channel->username)<span>{{ $channel->username }}</span>@endif
                    </div>
                </div>

                <span class="chn-status {{ $channel->status ? 'is-on' : 'is-off' }} d-none d-sm-inline-flex">{{ $channel->status ? 'Active' : 'Inactive' }}</span>

                <div class="chn-row-actions">
                    @if ($channel->platform === 'x')
                        @if ($xChat && $xChat->status === 'active')
                            <form action="{{ route('admin.messaging.channels.x-chat.disable', ['channel' => $channel->id]) }}" method="POST" onsubmit="return confirm('Disable encrypted X Chat? The stored PIN is deleted and new X messages will no longer be decrypted.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="chn-btn chn-btn-sm chn-btn-ok" title="Encrypted X Chat is on - click to disable"><i class="bx bx-lock-alt"></i> X Chat on</button>
                            </form>
                        @else
                            <button type="button" class="chn-btn chn-btn-sm {{ $xChat ? 'chn-btn-warn' : 'chn-btn-outline' }}" data-bs-toggle="modal" data-bs-target="#xChatModal"
                                    data-action="{{ route('admin.messaging.channels.x-chat.enable', ['channel' => $channel->id]) }}" data-name="{{ $displayName }}"
                                    title="{{ $xChat?->last_error ?? 'Read encrypted X messages in your inbox' }}">
                                <i class="bx {{ $xChat ? 'bx-error' : 'bx-lock-open-alt' }}"></i> {{ $xChat ? 'Re-enter PIN' : 'Enable X Chat' }}
                            </button>
                        @endif
                    @endif
                    <form action="{{ route('admin.messaging.channels.destroy', ['channel' => $channel->id]) }}" method="POST" onsubmit="return confirm('Disconnect this channel? Existing conversations are kept, but it will stop sending/receiving.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="chn-icon-btn" title="Disconnect {{ $displayName }}" aria-label="Disconnect {{ $displayName }}"><i class="bx bx-unlink"></i></button>
                    </form>
                </div>
            </div>
        @empty
            <div class="chn-empty">
                <div class="chn-empty-icon"><i class="bx bx-plug"></i></div>
                <h6>No channels connected yet</h6>
                <p class="mb-3 small">Pick a platform below to start receiving and replying to customer messages.</p>
                <a href="#chn-catalog" class="chn-btn chn-btn-brand"><i class="bx bx-plus"></i> Connect your first channel</a>
            </div>
        @endforelse
        <div class="chn-no-match">No accounts match your search.</div>
    </div>

    {{-- Platform catalog --}}
    <div class="chn-section" id="chn-catalog">
        <div>
            <h5>Add a channel</h5>
            <p>Connect as many accounts as you need, on any of these platforms.</p>
        </div>
        <div class="chn-tabs" role="tablist">
            <button type="button" class="chn-tab is-active" data-chn-group="all">All <span>{{ count($platforms) }}</span></button>
            @foreach ($groups as $groupKey => $groupLabel)
                <button type="button" class="chn-tab" data-chn-group="{{ $groupKey }}">{{ $groupLabel }} <span>{{ collect($platforms)->where('group', $groupKey)->count() }}</span></button>
            @endforeach
        </div>
    </div>

    <div class="chn-grid">
        @foreach ($platforms as $platform)
            <div class="chn-platform" style="--pc: {{ $platform['color'] }}" data-group="{{ $platform['group'] }}">
                <div class="chn-platform-top">
                    <div class="chn-logo"><i class="bx {{ $platform['icon'] }}"></i></div>
                    <div>
                        <h6>{{ $platform['name'] }}</h6>
                        <span class="chn-setup">
                            @if ($platform['setup'] === 'oauth')
                                <i class="bx bx-log-in-circle"></i> Sign in to connect
                            @else
                                <i class="bx bx-key"></i> API credentials
                            @endif
                        </span>
                    </div>
                </div>

                <p>{!! $platform['desc'] !!}</p>

                <div class="chn-platform-foot">
                    @if ($platform['connected'])
                        <span class="chn-connected"><i class="bx bxs-check-circle"></i> {{ $platform['connected'] }} connected</span>
                    @else
                        <span></span>
                    @endif

                    @if (isset($platform['href']))
                        <a href="{{ $platform['href'] }}" class="chn-btn chn-btn-sm chn-connect"><i class="bx bx-plus"></i> {{ $platform['connected'] ? 'Add another' : 'Connect' }}</a>
                    @else
                        <button type="button" class="chn-btn chn-btn-sm chn-connect" data-bs-toggle="modal" data-bs-target="#{{ $platform['modal'] }}"><i class="bx bx-plus"></i> {{ $platform['connected'] ? 'Add another' : 'Connect' }}</button>
                    @endif
                </div>

                @isset($platform['extra'])
                    <div class="chn-extra">
                        <a href="{{ $platform['extra']['href'] }}">{{ $platform['extra']['label'] }} <i class="bx bx-right-arrow-alt"></i></a>
                        <span>{{ $platform['extra']['hint'] }}</span>
                    </div>
                @endisset
            </div>
        @endforeach
    </div>

</div>

    <!-- Encrypted X Chat Modal -->
    <div class="modal fade" id="xChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="xChatForm" autocomplete="off">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#000"><i class="bx bx-lock-alt"></i></span> Enable encrypted X Chat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2">X now sends direct messages end-to-end encrypted. To show them as readable text in your inbox (and let AI Copilot answer them), Socialeaz needs your <strong>X Chat PIN</strong> for <strong id="xChatAccountName">this account</strong> - the PIN you set for encrypted messages in the X app.</p>
                        <ul class="small text-muted ps-3 mb-3">
                            <li>X's official encryption library uses it to unlock your encrypted chats on our server.</li>
                            <li>It is checked with X first, then stored encrypted. It is never shown, logged or shared.</li>
                            <li>Anyone with this PIN can read your encrypted X messages. Disable here at any time to delete it, and change your PIN in the X app if you stop using Socialeaz.</li>
                        </ul>
                        <div class="mb-3">
                            <label class="form-label">X Chat PIN *</label>
                            <input type="password" name="pin" class="form-control" minlength="4" maxlength="64" required autocomplete="new-password" inputmode="text">
                            @error('pin')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent" value="1" id="xChatConsent" required>
                            <label class="form-check-label small" for="xChatConsent">I'm the owner of this X account and allow Socialeaz to decrypt and send its encrypted X messages.</label>
                            @error('consent')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-dark w-100">Unlock & enable</button>
                    </div>
                </form>
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

@push('scripts')
<script>
    // Delegated on document: this page renders inside the Vue #app root,
    // which replaces the original DOM nodes after this script runs.

    // Point the shared X Chat PIN modal at the account whose button opened it.
    document.addEventListener('show.bs.modal', function (event) {
        if (event.target.id !== 'xChatModal' || !event.relatedTarget) return;
        document.getElementById('xChatForm').action = event.relatedTarget.dataset.action;
        document.getElementById('xChatAccountName').textContent = event.relatedTarget.dataset.name || 'this account';
    });

    // Platform category tabs.
    document.addEventListener('click', function (event) {
        const tab = event.target.closest('[data-chn-group]');
        if (!tab) return;
        const group = tab.dataset.chnGroup;
        document.querySelectorAll('[data-chn-group]').forEach(t => t.classList.toggle('is-active', t === tab));
        document.querySelectorAll('.chn-platform').forEach(card => {
            card.style.display = group === 'all' || card.dataset.group === group ? '' : 'none';
        });
    });

    // Connected-accounts search.
    document.addEventListener('input', function (event) {
        const input = event.target.closest('[data-chn-filter]');
        if (!input) return;
        const list = document.querySelector(input.dataset.chnFilter);
        const term = input.value.trim().toLowerCase();
        let shown = 0;
        list.querySelectorAll('.chn-row').forEach(row => {
            const match = !term || row.dataset.search.includes(term);
            row.style.display = match ? '' : 'none';
            shown += match ? 1 : 0;
        });
        list.querySelector('.chn-no-match').style.display = shown ? 'none' : 'block';
    });
</script>
@endpush
