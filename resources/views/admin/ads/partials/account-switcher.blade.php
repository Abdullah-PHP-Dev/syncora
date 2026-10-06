{{--
    Which ad account this campaign is created in, with a switcher when the
    seller has several (App\Support\AdAccountSelection - the services use
    the same selection on save). "Connect another" returns here after OAuth
    (App\Http\Middleware\ReturnToOrigin) with the new account preselected.

    Expects: $platform, $account (?SocialAccount), $adAccounts (Collection).
--}}
@php
    $adAccounts = $adAccounts ?? collect();
    $platformName = ['x' => 'X', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'][$platform] ?? ucfirst($platform);
    $connectUrl = \App\Support\Connections\HubLink::for($platform)
        ?? route('admin.ads.redirect', $platform === 'youtube' ? 'google' : $platform) . '?' . http_build_query(['return_to' => request()->getRequestUri()]);
    $switchUrl = fn ($id) => request()->fullUrlWithQuery(['account' => $id, 'connected' => null]);
@endphp
<div class="ads-acct">
    @if ($account)
        <span class="ads-acct-icon"><i class="bx bx-briefcase-alt-2"></i></span>
        <div class="ads-acct-text">
            <small>{{ $platformName }} ad account</small>
            <strong title="{{ $account->name }}">{{ $account->name ?: $platformName . ' ad account' }}</strong>
        </div>
        @if ($adAccounts->count() > 1)
            <div class="dropdown ms-auto">
                <button type="button" class="ads-acct-btn" data-bs-toggle="dropdown" aria-expanded="false"><i class="bx bx-transfer-alt"></i> Switch <i class="bx bx-chevron-down"></i></button>
                <ul class="dropdown-menu dropdown-menu-end ads-acct-menu">
                    @foreach ($adAccounts as $option)
                        <li>
                            <a class="dropdown-item {{ $option->id === $account->id ? 'active' : '' }}" href="{{ $switchUrl($option->id) }}" data-ads-acct-switch>
                                <i class="bx {{ $option->id === $account->id ? 'bx-check' : 'bx-briefcase-alt-2' }}"></i>
                                <span>{{ $option->name ?: $platformName . ' ad account #' . $option->id }}</span>
                            </a>
                        </li>
                    @endforeach
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ $connectUrl }}"><i class="bx bx-plus"></i> <span>Connect another account</span></a></li>
                </ul>
            </div>
        @else
            <a href="{{ $connectUrl }}" class="ads-acct-btn ms-auto"><i class="bx bx-plus"></i> Connect another</a>
        @endif
    @else
        <span class="ads-acct-icon is-warn"><i class="bx bx-error"></i></span>
        <div class="ads-acct-text">
            <strong>No {{ $platformName }} advertising account is connected</strong>
            <small>Connect one to create this campaign - you'll come straight back here.</small>
        </div>
        <a href="{{ $connectUrl }}" class="ads-acct-btn is-primary ms-auto"><i class="bx bx-link"></i> Connect {{ $platformName }}</a>
    @endif
</div>
@include('admin.ads.partials.promote-banner')
{{-- In the head stack: inline <style> inside the Vue #app root is stripped by Vue's template compiler. --}}
@once
@push('styles')
<style>
    .ads-acct { display: flex; align-items: center; gap: 12px; padding: 12px 16px; margin-bottom: 16px; background: #fff; border: 1px solid #e7e9f0; border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .ads-acct-icon { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: 19px; background: #f2eeff; color: #6d4aff; flex-shrink: 0; }
    .ads-acct-icon.is-warn { background: #fffaeb; color: #b54708; }
    .ads-acct-text { min-width: 0; line-height: 1.3; }
    .ads-acct-text small { display: block; font-size: .72rem; color: #8a92a3; }
    .ads-acct-text strong { display: block; color: #161b2b; font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ads-acct-btn { display: inline-flex; align-items: center; gap: 5px; height: 36px; padding: 0 12px; border-radius: 9px; border: 1px solid #e7e9f0; background: #fff; color: #161b2b; font-size: .8rem; font-weight: 600; text-decoration: none; white-space: nowrap; }
    .ads-acct-btn:hover { background: #fafbfd; border-color: #cfd4de; color: #161b2b; }
    .ads-acct-btn.is-primary { background: linear-gradient(135deg, #6d4aff, #8f6bff); border-color: transparent; color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
    .ads-acct-menu { min-width: 260px; border-radius: 12px; padding: 6px; }
    .ads-acct-menu .dropdown-item { display: flex; align-items: center; gap: 8px; border-radius: 8px; font-size: .84rem; }
    .ads-acct-menu .dropdown-item.active { background: #f2eeff; color: #4f2fd6; }
</style>
@endpush
@push('scripts')
<script>
    // Unsaved-changes guard for campaign creation: only after the user
    // actually types/changes something (trusted events - the Promote
    // prefill doesn't count), cleared when the form is submitted.
    (function () {
        let dirty = false;
        const inForm = (el) => el && el.closest && el.closest('form') && !el.closest('.ads-acct, .ads-promote');
        document.addEventListener('input', (e) => { if (e.isTrusted && inForm(e.target)) dirty = true; }, true);
        document.addEventListener('change', (e) => { if (e.isTrusted && inForm(e.target)) dirty = true; }, true);
        document.addEventListener('submit', () => { dirty = false; }, true);
        window.addEventListener('beforeunload', (e) => {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    })();
</script>
@endpush
@endonce
