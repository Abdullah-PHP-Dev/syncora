@extends('layouts.app')
@section('title', __('seller_team.title'))
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/seller-team.css') }}?v={{ filemtime(public_path('assets/css/seller-team.css')) }}">
@endpush
@section('content')
@php
    $joined = $members->filter(fn ($member) => $member->accepted_at)->count();
    $pending = $members->filter(fn ($member) => !$member->accepted_at && $member->expires_at?->isFuture())->count();
    $available = $limit === -1 ? null : max(0, $limit - $seats);
    $percent = $limit > 0 ? min(100, (int) round($seats / $limit * 100)) : ($limit === -1 ? 0 : 100);
    $owner = auth()->user();
    $initials = fn ($name) => mb_strtoupper(collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
@endphp
<div class="st-page">
    <header class="st-heading">
        <div><span class="st-eyebrow">{{ __('seller_team.workspace_label') }}</span><h1>{{ __('seller_team.title') }}</h1><p>{{ __('seller_team.description') }}</p></div>
        <a class="st-button st-button-primary" href="#team-invite" data-team-invite><i class="bi bi-person-plus" aria-hidden="true"></i>{{ __('seller_team.invite') }}</a>
    </header>

    @if(session('success'))<div class="st-notice st-notice-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('success') }}</span></div>@endif
    @if($errors->any())<div class="st-notice st-notice-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>@endif
    @if(session('invitation_url'))
<section class="st-share" aria-label="{{ __('seller_team.share_link') }}"><span class="st-icon"><i class="bi bi-link-45deg" aria-hidden="true"></i></span><div><h2>{{ __('seller_team.share_link') }}</h2><p>{{ __('seller_team.share_help') }}</p><label class="visually-hidden" for="team-invitation-link">{{ __('seller_team.share_link') }}</label><div class="st-copy-row"><input id="team-invitation-link" readonly value="{{ session('invitation_url') }}" dir="ltr"><button type="button" class="st-button st-button-secondary" data-team-copy data-copied="{{ __('seller_team.copied') }}" data-manual="{{ __('seller_team.copy_manually') }}"><i class="bi bi-copy" aria-hidden="true"></i><span>{{ __('seller_team.copy_link') }}</span></button></div><span class="st-copy-feedback" role="status" aria-live="polite"></span></div></section>
    @endif

    <div class="st-stats">
        @foreach([['bi-people', 'active_members', $joined + 1, 'active_help', 'purple'], ['bi-envelope-paper', 'pending_invites', $pending, 'pending_help', 'amber'], ['bi-person-check', 'available_seats', $available === null ? '∞' : $available, 'available_help', 'blue']] as [$icon, $label, $value, $help, $color])
        <div class="st-stat"><span class="st-icon st-icon-{{ $color }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><div><span>{{ __('seller_team.'.$label) }}</span><strong>{{ $value }}</strong><small>{{ __('seller_team.'.$help) }}</small></div></div>
        @endforeach
    </div>

    <details id="team-invite" class="st-panel st-invitation" @if($errors->any()) open @endif>
        <summary><span class="st-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span><span><strong>{{ __('seller_team.invite') }}</strong><small>{{ __('seller_team.invite_hint') }}</small></span><i class="bi bi-chevron-down st-chevron" aria-hidden="true"></i></summary>
        <div class="st-invitation-body">
        @if($canInvite)
            <form action="{{ route('admin.seller-team.store') }}" method="POST">@csrf
                <div class="st-email-field"><label for="team-email">{{ __('seller_team.email_address') }}</label><div><i class="bi bi-envelope" aria-hidden="true"></i><input id="team-email" type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('seller_team.email_placeholder') }}" autocomplete="email" required></div></div>
                @include('admin.seller-team.permissions', ['values' => old('permissions', []), 'prefix' => 'invite'])
                <div class="st-form-footer"><span><i class="bi bi-shield-check" aria-hidden="true"></i>{{ __('seller_team.private_invite') }}</span><button class="st-button st-button-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>{{ __('seller_team.create_invitation') }}</button></div>
            </form>
        @else
            <div class="st-locked"><span class="st-icon"><i class="bi bi-lock" aria-hidden="true"></i></span><div><h3>{{ __('seller_team.unlock_team') }}</h3><p>{{ __('seller_team.package_required') }}</p><a class="st-button st-button-primary" href="{{ route('admin.subscription.select') }}">{{ __('package_access.view_packages') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div></div>
        @endif
        </div>
    </details>

    <div class="st-layout">
        <section class="st-panel st-roster" aria-labelledby="team-members-heading">
            <div class="st-panel-heading"><div><h2 id="team-members-heading">{{ __('seller_team.members') }} <span class="st-count">{{ $members->count() + 1 }}</span></h2><p>{{ __('seller_team.roster_help') }}</p></div><span class="st-icon st-icon-subtle"><i class="bi bi-people" aria-hidden="true"></i></span></div>
            <div class="st-member st-owner"><span class="st-avatar">{{ $initials($owner->name) }}</span><div class="st-member-info"><h3>{{ $owner->name }} <span class="st-you">{{ __('seller_team.you') }}</span></h3><p>{{ $owner->email }}</p><small>{{ __('seller_team.full_access') }}</small></div><span class="st-status st-status-owner"><i class="bi bi-stars" aria-hidden="true"></i>{{ __('seller_team.owner') }}</span></div>
            @forelse($members as $member)
                @php
                    $state = $member->accepted_at ? 'joined' : ($member->expires_at?->isFuture() ? 'pending' : 'expired');
                    $displayName = $member->user?->name ?: explode('@', $member->email)[0];
                    $grants = collect($member->permissions)->filter(fn ($value) => in_array($value, ['read', 'write'], true));
                @endphp
                <article class="st-member-entry">
                    <div class="st-member"><span class="st-avatar st-avatar-{{ $loop->index % 3 }}">{{ $initials($displayName) }}</span><div class="st-member-info"><h3>{{ $displayName }}</h3><p>{{ $member->email }}</p><small>{{ trans_choice('seller_team.module_count', $grants->count(), ['count' => $grants->count()]) }}</small></div><span class="st-status st-status-{{ $state }}"><span></span>{{ __('seller_team.'.$state) }}</span><form action="{{ route('admin.seller-team.destroy', $member) }}" method="POST">@csrf @method('DELETE')<button class="st-remove" aria-label="{{ __('seller_team.remove') }}: {{ $member->email }}" title="{{ __('seller_team.remove') }}"><i class="bi bi-trash3" aria-hidden="true"></i></button></form></div>
                    @if($canInvite)
                    <details class="st-member-permissions"><summary><i class="bi bi-sliders2" aria-hidden="true"></i>{{ __('seller_team.edit_permissions') }}<i class="bi bi-chevron-down" aria-hidden="true"></i></summary><form action="{{ route('admin.seller-team.update', $member) }}" method="POST">@csrf @method('PATCH')@include('admin.seller-team.permissions', ['values' => $member->permissions, 'prefix' => 'member-'.$member->id])<div class="st-edit-footer"><button class="st-button st-button-primary">{{ __('Save') }}</button></div></form></details>
                    @endif
                </article>
            @empty
                <div class="st-empty"><div class="st-empty-art" aria-hidden="true"><span><i class="bi bi-person" aria-hidden="true"></i></span><span><i class="bi bi-person-plus" aria-hidden="true"></i></span><span><i class="bi bi-person" aria-hidden="true"></i></span></div><h3>{{ __('seller_team.empty_title') }}</h3><p>{{ __('seller_team.empty') }}</p><a href="#team-invite" data-team-invite class="st-button st-button-secondary"><i class="bi bi-plus-lg" aria-hidden="true"></i>{{ __('seller_team.invite') }}</a></div>
            @endforelse
        </section>

        <aside class="st-aside">
            <section class="st-panel st-capacity"><span class="st-icon"><i class="bi bi-pie-chart" aria-hidden="true"></i></span><h2>{{ __('seller_team.team_capacity') }}</h2><div class="st-seat-total"><strong>{{ $seats }}</strong><span>/ {{ $limit === -1 ? __('Unlimited') : $limit }} {{ __('seller_team.seats') }}</span></div><div class="st-progress" role="progressbar" aria-label="{{ __('seller_team.team_capacity') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"><span style="width: {{ $percent }}%"></span></div><p>{{ __('seller_team.seat_help') }}</p><a href="{{ route('admin.subscription.select') }}" class="st-plan-link">{{ __('package_access.view_packages') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></section>
            <section class="st-guidance"><span class="st-icon st-icon-subtle"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><h3>{{ __('seller_team.access_title') }}</h3><p>{{ __('seller_team.access_help') }}</p><a href="{{ route('seller-workspaces.index') }}">{{ __('seller_team.workspaces') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></section>
        </aside>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-team-invite]').forEach(link => link.addEventListener('click', () => {
    const panel = document.getElementById('team-invite');
    panel.open = true;
    document.getElementById('team-email')?.focus({ preventScroll: true });
}));
document.querySelector('[data-team-copy]')?.addEventListener('click', async function () {
    const input = document.getElementById('team-invitation-link');
    const feedback = document.querySelector('.st-copy-feedback');
    try {
        await navigator.clipboard.writeText(input.value);
        feedback.textContent = this.dataset.copied;
    } catch {
        input.focus();
        input.select();
        let copied = false;
        try { copied = document.execCommand('copy'); } catch {}
        feedback.textContent = copied ? this.dataset.copied : this.dataset.manual;
    }
});
</script>
@endpush
