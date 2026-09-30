{{-- Usage: <x-connect-social-media />. Renders the button and its modal. --}}
<button type="button" {{ $attributes->class(['btn btn-primary']) }} data-bs-toggle="modal" data-bs-target="#{{ $id }}" aria-controls="{{ $id }}">
    <i class="bx bx-link-alt me-1"></i> {{ __('Connect Social Media') }}
</button>


    @php
        // Same social-card-mini / social-icon-mini system already used by
        // the Ads "Connect Account" modal (admin/ads/dashboard.blade.php)
        // and the Posts dashboard's platform badges - reused here instead
        // of inventing a new style, so every "connect a platform" surface
        // in the app looks and behaves the same way.
        $connectPlatforms = [
            'facebook' => ['label' => 'Facebook', 'icon' => 'bxl-facebook', 'class' => 'facebook', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads + Messaging'],
            'google'   => ['label' => 'Google / YouTube', 'icon' => 'bxl-google', 'class' => 'google', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads'],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'bxl-linkedin', 'class' => 'linkedin', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting + Ads'],
            'tiktok'   => ['label' => 'TikTok', 'icon' => 'bxl-tiktok', 'class' => 'tiktok', 'route' => 'admin.social-accounts.redirect', 'tag' => 'Posting'],
            'instagram'=> ['label' => 'Instagram', 'icon' => 'bxl-instagram', 'class' => 'instagram', 'route' => 'admin.post-accounts.instagram.redirect', 'tag' => 'Posting'],
            'x'        => ['label' => 'X', 'icon' => 'bxl-twitter', 'class' => 'twitter', 'route' => 'admin.post-accounts.x.redirect', 'tag' => 'Posting'],
            'threads'  => ['label' => 'Threads', 'icon' => 'bx-at', 'class' => 'threads', 'route' => 'admin.post-accounts.threads.redirect', 'tag' => 'Posting'],
            'pinterest'=> ['label' => 'Pinterest', 'icon' => 'bx-share-alt', 'class' => 'pinterest', 'route' => 'admin.post-accounts.pinterest.redirect', 'tag' => 'Posting'],
        ];
    @endphp

    {{-- Connect Social Media Modal - the four platforms whose OAuth model
         supports it get one combined redirect for posting + ads + messaging
         consent (see SocialAuthService); the rest use their existing
         posting-only redirect. Platforms that need manual credential entry
         instead of an OAuth redirect (WhatsApp, Telegram, Discord, Slack,
         LINE, Teams, Matrix, Zalo, Google Chat) are managed from
         Messaging > Channels instead of duplicating those forms here. --}}
    <div class="modal fade" id="{{ $id }}" aria-labelledby="{{ $id }}-title" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg social-modal">
                <div class="modal-header border-0 pb-0 mt-0 pt-0">
                    <div>
                        <h4 id="{{ $id }}-title" class="mb-1 font-weight-bold mb-0 mt-0">{{ __('Connect Social Media') }}</h4>
                        <small class="text-muted">Choose a platform to authorize - already-connected accounts are marked below.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div class="row">
                        @foreach ($connectPlatforms as $platform => $meta)
                            @php $isConnected = in_array($platform, $connectedPlatforms); @endphp
                            <div class="col-6 col-md-3 mb-3">
                                <div class="social-card-mini">
                                    <a href="{{ $isConnected ? route('admin.posts.create') : route($meta['route'], $meta['route'] === 'admin.social-accounts.redirect' ? ['platform' => $platform] : []) }}">
                                        <div class="social-icon-mini {{ $meta['class'] }}">
                                            <i class="bx {{ $meta['icon'] }}"></i>
                                        </div>
                                        <h6 class="mt-2 mb-1">{{ $meta['label'] }}</h6>
                                        <small class="text-muted d-block mb-1">{{ $meta['tag'] }}</small>
                                        @if ($isConnected)
                                            <small class="connected-text"><i class="bx bx-check-circle"></i> Connected</small>
                                        @else
                                            <small class="disconnected-text">Connect</small>
                                        @endif
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-body-secondary small mb-0 mt-2">
                        Need WhatsApp, Telegram, Discord, Slack, or another messaging channel?
                        <a href="{{ route('admin.chats.channels') }}">Manage channels</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
