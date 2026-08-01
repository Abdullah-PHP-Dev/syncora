@extends('layouts.app')

@section('title', 'Email Campaigns')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bx bx-paper-plane"></i> Campaigns</h4>
    <a href="{{ route('admin.email.campaigns.create') }}" class="btn btn-primary btn-sm"><i class="bx bx-plus"></i> New Campaign</a>
</div>

@if (session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bx bx-check-circle fs-5"></i> {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>List</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Open rate</th>
                    <th>Click rate</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    <tr>
                        <td><a href="{{ route('admin.email.campaigns.show', $campaign) }}">{{ $campaign->name }}</a></td>
                        <td>{{ $campaign->list->name ?? '—' }}</td>
                        <td>
                            <span class="badge text-capitalize bg-label-{{ match($campaign->status) { 'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning', 'failed' => 'danger', default => 'secondary' } }}">{{ $campaign->status }}</span>
                            @if ($campaign->status === 'scheduled')
                                <div class="small text-muted">{{ $campaign->scheduled_at->format('M j, Y H:i') }}</div>
                            @endif
                        </td>
                        <td>{{ $campaign->total_recipients }}</td>
                        <td>{{ $campaign->openRate() }}%</td>
                        <td>{{ $campaign->clickRate() }}%</td>
                        <td class="text-end">
                            @if ($campaign->isEditable())
                                <a href="{{ route('admin.email.campaigns.edit', $campaign) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('admin.email.campaigns.send', $campaign) }}" method="POST" class="d-inline" onsubmit="return confirm('Send this campaign now?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Send Now</button>
                                </form>
                            @else
                                <a href="{{ route('admin.email.campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-secondary">View Stats</a>
                            @endif
                            <form action="{{ route('admin.email.campaigns.destroy', $campaign) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No campaigns yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($campaigns->hasPages())
        <div class="card-footer">{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
