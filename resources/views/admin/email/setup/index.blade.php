@extends('layouts.app')

@section('title', 'Email Marketing Setup')

<style>
    .setup-step { border-radius: 14px; border: 1px solid rgba(0,0,0,.08); padding: 24px; margin-bottom: 20px; }
    .setup-step.is-done { border-color: #22c55e; background: rgba(34,197,94,.04); }
    .setup-step.is-locked { opacity: .55; pointer-events: none; }
    .setup-step-badge { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; background: #6366f1; color: #fff; margin-right: 10px; }
    .setup-step.is-done .setup-step-badge { background: #22c55e; }
    .dns-table td, .dns-table th { vertical-align: middle; font-size: .85rem; }
    .dns-copy-btn { cursor: pointer; }
</style>

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bx bx-cog"></i> Email Marketing Setup</h4>
    @if ($ready)
        <a href="{{ route('admin.email.campaigns.create') }}" class="btn btn-success">Create Campaign</a>
    @endif
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- STEP 1: SUBACCOUNT --}}
<div class="setup-step {{ $subaccount?->isActive() ? 'is-done' : '' }}">
    <h6><span class="setup-step-badge">1</span> SendGrid Subaccount</h6>
    <p class="text-muted">A dedicated, isolated SendGrid sending account created just for you - separate from every other Socialeaz seller's own email sending.</p>

    @if (!$subaccount)
        <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
            @csrf
            <button class="btn btn-primary">Create My SendGrid Subaccount</button>
        </form>
    @elseif ($subaccount->status === 'provisioning')
        <span class="badge bg-label-info">Provisioning...</span>
    @elseif ($subaccount->status === 'failed')
        <div class="alert alert-danger">{{ $subaccount->error_message }}</div>
        <form method="POST" action="{{ route('admin.email.setup.subaccount') }}">
            @csrf
            <button class="btn btn-primary">Retry</button>
        </form>
    @else
        <span class="badge bg-label-success"><i class="bx bx-check"></i> Active</span>
        <span class="text-muted small">({{ $subaccount->sendgrid_username }})</span>
    @endif
</div>

{{-- STEP 2 + 3: DOMAIN + DNS --}}
<div class="setup-step {{ $domain?->isVerified() ? 'is-done' : '' }} {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
    <h6><span class="setup-step-badge">2</span> Sending Domain &amp; DNS</h6>

    @if (!$domain)
        <form method="POST" action="{{ route('admin.email.setup.domain') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-auto">
                <label class="form-label small">Domain</label>
                <input type="text" name="domain" class="form-control" placeholder="yourdomain.com" required>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Authenticate Domain</button>
            </div>
        </form>
    @else
        <p><strong>{{ $domain->domain }}</strong> -
            @if ($domain->isVerified())
                <span class="badge bg-label-success">Verified</span>
            @else
                <span class="badge bg-label-warning">{{ str_replace('_', ' ', $domain->status) }}</span>
            @endif
        </p>

        @unless ($domain->isVerified())
            <div class="table-responsive">
                <table class="table dns-table">
                    <thead><tr><th>Type</th><th>Host / Name</th><th>Value</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach ($domain->dnsRecords as $record)
                            <tr>
                                <td>{{ strtoupper($record->type) }}</td>
                                <td><code>{{ $record->host }}</code></td>
                                <td><code>{{ $record->data }}</code></td>
                                <td>
                                    @if ($record->valid)
                                        <span class="badge bg-label-success">Verified</span>
                                    @else
                                        <span class="badge bg-label-secondary">Pending</span>
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
                <button class="btn btn-primary">Verify DNS</button>
            </form>
        @endunless
    @endif
</div>

{{-- STEP 4: SENDER --}}
<div class="setup-step {{ $sender?->isVerified() ? 'is-done' : '' }} {{ !$subaccount?->isActive() ? 'is-locked' : '' }}">
    <h6><span class="setup-step-badge">3</span> Sender Identity</h6>

    @if (!$sender)
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
            <div class="col-12"><button class="btn btn-primary mt-2">Create Sender Identity</button></div>
        </form>
    @else
        <p><strong>{{ $sender->from_name }}</strong> &lt;{{ $sender->from_email }}&gt; -
            @if ($sender->isVerified())
                <span class="badge bg-label-success">Verified</span>
            @else
                <span class="badge bg-label-warning">{{ str_replace('_', ' ', $sender->status) }}</span>
            @endif
        </p>

        @unless ($sender->isVerified())
            <form method="POST" action="{{ route('admin.email.setup.sender.refresh', $sender) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary btn-sm">Refresh Status</button>
            </form>
            <form method="POST" action="{{ route('admin.email.setup.sender.resend', $sender) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">Resend Verification Email</button>
            </form>
        @endunless
    @endif
</div>

{{-- STEP 5: READY --}}
<div class="setup-step {{ $ready ? 'is-done' : 'is-locked' }}">
    <h6><span class="setup-step-badge">4</span> Ready</h6>
    @if ($ready)
        <p class="text-success mb-3"><i class="bx bx-check-circle"></i> Email Marketing is fully set up.</p>
        <a href="{{ route('admin.email.campaigns.create') }}" class="btn btn-success me-2">Create Campaign</a>
        <a href="{{ route('admin.email.dashboard') }}" class="btn btn-outline-secondary">Go to Email Dashboard</a>
    @else
        <p class="text-muted mb-0">Complete the steps above to unlock campaign sending.</p>
    @endif
</div>

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
