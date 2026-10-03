{{-- Right-hand "Chat Details" column - mirrors renderDetailsPanel() in
     dashboard.blade.php's script, since that JS replaces this same markup
     whenever the admin switches conversations (see thread.blade.php's own
     header comment for why this mirroring approach exists). $platformIcons/
     $platformColors/$platformLabel are inherited from dashboard.blade.php. --}}
<div class="details-header">
    {{ __('Chat Details') }}
    <button type="button" class="details-close" id="closeDetailsBtn" title="{{ __('Hide panel') }}"><i class="bx bx-x"></i></button>
</div>

<div class="details-section">
    <div class="details-section-title">{{ __(':name\'s Contact Details', ['name' => $conversation->customer_name ?: __('Unknown')]) }}</div>
    <div class="details-row">
        <span class="details-row-label">{{ __('Name') }}</span>
        <span class="details-row-value">{{ $conversation->customer_name ?: __('Unknown') }}</span>
    </div>
    <div class="details-row">
        <span class="details-row-label">{{ __('Agent') }}</span>
        <span class="details-row-value">{{ $conversation->assignedUser->name ?? __('Unassigned') }}</span>
    </div>
    @php $contactMeta = $conversation->meta ?? []; @endphp
    @if (!empty($contactMeta['phone']))
        <div class="details-row">
            <span class="details-row-label">{{ __('Phone') }}</span>
            <span class="details-row-value">{{ $contactMeta['phone'] }}</span>
        </div>
    @endif
    @if (!empty($contactMeta['email']))
        <div class="details-row">
            <span class="details-row-label">{{ __('E-mail') }}</span>
            <span class="details-row-value">{{ $contactMeta['email'] }}</span>
        </div>
    @endif
</div>

<div class="details-section">
    <div class="details-section-title">{{ __('Platform History') }}</div>
    <div class="details-platform-icons">
        @foreach ($platformHistory as $platform)
            <span class="details-platform-icon" style="background:{{ $platformColors[$platform] ?? '#6d28d9' }}" title="{{ $platformLabel($platform) }}">
                <i class="bx {{ $platformIcons[$platform] ?? 'bx-message-rounded-dots' }}"></i>
            </span>
        @endforeach
    </div>
</div>

<div class="details-section">
    <div class="details-section-title">{{ __('Recent Activity') }}</div>
    <div class="details-row">
        <span class="details-row-label">{{ __('Messages') }}</span>
        <span class="details-row-value">{{ $messageCount }}</span>
    </div>
    <div class="details-row">
        <span class="details-row-label">{{ __('Last activity') }}</span>
        <span class="details-row-value">{{ optional($conversation->last_message_at)->diffForHumans() ?? '—' }}</span>
    </div>
</div>

<div class="details-section">
    <div class="details-section-title">{{ __('Open Cases') }}</div>
    <div class="details-empty">{{ __('No open cases.') }}</div>
</div>

<div class="details-section">
    <div class="details-section-title">{{ __('AI Sentiment Analysis') }}</div>
    <div class="details-placeholder">
        <i class="bx bx-time-five"></i> {{ __('Not available yet') }}
    </div>
</div>
