@extends('layouts.app')

@section('title', 'Connected Channels')

<style>
    .channel-card {
        border: 1px solid #eef1f5;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
    }

    .channel-platform-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 22px;
    }

    .channel-platform-icon.facebook { background: #1877F2; }
    .channel-platform-icon.instagram { background: #E1306C; }
    .channel-platform-icon.whatsapp { background: #25D366; }
    .channel-platform-icon.telegram { background: #229ED9; }
    .channel-platform-icon.x { background: #000; }

    .connected-channel-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f6f7f9;
    }

    .connected-channel-row:last-child {
        border-bottom: none;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Connected Channels</h4>
            <a href="{{ route('admin.chats.dashboard') }}" class="btn btn-outline-primary btn-sm">
                <i class="bx bx-message-dots"></i> Go to Inbox
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($channels->isNotEmpty())
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Your Connected Channels</h6>
                    @foreach ($channels as $channel)
                        <div class="connected-channel-row">
                            <div class="channel-platform-icon {{ $channel->platform }}">
                                <i class="bx bxl-{{ $channel->platform === 'x' ? 'twitter' : $channel->platform }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $channel->name }}</div>
                                <small class="text-muted text-capitalize">{{ $channel->platform }} @if($channel->username) &middot; {{ $channel->username }} @endif</small>
                            </div>
                            <span class="badge {{ $channel->status ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $channel->status ? 'Active' : 'Inactive' }}</span>
                            <form action="{{ route('admin.messaging.channels.destroy', ['channel' => $channel->id]) }}" method="POST" onsubmit="return confirm('Disconnect this channel? Existing conversations are kept, but it will stop sending/receiving.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Disconnect</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-md-6 col-lg-4">
                <div class="channel-card text-center">
                    <div class="channel-platform-icon facebook mx-auto mb-3"><i class="bx bxl-facebook"></i></div>
                    <h6>Facebook Messenger &amp; Instagram</h6>
                    <p class="text-muted small">One connection covers both - every Page you manage, plus its linked Instagram professional account.</p>
                    <a href="{{ route('admin.messaging.auth.meta.redirect') }}" class="btn btn-primary btn-sm">Connect with Facebook</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="channel-card text-center">
                    <div class="channel-platform-icon x mx-auto mb-3"><i class="bx bxl-twitter"></i></div>
                    <h6>X (Twitter) DMs</h6>
                    <p class="text-muted small">New messages are checked roughly every minute (X's real-time DM webhooks require an Enterprise tier).</p>
                    <a href="{{ route('admin.messaging.auth.x.redirect') }}" class="btn btn-dark btn-sm">Connect X Account</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="channel-card text-center">
                    <div class="channel-platform-icon telegram mx-auto mb-3"><i class="bx bxl-telegram"></i></div>
                    <h6>Telegram Bot</h6>
                    <p class="text-muted small">Create a bot with <a href="https://t.me/BotFather" target="_blank">@BotFather</a>, then paste its token below.</p>
                    <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#telegramModal">Connect Telegram Bot</button>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="channel-card text-center">
                    <div class="channel-platform-icon whatsapp mx-auto mb-3"><i class="bx bxl-whatsapp"></i></div>
                    <h6>WhatsApp Business</h6>
                    <p class="text-muted small">Paste the Phone Number ID and permanent access token from your Meta Business System User.</p>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#whatsappModal">Connect WhatsApp Number</button>
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
                        <h5 class="modal-title">Connect Telegram Bot</h5>
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
    <div class="modal fade" id="whatsappModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admin.messaging.channels.whatsapp.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Connect WhatsApp Number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="name" class="form-control" required>
                            @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number ID *</label>
                            <input type="text" name="phone_number_id" class="form-control" required>
                            @error('phone_number_id')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permanent Access Token *</label>
                            <input type="text" name="access_token" class="form-control" required>
                            @error('access_token')<p class="text-danger small">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Connect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
