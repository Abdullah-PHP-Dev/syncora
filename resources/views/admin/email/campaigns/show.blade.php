@extends('layouts.app')

@section('title', $campaign->name)

<style>
    .campaign-stat { border-radius: 12px; border: 1px solid rgba(0,0,0,.06); padding: 16px; text-align: center; }
    .campaign-stat .val { font-size: 1.4rem; font-weight: 700; }
    .campaign-stat .lbl { color: #6b7280; font-size: .78rem; }
</style>

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="{{ route('admin.email.campaigns.index') }}" class="small text-muted"><i class="bx bx-arrow-back"></i> Campaigns</a>
        <h4 class="mb-0">{{ $campaign->name }}</h4>
        <span class="badge text-capitalize bg-label-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'secondary' } }}">{{ $campaign->status }}</span>
    </div>
    @if ($campaign->isEditable())
        <div class="d-flex gap-2">
            <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <form action="{{ route('admin.email.campaigns.send', $campaign) }}" method="POST" onsubmit="return confirm('Send this campaign now?');">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Send Now</button>
            </form>
        </div>
    @endif
</div>

@if ($campaign->error_message)
    <div class="alert alert-danger">{{ $campaign->error_message }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->total_recipients }}</div><div class="lbl">Recipients</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->sent_count }}</div><div class="lbl">Sent</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->delivered_count }}</div><div class="lbl">Delivered</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->openRate() }}%</div><div class="lbl">Opened ({{ $campaign->opened_count }})</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->clickRate() }}%</div><div class="lbl">Clicked ({{ $campaign->clicked_count }})</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->bounced_count }}</div><div class="lbl">Bounced</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->complained_count }}</div><div class="lbl">Complained</div></div></div>
    <div class="col-md-3 col-6"><div class="campaign-stat bg-white"><div class="val">{{ $campaign->failed_count }}</div><div class="lbl">Failed</div></div></div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Recipients</h6></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Opened</th>
                    <th>Clicked</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sends as $send)
                    <tr>
                        <td>{{ $send->subscriber->email ?? '—' }}</td>
                        <td><span class="badge text-capitalize bg-label-{{ in_array($send->status, ['bounced','complained','failed']) ? 'danger' : ($send->status === 'unsubscribed' ? 'secondary' : 'success') }}">{{ $send->status }}</span></td>
                        <td>{{ $send->sent_at?->format('M j, H:i') ?? '—' }}</td>
                        <td>{{ $send->opened_at?->format('M j, H:i') ?? '—' }}</td>
                        <td>{{ $send->clicked_at?->format('M j, H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No sends yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($sends->hasPages())
        <div class="card-footer">{{ $sends->links() }}</div>
    @endif
</div>
@endsection
