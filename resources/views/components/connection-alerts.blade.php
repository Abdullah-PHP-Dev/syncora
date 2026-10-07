{{-- See App\View\Components\ConnectionAlerts. --}}
@php
    $capabilityLabel = __('admin.connections.capability.' . $capability);
    $copy = [
        'reconnect' => ['icon' => 'bx-error-circle', 'tone' => 'is-bad', 'text' => 'admin.connections.alert.reconnect', 'cta' => 'admin.connections.alert.reconnect_cta'],
        'upgrade' => ['icon' => 'bx-up-arrow-circle', 'tone' => 'is-info', 'text' => 'admin.connections.alert.upgrade', 'cta' => 'admin.connections.alert.upgrade_cta'],
        'not_connected' => ['icon' => 'bx-plug', 'tone' => 'is-muted', 'text' => 'admin.connections.alert.not_connected', 'cta' => 'admin.connections.alert.connect_cta'],
    ];
@endphp
<div class="conn-alerts" {{ $attributes }}>
    @foreach ($alerts as $alert)
        @php $c = $copy[$alert['reason']]; @endphp
        <div class="conn-alert {{ $c['tone'] }}" role="status">
            <i class="bx {{ $c['icon'] }}"></i>
            <span>{{ __($c['text'], ['platform' => $alert['label'], 'capability' => $capabilityLabel]) }}</span>
            <a href="{{ $alert['url'] }}" class="conn-alert-btn">{{ __($c['cta']) }}</a>
        </div>
    @endforeach
</div>

@once
    @push('styles')
        <style>
            .conn-alerts { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
            .conn-alert { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; font-size: 13px; }
            .conn-alert > i { font-size: 18px; flex-shrink: 0; }
            .conn-alert > span { flex: 1; min-width: 0; }
            .conn-alert.is-bad { background: #FDECEC; color: #991B1B; }
            .conn-alert.is-info { background: #EEF4FF; color: #1E3A8A; }
            .conn-alert.is-muted { background: #F4F5F9; color: #4B5263; }
            .conn-alert-btn { flex-shrink: 0; display: inline-flex; align-items: center; height: 32px; padding: 0 12px; border-radius: 9px; background: #fff; border: 1px solid rgba(16, 24, 40, .1); color: inherit; font-weight: 600; font-size: 12.5px; text-decoration: none; }
            .conn-alert-btn:hover { border-color: currentColor; color: inherit; }
        </style>
    @endpush
@endonce
