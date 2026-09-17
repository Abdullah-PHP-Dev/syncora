<div class="row g-4 mb-6">
@foreach(['open' => ['Open tickets', []], 'unassigned' => ['Unassigned tickets', ['assignment' => 'unassigned']], 'urgent' => ['Urgent tickets', ['priority' => 'urgent']], 'mine' => ['Assigned to me', ['assignment' => 'mine']]] as $key => [$label, $filter])
<div class="col-sm-6 col-xl-3"><a href="{{ route('tickets.index', $filter) }}" class="card h-100"><div class="card-body"><span>{{ __($label) }}</span><h3 class="mt-3 mb-0">{{ $ticketCounts[$key] }}</h3></div></a></div>
@endforeach
</div>
<div class="card"><div class="card-header d-flex justify-content-between"><h5 class="mb-0">{{ __('Recent open tickets') }}</h5><a href="{{ route('tickets.index') }}">{{ __('View all') }}</a></div>
<div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Assigned to') }}</th></tr></thead><tbody>
@forelse($recentTickets as $ticket)<tr><td><a href="{{ route('tickets.show', $ticket) }}">#{{ $ticket->id }} {{ $ticket->subject }}</a></td><td>{{ $ticket->customer->name }}</td><td>{{ __('workspace.'.$ticket->priority) }}</td><td>{{ __('workspace.'.$ticket->status) }}</td><td>{{ $ticket->assignee?->name ?? __('Unassigned') }}</td></tr>
@empty<tr><td colspan="5" class="text-center py-6">{{ __('No open tickets.') }}</td></tr>@endforelse
</tbody></table></div></div>
