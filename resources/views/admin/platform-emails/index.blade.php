@extends('layouts.app')
@section('title', __('platform_emails.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}?v={{ filemtime(public_path('assets/css/profile.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/platform-emails.css') }}?v={{ filemtime(public_path('assets/css/platform-emails.css')) }}">
@endpush

@section('content')
{{-- v-pre: the page sits inside the #app Vue root; template text like "{{ plan }}" must stay text. --}}
<div class="account-page" v-pre>
    @include('admin.platform-emails._nav', ['title' => __('platform_emails.title'), 'intro' => __('platform_emails.intro')])

    <div class="pe-create-bar">
        <a class="btn btn-primary" href="{{ route('admin.platform-emails.create') }}"><i class="bx bx-plus" aria-hidden="true"></i> {{ __('platform_emails.create.button') }}</a>
    </div>

    <div class="pe-stats">
        @foreach($stats as $label => $value)
            <div class="account-card pe-stat"><b>{{ $value }}</b><span>{{ __('platform_emails.stats.'.$label) }}</span></div>
        @endforeach
    </div>

    <section class="account-card">
        <form class="pe-filters" method="GET" action="{{ route('admin.platform-emails.index') }}">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('platform_emails.filters.search') }}" aria-label="{{ __('platform_emails.filters.search') }}">
            <select name="module" aria-label="{{ __('platform_emails.columns.module') }}">
                <option value="">{{ __('platform_emails.filters.all_modules') }}</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ __('notifications.categories.'.$module) }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="{{ __('platform_emails.columns.status') }}">
                <option value="">{{ __('platform_emails.filters.all_status') }}</option>
                @foreach(__('platform_emails.statuses') as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="locale" aria-label="{{ __('platform_emails.columns.language') }}">
                <option value="">{{ __('platform_emails.filters.all_languages') }}</option>
                @foreach(__('platform_emails.languages') as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['locale'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary btn-sm" type="submit">{{ __('platform_emails.filters.apply') }}</button>
            @if(array_filter($filters))<a class="btn btn-light btn-sm" href="{{ route('admin.platform-emails.index') }}">{{ __('platform_emails.filters.clear') }}</a>@endif
        </form>

        @if($templates->isEmpty())
            <div class="pe-empty">{{ __('platform_emails.empty') }}</div>
        @else
        <div class="table-responsive">
        <table class="pe-table">
            <thead><tr>
                <th>{{ __('platform_emails.columns.name') }}</th><th>{{ __('platform_emails.columns.module') }}</th><th>{{ __('platform_emails.columns.subject') }}</th>
                <th>{{ __('platform_emails.columns.language') }}</th><th>{{ __('platform_emails.columns.status') }}</th><th>{{ __('platform_emails.columns.updated') }}</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($templates as $t)
                <tr>
                    <td>
                        <a class="pe-name" href="{{ route('admin.platform-emails.edit', ['key' => $t['key'], 'locale' => $t['locale']]) }}">{{ $t['name'] }}</a>
                        <small class="pe-mono">{{ $t['key'] }}</small>
                    </td>
                    <td>{{ __('notifications.categories.'.$t['module']) }}</td>
                    <td><div class="pe-subject" dir="{{ $t['locale'] === 'ar' ? 'rtl' : 'ltr' }}" title="{{ $t['subject'] }}">{{ $t['subject'] }}</div></td>
                    <td>{{ __('platform_emails.languages.'.$t['locale']) }}</td>
                    <td>
                        @if($t['locked'])<span class="pe-badge lock">{{ __('platform_emails.badges.locked') }}</span>
                        @else<span class="pe-badge {{ $t['active'] ? 'on' : 'off' }}">{{ __('platform_emails.statuses.'.($t['active'] ? 'active' : 'disabled')) }}</span>@endif
                        <span class="pe-badge {{ $t['custom'] ? 'custom' : 'default' }}">{{ __('platform_emails.badges.'.($t['custom'] ? 'custom' : 'default')) }}@if($t['custom']) v{{ $t['version'] }}@endif</span>
                        @if($t['draft'])<span class="pe-badge draft">{{ __('platform_emails.badges.draft') }}</span>@endif
                    </td>
                    <td>
                        @if($t['updated_at']){{ $t['updated_at']->diffForHumans() }}<small>{{ $t['updated_by'] ? __('platform_emails.by', ['name' => $t['updated_by']]) : '' }}</small>
                        @else<small>{{ __('platform_emails.never') }}</small>@endif
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.platform-emails.edit', ['key' => $t['key'], 'locale' => $t['locale']]) }}">{{ __('platform_emails.actions.edit') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="pe-pages">{{ $templates->links() }}</div>
        @endif
    </section>
</div>
@endsection
