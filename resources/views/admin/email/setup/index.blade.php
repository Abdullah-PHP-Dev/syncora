@extends('layouts.app')

@section('title', 'Email Marketing Setup')

@push('styles')
@include('layouts.partials.dash-styles')
<style>
    .socialeaz-dash .setup-step.is-locked { opacity: .55; pointer-events: none; }
    .socialeaz-dash .dns-table td, .socialeaz-dash .dns-table th { vertical-align: middle; font-size: .85rem; }
    .dns-copy-btn { cursor: pointer; }
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
@endphp

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="dash-title mb-0"><i class="bx bx-cog"></i> Email Marketing Setup</h4>
            <p class="dash-subtitle mb-0">Complete the following steps to start sending emails.</p>
        </div>
        @if ($ready)
            <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary">Create Campaign</a>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="dash-card mb-3">
        <div class="dash-stepper">
            @foreach (['subaccount' => ['1', 'Connect SendGrid'], 'domain' => ['2', 'Authenticate Domain'], 'sender' => ['3', 'Verify Sender'], 'ready' => ['4', 'Ready']] as $key => [$number, $label])
                <div class="dash-stepper-item {{ $stepStates[$key] === 'done' ? 'is-done' : ($stepStates[$key] === 'current' ? 'is-current' : '') }}">
                    <div class="dash-stepper-circle">
                        @if ($stepStates[$key] === 'done')
                            <i class="bx bx-check"></i>
                        @else
                            {{ $number }}
                        @endif
                    </div>
                    <span class="dash-stepper-label">{{ $label }}</span>
                    <span class="dash-stepper-line"></span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- STEP 1: SUBACCOUNT --}}
    <div class="dash-card mb-3 setup-step">
        <h6 style="color:var(--dash-heading);"><span class="dash-stepper-circle" style="width:28px;height:28px;font-size:.75rem;display:inline-flex;{{ $subaccount?->isActive() ? 'background:var(--dash-success);border-color:var(--dash-success);color:#fff;' : '' }}">1</span> SendGrid Subaccount</h6>
        <p class="dash-subtitle">A dedicated, isolated SendGrid sending account created just for you - separate from every other Socialeaz seller's own email sending.</p>

        @if (!$subaccount)
            <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
                @csrf
                <button class="dash-btn dash-btn-primary">Create My SendGrid Subaccount</button>
            </form>
        @elseif ($subaccount->status === 'provisioning')
            <span class="dash-badge dash-badge-info">Provisioning...</span>
        @elseif ($subaccount->status === 'failed')
            <div class="alert alert-danger">{{ $subaccount->error_message }}</div>
            <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
                @csrf
                <button class="dash-btn dash-btn-primary">Retry</button>
            </form>
        @else
            <div class="dash-status-pill"><span class="dot"></span> Connected</div>
            <span class="dash-subtitle small">({{ $subaccount->sendgrid_username }})</span>
        @endif
    </div>

    {{-- STEP 2 + 3: DOMAIN + DNS --}}
    <div class="dash-card mb-3 setup-step {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
        <h6 style="color:var(--dash-heading);"><span class="dash-stepper-circle" style="width:28px;height:28px;font-size:.75rem;display:inline-flex;{{ $domain?->isVerified() ? 'background:var(--dash-success);border-color:var(--dash-success);color:#fff;' : '' }}">2</span> Sending Domain &amp; DNS</h6>

        @if (!$domain)
            <form method="POST" action="{{ route('admin.email.setup.domain') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small">Domain</label>
                    <input type="text" name="domain" class="form-control" placeholder="yourdomain.com" required>
                </div>
                <div class="col-auto">
                    <button class="dash-btn dash-btn-primary">Authenticate Domain</button>
                </div>
            </form>
        @else
            <p style="color:var(--dash-text);"><strong>{{ $domain->domain }}</strong> -
                @if ($domain->isVerified())
                    <span class="dash-badge dash-badge-success">Verified</span>
                @else
                    <span class="dash-badge dash-badge-warning">{{ str_replace('_', ' ', $domain->status) }}</span>
                @endif
            </p>

            @unless ($domain->isVerified())
                <div class="table-responsive">
                    <table class="dash-table dns-table">
                        <thead><tr><th>Type</th><th>Host / Name</th><th>Value</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($domain->dnsRecords as $record)
                                <tr>
                                    <td>{{ strtoupper($record->type) }}</td>
                                    <td><code>{{ $record->host }}</code></td>
                                    <td><code>{{ $record->data }}</code></td>
                                    <td>
                                        @if ($record->valid)
                                            <span class="dash-badge dash-badge-success">Verified</span>
                                        @else
                                            <span class="dash-badge dash-badge-muted">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-secondary dns-copy-btn" data-copy="{{ $record->data }}">Copy</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info small mb-3">
                    <strong>DMARC recommended:</strong> SendGrid doesn't generate a DMARC record for you - it's a separate DNS TXT policy record you manage yourself (eg. <code>_dmarc.{{ $domain->domain }}</code>). Optional, but recommended for deliverability.
                </div>

                <form method="POST" action="{{ route('admin.email.setup.domain.verify', $domain) }}">
                    @csrf
                    <button class="dash-btn dash-btn-primary">Verify DNS</button>
                </form>
            @endunless
        @endif
    </div>

    {{-- STEP 4: SENDER MANAGEMENT --}}
    <div class="dash-card mb-3 setup-step {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
        <div class="dash-card-header">
            <h6 style="color:var(--dash-heading);margin:0;"><span class="dash-stepper-circle" style="width:28px;height:28px;font-size:.75rem;display:inline-flex;{{ $senders->contains(fn($s) => $s->isVerified()) ? 'background:var(--dash-success);border-color:var(--dash-success);color:#fff;' : '' }}">3</span> Sender Identities</h6>
            @if ($senders->isNotEmpty())
                <button type="button" class="dash-btn dash-btn-ghost" data-bs-toggle="modal" data-bs-target="#addSenderModal"><i class="bx bx-plus"></i> Add Sender</button>
            @endif
        </div>

        @if ($senders->isEmpty())
            <form method="POST" action="{{ route('admin.email.setup.sender') }}" class="row g-2">
                @csrf
                <div class="col-md-4"><label class="form-label small">Nickname</label><input type="text" name="nickname" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">From Name</label><input type="text" name="from_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">From Email</label><input type="email" name="from_email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">Reply-To (optional)</label><input type="email" name="reply_to" class="form-control"></div>
                <div class="col-md-8"><label class="form-label small">Address</label><input type="text" name="address" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">City</label><input type="text" name="city" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label small">State</label><input type="text" name="state" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small">ZIP</label><input type="text" name="zip" class="form-control"></div>
                <div class="col-md-4"><label class="form-label small">Country</label><input type="text" name="country" class="form-control" required></div>
                <div class="col-12"><button class="dash-btn dash-btn-primary mt-2">Create Sender Identity</button></div>
            </form>
        @else
            <div class="table-responsive">
                <table class="dash-table">
                    <thead><tr><th>From Name</th><th>From Email</th><th>Status</th><th>Created At</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($senders as $s)
                            <tr>
                                <td>{{ $s->from_name }}</td>
                                <td>{{ $s->from_email }}</td>
                                <td>
                                    @if ($s->isVerified())
                                        <span class="dash-badge dash-badge-success">Verified</span>
                                    @elseif ($s->status === 'failed')
                                        <span class="dash-badge dash-badge-danger">Failed</span>
                                    @else
                                        <span class="dash-badge dash-badge-warning">Pending</span>
                                    @endif
                                </td>
                                <td>{{ $s->created_at->format('M j, Y') }}</td>
                                <td class="text-end">
                                    @unless ($s->isVerified())
                                        <form method="POST" action="{{ route('admin.email.setup.sender.refresh', $s) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary">Refresh</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.email.setup.sender.resend', $s) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Resend</button>
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
        <h6 style="color:var(--dash-heading);"><span class="dash-stepper-circle" style="width:28px;height:28px;font-size:.75rem;display:inline-flex;{{ $ready ? 'background:var(--dash-success);border-color:var(--dash-success);color:#fff;' : '' }}">4</span> Ready</h6>
        @if ($ready)
            <p class="mb-3" style="color:var(--dash-success);"><i class="bx bx-check-circle"></i> Email Marketing is fully set up.</p>
            <a href="{{ route('admin.email.campaigns.create') }}" class="dash-btn dash-btn-primary me-2">Create Campaign</a>
            <a href="{{ route('admin.email.dashboard') }}" class="dash-btn dash-btn-ghost">Go to Email Dashboard</a>
        @else
            <p class="dash-subtitle mb-0">Complete the steps above to unlock campaign sending.</p>
        @endif
    </div>

</div>

@if ($senders->isNotEmpty())
    <div class="modal fade" id="addSenderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.email.setup.sender') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Sender Identity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <div class="col-md-6"><label class="form-label small">Nickname</label><input type="text" name="nickname" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">From Name</label><input type="text" name="from_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">From Email</label><input type="email" name="from_email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label small">Reply-To (optional)</label><input type="email" name="reply_to" class="form-control"></div>
                        <div class="col-12"><label class="form-label small">Address</label><input type="text" name="address" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">City</label><input type="text" name="city" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label small">State</label><input type="text" name="state" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label small">ZIP</label><input type="text" name="zip" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label small">Country</label><input type="text" name="country" class="form-control" required></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Create Sender Identity</button>
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
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    });
</script>
@endpush
