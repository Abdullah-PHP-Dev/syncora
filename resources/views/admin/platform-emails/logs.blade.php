@extends('layouts.app')
@section('title', __('platform_emails.log.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/platform-emails.css') }}?v={{ filemtime(public_path('assets/css/platform-emails.css')) }}">
@endpush

@section('content')
@php
    $tone = ['sent' => 'on', 'grouped' => 'on', 'failed' => 'off', 'suppressed' => 'off', 'skipped' => 'default', 'digested' => 'custom', 'queued' => 'draft', 'sending' => 'draft'];
@endphp
{{-- v-pre: the page sits inside the #app Vue root; template text like "{{ plan }}" must stay text. --}}
<div class="account-page" v-pre>
    @include('admin.platform-emails._nav', ['title' => __('platform_emails.log.title'), 'intro' => __('platform_emails.log.intro')])

    <section class="account-card">
        <form class="pe-filters" method="GET" action="{{ route('admin.platform-emails.logs') }}">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('platform_emails.log.search') }}" aria-label="{{ __('platform_emails.log.search') }}">
            <select name="event" aria-label="{{ __('platform_emails.log.event') }}">
                <option value="">{{ __('platform_emails.log.all_events') }}</option>
                @foreach($events as $event)<option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>@endforeach
            </select>
            <select name="status" aria-label="{{ __('platform_emails.log.status') }}">
                <option value="">{{ __('platform_emails.filters.all_status') }}</option>
                @foreach(__('platform_emails.log.statuses') as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn btn-primary btn-sm" type="submit">{{ __('platform_emails.filters.apply') }}</button>
        </form>

        @if($deliveries->isEmpty())
            <div class="pe-empty">{{ __('platform_emails.log.empty') }}</div>
        @else
        <div class="table-responsive">
        <table class="pe-table">
            <thead><tr>
                <th>{{ __('platform_emails.log.recipient') }}</th><th>{{ __('platform_emails.log.event') }}</th><th>{{ __('platform_emails.log.subject') }}</th>
                <th>{{ __('platform_emails.log.status') }}</th><th>{{ __('platform_emails.log.template') }}</th><th>{{ __('platform_emails.log.queued') }}</th><th>{{ __('platform_emails.log.sent') }}</th>
            </tr></thead>
            <tbody>
            @foreach($deliveries as $d)
                <tr>
                    <td><bdi>{{ $d->to_address }}</bdi><small>{{ strtoupper((string) $d->locale) }}</small></td>
                    <td class="pe-mono">{{ $d->notificationEvent?->event }}</td>
                    <td><div class="pe-subject" dir="auto" title="{{ $d->subject }}">{{ $d->subject ?? '—' }}</div></td>
                    <td>
                        <span class="pe-badge {{ $tone[$d->status] ?? 'default' }}">{{ __('platform_emails.log.statuses.'.$d->status) }}</span>
                        @if($d->error)<small title="{{ $d->error }}">{{ \Illuminate\Support\Str::limit($d->error, 60) }}</small>@endif
                        @if($d->attempts > 1)<small>× {{ $d->attempts }}</small>@endif
                    </td>
                    <td class="pe-mono">{{ $d->template_version ?? '—' }}</td>
                    <td>{{ $d->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $d->sent_at?->format('Y-m-d H:i') ?? ($d->failed_at?->format('Y-m-d H:i') ?? '—') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="pe-pages">{{ $deliveries->links() }}</div>
        @endif
    </section>
</div>
@endsection
