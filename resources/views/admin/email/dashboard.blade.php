@extends('layouts.app')

@section('title', 'Email Marketing')

<style>
    .email-hero {
        background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 55%, #8b5cf6 100%);
        border-radius: 20px;
        padding: 32px 36px;
        color: #fff;
        margin-bottom: 24px;
    }

    .email-hero h4 { color: #fff; font-weight: 700; margin-bottom: 6px; }
    .email-hero p { color: rgba(255,255,255,.85); margin-bottom: 0; max-width: 560px; }

    .email-stat-card {
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.06);
        padding: 20px;
        height: 100%;
    }

    .email-stat-card .stat-value { font-size: 1.6rem; font-weight: 700; }
    .email-stat-card .stat-label { color: #6b7280; font-size: .82rem; }

    .email-quick-links a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid rgba(0,0,0,.06);
        color: inherit;
        text-decoration: none;
        margin-bottom: 10px;
        transition: all .15s ease;
    }

    .email-quick-links a:hover { border-color: #6366f1; background: rgba(99,102,241,.06); }

    .campaign-status-badge { text-transform: capitalize; }
</style>

@section('content')
<div class="email-hero">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bx bx-envelope-open"></i> Email Marketing</h4>
            <p>Build lists, design campaigns, and track opens/clicks/bounces through your Mailgun sending domain.</p>
        </div>
        <a href="{{ route('admin.email.campaigns.create') }}" class="btn btn-light fw-semibold"><i class="bx bx-plus"></i> New Campaign</a>
    </div>
</div>

@if (!$isConfigured)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bx bx-error-circle fs-5"></i>
        Mailgun isn't configured yet, so campaigns can't send. Add <code>email_marketing.mailgun.domain</code> and <code>email_marketing.mailgun.secret</code> under <a href="{{ route('admin.apis.index') }}">Admin &gt; APIs</a>.
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="email-stat-card bg-white">
            <div class="stat-value">{{ number_format($totalSubscribers) }}</div>
            <div class="stat-label">Subscribed contacts</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="email-stat-card bg-white">
            <div class="stat-value">{{ number_format($totalLists) }}</div>
            <div class="stat-label">Lists</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="email-stat-card bg-white">
            <div class="stat-value">{{ number_format($totalSent) }}</div>
            <div class="stat-label">Campaigns sent</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="email-stat-card bg-white">
            <div class="stat-value">{{ $avgOpenRate }}%</div>
            <div class="stat-label">Avg. open rate</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Campaigns</h6>
                <a href="{{ route('admin.email.campaigns.index') }}" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>List</th>
                            <th>Status</th>
                            <th>Recipients</th>
                            <th>Open rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCampaigns as $campaign)
                            <tr>
                                <td><a href="{{ route('admin.email.campaigns.show', $campaign) }}">{{ $campaign->name }}</a></td>
                                <td>{{ $campaign->list->name ?? '—' }}</td>
                                <td><span class="badge campaign-status-badge bg-label-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'secondary' } }}">{{ $campaign->status }}</span></td>
                                <td>{{ $campaign->total_recipients }}</td>
                                <td>{{ $campaign->openRate() }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No campaigns yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card p-3">
            <h6 class="mb-3">Quick Links</h6>
            <div class="email-quick-links">
                <a href="{{ route('admin.email.lists.index') }}"><i class="bx bx-list-ul text-primary"></i> Manage Lists</a>
                <a href="{{ route('admin.email.templates.index') }}"><i class="bx bx-file text-primary"></i> Email Templates</a>
                <a href="{{ route('admin.email.campaigns.index') }}"><i class="bx bx-paper-plane text-primary"></i> All Campaigns</a>
            </div>
        </div>
    </div>
</div>
@endsection
