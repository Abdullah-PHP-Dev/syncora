@extends('layouts.app')
@section('title', __('notifications.preferences.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/notifications.css') }}?v={{ filemtime(public_path('assets/css/notifications.css')) }}">
@endpush

@section('content')
@php
    $quiet = $settings['quiet_hours'] ?? [];
    $daily = $settings['daily_hour'] ?? config('notifications.defaults.daily_digest_hour');
    $weekly = $settings['weekly_day'] ?? config('notifications.defaults.weekly_digest_day');
    $global = $workspaceId === 0;
@endphp
<div class="account-page">
    <header class="account-heading">
        <span class="account-eyebrow">{{ __('profile.settings') }}</span>
        <h1>{{ __('notifications.preferences.title') }}</h1>
        <p>{{ __('notifications.preferences.intro') }}</p>
    </header>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <form id="ntf-settings" class="account-card" method="POST" action="{{ route('admin.notifications.settings.update') }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="workspace" value="{{ $workspaceId }}">

        @if($workspaces->count() > 1 || ($workspaces->count() === 1 && $workspaces->keys()->first() !== $person->id))
        <section class="account-section">
            <div class="account-field" style="max-width:360px">
                <label for="ntf-workspace">{{ __('notifications.preferences.workspace_scope') }}</label>
                <select id="ntf-workspace" class="form-control" onchange="location.href='{{ route('admin.notifications.settings') }}' + (this.value !== '0' ? '?workspace=' + this.value : '')">
                    <option value="0" @selected($global)>{{ __('notifications.preferences.all_workspaces') }}</option>
                    @foreach($workspaces as $id => $name)
                        <option value="{{ $id }}" @selected($workspaceId === (int) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </section>
        @endif

        @if($global)
        <section class="account-section" style="border-top:1px solid var(--theme-border)">
            <div class="account-section-heading"><span class="account-icon"><i class="bx bx-slider-alt" aria-hidden="true"></i></span><div><h2>{{ __('notifications.preferences.general') }}</h2></div></div>
            <div class="account-fields">
                <div class="account-field account-field-wide">
                    <input type="hidden" name="email_enabled" value="0">
                    <label><input type="checkbox" class="ntf-switch" name="email_enabled" value="1" @checked(($settings['email_enabled'] ?? true) !== false)> {{ __('notifications.preferences.email_enabled') }}</label>
                    <small>{{ __('notifications.preferences.email_enabled_hint') }}</small>
                </div>
                <div class="account-field">
                    <label for="ntf-locale">{{ __('notifications.preferences.language') }}</label>
                    <select id="ntf-locale" name="locale" class="form-control">
                        <option value="">{{ __('notifications.preferences.language_auto') }}</option>
                        <option value="en" @selected($person->locale === 'en')>English</option>
                        <option value="ar" @selected($person->locale === 'ar')>العربية</option>
                    </select>
                </div>
                <div class="account-field">
                    <label for="ntf-timezone">{{ __('notifications.preferences.timezone') }}</label>
                    <select id="ntf-timezone" name="timezone" class="form-control">
                        <option value="">{{ __('notifications.preferences.timezone_auto') }}</option>
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected($person->timezone === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="account-field account-field-wide">
                    <label><input type="checkbox" class="ntf-switch" name="quiet_enabled" value="1" @checked(!empty($quiet['enabled']))> {{ __('notifications.preferences.quiet_hours') }}</label>
                    <div class="ntf-inline">
                        <span>{{ __('notifications.preferences.from') }}</span>
                        <input type="time" name="quiet_start" class="form-control" value="{{ $quiet['start'] ?? '22:00' }}">
                        <span>{{ __('notifications.preferences.to') }}</span>
                        <input type="time" name="quiet_end" class="form-control" value="{{ $quiet['end'] ?? '08:00' }}">
                    </div>
                    <small>{{ __('notifications.preferences.quiet_hours_hint') }}</small>
                </div>
                <div class="account-field">
                    <label for="ntf-daily">{{ __('notifications.preferences.daily_hour') }}</label>
                    <select id="ntf-daily" name="daily_hour" class="form-control">
                        @for($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}" @selected((int) $daily === $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="account-field">
                    <label for="ntf-weekly">{{ __('notifications.preferences.weekly_day') }}</label>
                    <select id="ntf-weekly" name="weekly_day" class="form-control">
                        @foreach(__('notifications.preferences.days') as $i => $day)
                            <option value="{{ $i }}" @selected((int) $weekly === $i)>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>
        @endif

        @foreach($groups as $category => $events)
        <details class="ntf-cat" id="category-{{ $category }}" @if($focus === $category || ($focus === null && $loop->index < 2)) open @endif>
            <summary>
                <span class="account-icon"><i class="bx {{ config('notifications.categories.'.$category.'.icon', 'bx-bell') }}" aria-hidden="true"></i></span>
                <div><h2>{{ __('notifications.categories.'.$category) }}</h2><p>{{ __('notifications.category_hints.'.$category) }}</p></div>
                <i class="bx bx-chevron-down" aria-hidden="true"></i>
            </summary>
            <table class="ntf-table">
                <thead><tr><th scope="col"></th><th scope="col">{{ __('notifications.preferences.in_app') }}</th><th scope="col">{{ __('notifications.preferences.email') }}</th></tr></thead>
                <tbody>
                @foreach($events as $event)
                    <tr>
                        <td>
                            {{ $event['label'] }}
                            @if($event['mandatory'])<span class="ntf-hint">{{ __('notifications.preferences.locked_hint') }}</span>
                            @elseif($event['failure_safe'])<span class="ntf-hint">{{ __('notifications.preferences.failure_safe_hint') }}</span>@endif
                        </td>
                        <td>
                            @if($event['mandatory'])
                                <span class="ntf-lock" title="{{ __('notifications.preferences.locked') }}"><i class="bx bx-lock-alt"></i></span>
                            @else
                                <input type="hidden" name="events[{{ $event['key'] }}][in_app]" value="0">
                                <input type="checkbox" class="ntf-switch" name="events[{{ $event['key'] }}][in_app]" value="1" aria-label="{{ $event['label'] }} — {{ __('notifications.preferences.in_app') }}" @checked($event['choice']['in_app']) @disabled($event['failure_safe'] && $event['choice']['email'] === 'off')>
                            @endif
                        </td>
                        <td>
                            @if($event['mandatory'])
                                <span class="ntf-lock"><i class="bx bx-lock-alt"></i> {{ __('notifications.preferences.always') }}</span>
                            @else
                                <select name="events[{{ $event['key'] }}][email]" aria-label="{{ $event['label'] }} — {{ __('notifications.preferences.email') }}">
                                    @foreach($event['allowed'] as $cadence)
                                        <option value="{{ $cadence }}" @selected($event['choice']['email'] === $cadence)>{{ __('notifications.cadences.'.$cadence) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </details>
        @endforeach

        @if($global)
        <section class="account-section" style="border-top:1px solid var(--theme-border)">
            <div class="account-field account-field-wide">
                <label><input type="checkbox" class="ntf-switch" name="marketing" value="1" @checked($person->marketing_opt_in_at)> {{ __('notifications.preferences.marketing') }}</label>
                <small>{{ __('notifications.preferences.marketing_hint') }}</small>
            </div>
        </section>
        @endif

        <footer class="account-footer">
            <span role="status" id="ntf-status"></span>
            <button type="submit" class="btn btn-primary account-save"><i class="bx bx-check" aria-hidden="true"></i><span>{{ __('notifications.preferences.save') }}</span></button>
        </footer>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('ntf-settings');
    const status = document.getElementById('ntf-status');
    form?.addEventListener('submit', (e) => {
        e.preventDefault();
        const button = form.querySelector('.account-save');
        button.disabled = true;
        fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => { status.textContent = ok ? data.message : (data.message || 'Error'); })
            .catch(() => { status.textContent = 'Error'; })
            .finally(() => { button.disabled = false; });
    });
})();
</script>
@endpush
