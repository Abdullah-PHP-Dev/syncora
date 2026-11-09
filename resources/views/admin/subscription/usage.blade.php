@extends('layouts.app')
@section('title', __('package_access.page.title'))

@php
    $locale = app()->getLocale();
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale($locale)->translatedFormat('j M Y') : '—';
    $number = fn ($value, $decimals = 0) => number_format((float) $value, $decimals);

    // One row per feature: how full it is, in plain words.
    $rows = collect($data['usage'])->map(function ($item) use ($number) {
        $used = (float) $item['used'];
        $limit = (int) $item['limit'];
        $storage = $item['key'] === 'media_storage_gb';
        $fmt = fn ($v) => $storage ? __('package_access.page.gb', ['value' => $number($v, $v > 0 && $v < 10 ? 2 : 0)]) : $number($v);
        $percent = $limit > 0 ? (int) min(100, round(100 * $used / $limit)) : 0;
        $state = match (true) {
            $limit === -1 => 'unlimited',
            $limit <= 0 => 'off',
            // A single slot (one workspace, the owner's seat) is the plan's shape, not a warning.
            $limit === 1 => 'ok',
            $percent >= 100 => 'full',
            $percent >= 80 => 'near',
            default => 'ok',
        };
        $value = match ($state) {
            'unlimited' => $fmt($used).' · '.__('package_access.page.unlimited'),
            'off' => __('package_access.page.not_included'),
            default => $fmt($used).' / '.$fmt($limit),
        };

        return $item + ['percent' => $percent, 'state' => $state, 'value' => $value];
    });

    $status = $data['active'] ? ($data['status'] ?? 'active') : 'inactive';
    $daysLeft = $data['expires_at'] ? max(0, (int) ceil(now()->diffInDays(\Illuminate\Support\Carbon::parse($data['expires_at'])->endOfDay(), false))) : null;
    $allowanceNote = $data['period_start']
        ? ($data['period_end']
            ? __('package_access.page.allowance_sub', ['start' => $date($data['period_start']), 'end' => $date($data['period_end'])])
            : __('package_access.page.allowance_sub_open', ['start' => $date($data['period_start'])]))
        : '';
@endphp

@push('styles')
<style>
    .us { --purple: var(--theme-purple, #720aff); --grad: var(--theme-gradient, linear-gradient(110deg, #004cff, #710bff)); --soft: var(--theme-soft, #f1eaff);
          --ink: var(--theme-ink, #09072d); --muted: var(--theme-muted, #626498); --line: var(--theme-border, #edf0fa);
          --ok: #15803D; --warn: #B45309; --bad: #B91C1C; max-width: 860px; margin: 0 auto; padding: 8px 0 40px; color: var(--ink); }
    .us *, .us *::before, .us *::after { box-sizing: border-box; }

    .us-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
    .us-head h1 { margin: 0 0 6px; font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
    .us-head p { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0; color: var(--muted); font-size: 14.5px; }
    .us-head p strong { color: var(--ink); }
    .us-status { padding: 2px 9px; border-radius: 999px; background: var(--soft); color: var(--purple); font-size: 12px; font-weight: 700; }
    .us-status.is-active { background: #DCFCE7; color: var(--ok); }
    .us-status.is-expired, .us-status.is-cancelled, .us-status.is-inactive { background: #FEE2E2; color: var(--bad); }
    .us-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 18px; border-radius: 12px; background: var(--grad); color: #fff; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .us-btn:hover { color: #fff; box-shadow: 0 8px 20px rgba(54, 11, 255, .25); }
    .us-btn:focus-visible { outline: 3px solid rgba(114, 10, 255, .35); outline-offset: 2px; }

    .us-section { margin-top: 20px; border: 1px solid var(--line); border-radius: 18px; background: #fff; }
    .us-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 16px 20px; border-bottom: 1px solid var(--line); }
    .us-section-head h2 { margin: 0; font-size: 16px; font-weight: 700; }
    .us-section-head p { margin: 0; color: var(--muted); font-size: 13px; }
    .us-list { margin: 0; padding: 0; list-style: none; }
    .us-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(120px, 220px) auto; align-items: center; gap: 16px; padding: 14px 20px; }
    .us-row + .us-row { border-top: 1px solid var(--line); }
    .us-name { font-size: 14.5px; font-weight: 600; }
    .us-bar { height: 6px; border-radius: 999px; background: #EEF0F8; overflow: hidden; }
    .us-bar span { display: block; height: 100%; border-radius: inherit; background: var(--purple); }
    .us-value { min-width: 110px; text-align: end; font-size: 14px; font-weight: 700; white-space: nowrap; }
    .us-row.is-near .us-bar span { background: #F59E0B; }
    .us-row.is-near .us-value { color: var(--warn); }
    .us-row.is-full .us-bar span { background: #EF4444; }
    .us-row.is-full .us-value { color: var(--bad); }
    .us-row.is-off .us-name, .us-row.is-off .us-value { color: var(--muted); font-weight: 500; }
    .us-row.is-unlimited .us-bar span { width: 100% !important; opacity: .25; }
    .us-note { margin: 16px 4px 0; color: var(--muted); font-size: 12.5px; line-height: 1.6; }

    @media (max-width: 640px) {
        .us-row { grid-template-columns: minmax(0, 1fr) auto; row-gap: 8px; padding: 14px 16px; }
        .us-bar { grid-column: 1 / -1; grid-row: 2; }
        .us-section-head { padding: 14px 16px; }
        .us-btn { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="us" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">

    <header class="us-head">
        <div>
            <h1>{{ __('package_access.page.title') }}</h1>
            <p>
                <strong>{{ $data['package'] ?? __('package_access.page.no_plan') }}</strong>
                <span class="us-status is-{{ $status }}">{{ __('package_access.page.statuses.'.$status) }}</span>
                @if($data['expires_at'])
                    <span>· {{ $status === 'active' ? __('package_access.page.renews') : __('package_access.page.ends') }} {{ $date($data['expires_at']) }}@if($daysLeft !== null) ({{ trans_choice('package_access.page.days_left', $daysLeft, ['count' => $daysLeft]) }})@endif</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.subscription.select') }}" class="us-btn">{{ __('package_access.page.view_plans') }}</a>
    </header>

    @foreach([
        ['kind' => 'capacity', 'title' => __('package_access.page.capacity_title'), 'sub' => __('package_access.page.capacity_sub')],
        ['kind' => 'allowance', 'title' => __('package_access.page.allowance_title'), 'sub' => $allowanceNote],
    ] as $section)
        @php($items = $rows->where('kind', $section['kind']))
        @continue($items->isEmpty())
        <section class="us-section">
            <div class="us-section-head">
                <h2>{{ $section['title'] }}</h2>
                <p>{{ $section['sub'] }}</p>
            </div>
            <ul class="us-list">
                @foreach($items as $row)
                    <li class="us-row is-{{ $row['state'] }}">
                        <span class="us-name">{{ $row['name'] }}</span>
                        <div class="us-bar" role="progressbar" aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $row['name'] }}">
                            <span style="width: {{ $row['state'] === 'off' ? 0 : $row['percent'] }}%"></span>
                        </div>
                        <span class="us-value">{{ $row['value'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <p class="us-note">{{ __('package_access.page.note') }}</p>
</div>
@endsection
