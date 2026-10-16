@extends('layouts.app')

@section('title', __('email_marketing.setup.title'))

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .setup-step.is-locked { opacity: .55; pointer-events: none; }
    .socialeaz-dash .dns-table td, .socialeaz-dash .dns-table th { vertical-align: middle; font-size: .85rem; }
    .dns-copy-btn { cursor: pointer; }
    .email-hero .hero-icon-badge {
        width: 52px; height: 52px; border-radius: .9rem; background: rgba(255,255,255,.18);
        display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; flex-shrink: 0;
    }
    .socialeaz-dash .step-badge {
        width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: var(--dash-primary); color: #fff; font-weight: 700; flex-shrink: 0;
    }
    .socialeaz-dash .help-icon-badge {
        width: 30px; height: 30px; border-radius: 50%; background: rgba(124,92,255,.12); color: var(--dash-primary);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
</style>
@endpush

@php
    // Drives both the numbered-circle stepper header and each card's
    // is-done/is-current state from the exact same real infrastructure
    // status this controller already computes - no separate "which step
    // am I on" counter to keep in sync.
    $stepStates = [
        'subaccount' => $subaccount?->isActive() ? 'done' : 'current',
        'domain'     => $domain?->isVerified() ? 'done' : (!$subaccount?->isActive() ? 'locked' : 'current'),
        'sender'     => $senders->contains(fn ($s) => $s->isVerified()) ? 'done' : (!$domain?->isVerified() ? 'locked' : 'current'),
        'ready'      => $ready ? 'done' : 'locked',
    ];
    $completedCount = collect($stepStates)->filter(fn ($s) => $s === 'done')->count();
    $progressPct = ($completedCount / 4) * 100;
@endphp

@section('content')
<div class="socialeaz-dash">

    <div class="email-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="hero-icon-badge"><i class="bx bx-envelope"></i></div>
                <div>
                    <h4 class="mb-1">{{ __('email_marketing.setup.title') }}</h4>
                    <p>{{ __('email_marketing.setup.subtitle') }}</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="hero-progress-card">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @if ($completedCount === 4)
                            <i class="bx bx-check-circle" style="color:#22c55e;font-size:1.1rem;"></i>
                        @else
                            <i class="bx bx-loader-circle" style="color:#6366f1;font-size:1.1rem;"></i>
                        @endif
                        <strong style="font-size:.85rem;">{{ __('email_marketing.setup.steps_completed', ['done' => $completedCount, 'total' => 4]) }}</strong>
                    </div>
                    <div class="progress"><div class="progress-bar" style="width: {{ $progressPct }}%"></div></div>
                </div>
                <a href="{{ route('admin.email.dashboard') }}" class="dash-btn dash-btn-ghost">{{ __('email_marketing.setup.save_exit') }}</a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="dash-card mb-3">
        <div class="dash-stepper">
            @foreach (['subaccount', 'domain', 'sender', 'ready'] as $i => $key)
                @php
                    $number = $i + 1;
                    $title = __("email_marketing.setup.steps.{$key}.title");
                    $desc = __("email_marketing.setup.steps.{$key}.desc");
                @endphp
                <div class="dash-stepper-item {{ $stepStates[$key] === 'done' ? 'is-done' : ($stepStates[$key] === 'current' ? 'is-current' : '') }}">
                    <div class="dash-stepper-circle">
                        @if ($stepStates[$key] === 'done')
                            <i class="bx bx-check"></i>
                        @else
                            {{ $number }}
                        @endif
                    </div>
                    <div class="dash-stepper-text">
                        <span class="dash-stepper-title">{{ $title }}</span>
                        <span class="dash-stepper-desc">{{ $desc }}</span>
                    </div>
                    <span class="dash-stepper-line"></span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- STEP 1: SUBACCOUNT --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="dash-card h-100 setup-step">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="step-badge">1</div>
                    <div>
                        <h6 style="color:var(--dash-heading);margin-bottom:.2rem;">{{ __('email_marketing.setup.connect_title') }}</h6>
                        <p class="dash-subtitle small mb-0">{{ __('email_marketing.setup.connect_text') }}</p>
                    </div>
                </div>

                @if (!$subaccount)
                    <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
                        @csrf
                        <button class="dash-btn dash-btn-primary"><i class="bx bx-link"></i> {{ __('email_marketing.setup.connect_button') }}</button>
                    </form>
                @elseif ($subaccount->status === 'provisioning')
                    <span class="dash-badge dash-badge-info">{{ __('email_marketing.setup.provisioning') }}</span>
                @elseif ($subaccount->status === 'failed')
                    <div class="alert alert-danger">{{ __('email_marketing.setup.connect_failed') }} {{ $subaccount->error_message }}</div>
                    <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
                        @csrf
                        <button class="dash-btn dash-btn-primary">{{ __('email_marketing.common.retry') }}</button>
                    </form>
                @else
                    <div class="dash-status-pill"><span class="dot"></span> {{ __('email_marketing.setup.connected') }}</div>
                    <span class="dash-subtitle small">({{ $subaccount->sendgrid_username }})</span>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dash-card h-100" style="background:var(--dash-card-hover);box-shadow:none;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="help-icon-badge"><i class="bx bx-info-circle"></i></div>
                    <strong style="color:var(--dash-heading);font-size:.85rem;">{{ __('email_marketing.setup.help_title') }}</strong>
                </div>
                <p class="dash-subtitle small">{{ __('email_marketing.setup.help_text') }}</p>
                <a href="https://docs.sendgrid.com/ui/account-and-settings/api-keys" target="_blank" rel="noopener" class="dash-link">{{ __('email_marketing.setup.help_link') }} <i class="bx bx-link-external"></i></a>
            </div>
        </div>
    </div>

    {{-- STEP 2 + 3: DOMAIN + DNS --}}
    <div class="dash-card mb-3 setup-step {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="step-badge">2</div>
            <div>
                <h6 style="color:var(--dash-heading);margin-bottom:.2rem;">{{ __('email_marketing.setup.domain_title') }}</h6>
                <p class="dash-subtitle small mb-0">{{ __('email_marketing.setup.domain_text') }}</p>
            </div>
        </div>

        @if (!$domain)
            <form method="POST" action="{{ route('admin.email.setup.domain') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small">{{ __('email_marketing.setup.domain_label') }}</label>
                    <input type="text" name="domain" class="form-control" placeholder="yourdomain.com" dir="ltr" required>
                </div>
                <div class="col-auto">
                    <button class="dash-btn dash-btn-primary"><i class="bx bx-shield-quarter"></i> {{ __('email_marketing.setup.authenticate_domain') }}</button>
                </div>
            </form>
        @else
            <p style="color:var(--dash-text);"><strong>{{ $domain->domain }}</strong> -
                @if ($domain->isVerified())
                    <span class="dash-badge dash-badge-success">{{ __('email_marketing.statuses.verified') }}</span>
                @else
                    <span class="dash-badge dash-badge-warning">{{ emailLabel('statuses', $domain->status) }}</span>
                @endif
            </p>

            @if ($domain->dns_last_synced_at)
                <p class="dash-subtitle small mb-2"><i class="bx bx-cloud"></i> {{ __('email_marketing.setup.dns_auto_configured', ['when' => $domain->dns_last_synced_at->diffForHumans()]) }}</p>
            @endif

            @unless ($domain->isVerified())
                @if (session('dnsConfigureResults'))
                    <div class="dash-card mb-3" style="background:var(--dash-card-hover);box-shadow:none;">
                        <h6 style="color:var(--dash-heading);font-size:.85rem;">{{ __('email_marketing.setup.dns_result_title') }}</h6>
                        <div class="table-responsive">
                            <table class="dash-table mb-0">
                                <thead><tr><th>{{ __('email_marketing.common.type') }}</th><th>{{ __('email_marketing.setup.hostname') }}</th><th>{{ __('email_marketing.setup.result') }}</th></tr></thead>
                                <tbody>
                                    @foreach (session('dnsConfigureResults') as $r)
                                        <tr>
                                            <td>{{ $r['type'] }}</td>
                                            <td><code>{{ $r['host'] }}</code></td>
                                            <td>
                                                @switch($r['outcome'])
                                                    @case('created')
                                                        <span class="dash-badge dash-badge-success">{{ __('email_marketing.setup.outcomes.created') }}</span>
                                                        @break
                                                    @case('skipped')
                                                        <span class="dash-badge dash-badge-muted">{{ __('email_marketing.setup.outcomes.skipped') }}</span>
                                                        @break
                                                    @case('updated')
                                                        <span class="dash-badge dash-badge-info">{{ __('email_marketing.setup.outcomes.updated', ['reason' => $r['reason'] ?? __('email_marketing.setup.outcomes.updated_default')]) }}</span>
                                                        @break
                                                    @case('conflict')
                                                        <span class="dash-badge dash-badge-danger">{{ __('email_marketing.setup.outcomes.conflict') }}</span>
                                                        <div class="dash-subtitle" style="font-size:.7rem;">{{ __('email_marketing.setup.outcomes.conflict_note') }}</div>
                                                        @break
                                                    @default
                                                        <span class="dash-badge dash-badge-danger">{{ __('email_marketing.setup.outcomes.error') }}</span>
                                                        <div class="dash-subtitle" style="font-size:.7rem;">{{ $r['error'] ?? '' }}</div>
                                                @endswitch
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="dash-table dns-table">
                        <thead><tr><th>{{ __('email_marketing.common.type') }}</th><th>{{ __('email_marketing.setup.host_name') }}</th><th>{{ __('email_marketing.setup.value') }}</th><th>{{ __('email_marketing.common.status') }}</th><th>{{ __('email_marketing.common.action') }}</th></tr></thead>
                        <tbody>
                            @foreach ($domain->dnsRecords as $record)
                                <tr>
                                    <td>{{ strtoupper($record->type) }}</td>
                                    <td><code>{{ $record->host }}</code></td>
                                    <td><code>{{ $record->data }}</code></td>
                                    <td>
                                        @if ($record->valid)
                                            <span class="dash-badge dash-badge-success">{{ __('email_marketing.statuses.verified') }}</span>
                                        @else
                                            <span class="dash-badge dash-badge-muted">{{ __('email_marketing.statuses.pending') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary dns-copy-btn" data-copy="{{ $record->data }}">{{ __('email_marketing.common.copy') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info small mb-3">
                    <strong>{{ __('email_marketing.setup.dmarc_title') }}</strong> {!! __('email_marketing.setup.dmarc_text', ['record' => '<code>' . e('_dmarc.' . $domain->domain) . '</code>']) !!}
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    @if ($cloudflareConfigured)
                        <form method="POST" action="{{ route('admin.email.setup.domain.configureDns', $domain) }}">
                            @csrf
                            <button class="dash-btn dash-btn-ghost"><i class="bx bxl-cloud"></i> {{ __('email_marketing.setup.configure_dns') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.email.setup.domain.verify', $domain) }}">
                        @csrf
                        <button class="dash-btn dash-btn-primary">{{ __('email_marketing.setup.verify_dns') }}</button>
                    </form>
                </div>
            @endunless
        @endif
    </div>

    {{-- STEP 4: SENDER MANAGEMENT --}}
    <div class="dash-card mb-3 setup-step {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
        <div class="dash-card-header">
            <div class="d-flex align-items-start gap-3">
                <div class="step-badge">3</div>
                <div>
                    <h6 style="color:var(--dash-heading);margin-bottom:.2rem;">{{ __('email_marketing.setup.senders_title') }}</h6>
                    <p class="dash-subtitle small mb-0">{{ __('email_marketing.setup.senders_text') }}</p>
                </div>
            </div>
            @if ($senders->isNotEmpty())
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-toggle="modal" data-bs-target="#addSenderModal"><i class="bx bx-plus"></i> {{ __('email_marketing.setup.add_sender') }}</button>
            @endif
        </div>

        @if ($senders->isEmpty())
            <form method="POST" action="{{ route('admin.email.setup.sender') }}" class="row g-3">
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.setup.nickname')]) }}</label><input type="text" name="nickname" class="form-control" placeholder="{{ __('email_marketing.setup.nickname_placeholder') }}" required></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.common.from_name')]) }}</label><input type="text" name="from_name" class="form-control" placeholder="{{ __('email_marketing.setup.from_name_placeholder') }}" required></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.common.from_email')]) }}</label><input type="email" name="from_email" class="form-control" placeholder="noreply@yourdomain.com" required></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.reply_to') }}</label><input type="email" name="reply_to" class="form-control" placeholder="support@yourdomain.com"></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.setup.address')]) }}</label><input type="text" name="address" class="form-control" placeholder="{{ __('email_marketing.setup.address_placeholder') }}" required></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.setup.city')]) }}</label><input type="text" name="city" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.state') }}</label><input type="text" name="state" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.zip') }}</label><input type="text" name="zip" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.required', ['field' => __('email_marketing.setup.country')]) }}</label><input type="text" name="country" class="form-control" required></div>
                @csrf
                <div class="col-12"><button class="dash-btn dash-btn-primary mt-2"><i class="bx bx-plus"></i> {{ __('email_marketing.setup.create_sender') }}</button></div>
            </form>
        @else
            <div class="table-responsive">
                <table class="dash-table">
                    <thead><tr><th>{{ __('email_marketing.common.from_name') }}</th><th>{{ __('email_marketing.common.from_email') }}</th><th>{{ __('email_marketing.common.status') }}</th><th>{{ __('email_marketing.common.created_at') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($senders as $s)
                            <tr>
                                <td>{{ $s->from_name }}</td>
                                <td dir="ltr">{{ $s->from_email }}</td>
                                <td>
                                    @if ($s->isVerified())
                                        <span class="dash-badge dash-badge-success">{{ __('email_marketing.statuses.verified') }}</span>
                                    @elseif ($s->status === 'failed')
                                        <span class="dash-badge dash-badge-danger">{{ __('email_marketing.statuses.failed') }}</span>
                                    @else
                                        <span class="dash-badge dash-badge-warning">{{ __('email_marketing.setup.pending_verification') }}</span>
                                    @endif
                                </td>
                                <td>{{ $s->created_at->translatedFormat('M j, Y') }}</td>
                                <td class="text-end">
                                    @unless ($s->isVerified())
                                        <form method="POST" action="{{ route('admin.email.setup.sender.refresh', $s) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">{{ __('email_marketing.common.refresh') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.email.setup.sender.resend', $s) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">{{ __('email_marketing.common.resend') }}</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- STEP 5: READY --}}
    <div class="dash-card setup-step {{ !$ready ? 'is-locked' : '' }}">
        <div class="d-flex align-items-start gap-3">
            <div class="step-badge">4</div>
            <div class="flex-grow-1">
                <h6 style="color:var(--dash-heading);margin-bottom:.2rem;">{{ __('email_marketing.setup.ready_title') }}</h6>
                @if ($ready)
                    <p class="mb-3" style="color:var(--dash-success);"><i class="bx bx-check-circle"></i> {{ __('email_marketing.setup.ready_text') }}</p>
                    <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary me-2">{{ __('email_marketing.setup.create_campaign') }}</a>
                    <a href="{{ route('admin.email.dashboard') }}" class="dash-btn dash-btn-ghost">{{ __('email_marketing.setup.go_to_module') }}</a>
                @else
                    <p class="dash-subtitle small mb-0">{{ __('email_marketing.setup.ready_locked') }}</p>
                @endif
            </div>
        </div>
    </div>

</div>

@if ($senders->isNotEmpty())
    <div class="modal fade" id="addSenderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.email.setup.sender') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('email_marketing.setup.add_sender_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <div class="col-md-6"><label class="form-label small">{{ __('email_marketing.setup.nickname') }}</label><input type="text" name="nickname" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">{{ __('email_marketing.common.from_name') }}</label><input type="text" name="from_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">{{ __('email_marketing.common.from_email') }}</label><input type="email" name="from_email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">{{ __('email_marketing.setup.reply_to') }}</label><input type="email" name="reply_to" class="form-control"></div>
                        <div class="col-12"><label class="form-label small">{{ __('email_marketing.setup.address') }}</label><input type="text" name="address" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.city') }}</label><input type="text" name="city" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.state') }}</label><input type="text" name="state" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label small">{{ __('email_marketing.setup.zip') }}</label><input type="text" name="zip" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label small">{{ __('email_marketing.setup.country') }}</label><input type="text" name="country" class="form-control" required></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">{{ __('email_marketing.setup.create_sender') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    // Registered on 'load' rather than DOMContentLoaded - this admin
    // layout mounts a bare Vue 2 root on #app with no template, which
    // re-touches the DOM shortly after initial load; listeners attached
    // before that can be silently detached (documented gotcha from the
    // ads campaign builder's own wizard JS).
    window.addEventListener('load', function () {
        document.querySelectorAll('.dns-copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                navigator.clipboard.writeText(btn.dataset.copy);
                var original = btn.textContent;
                btn.textContent = @json(__('email_marketing.common.copied'));
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    });
</script>
@endpush
