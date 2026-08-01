@extends('layouts.front')

@section('content')
<div class="container py-5" style="max-width:560px;">
    <div class="card shadow-sm">
        <div class="card-body text-center p-5">
            @if (!empty($confirmed))
                <i class="bx bx-check-circle text-success" style="font-size:3rem;"></i>
                <h4 class="mt-3">You're unsubscribed</h4>
                <p class="text-muted">{{ $subscriber->email }} will no longer receive marketing emails from us.</p>
            @elseif ($subscriber->status !== 'subscribed')
                <i class="bx bx-check-circle text-success" style="font-size:3rem;"></i>
                <h4 class="mt-3">Already unsubscribed</h4>
                <p class="text-muted">{{ $subscriber->email }} isn't subscribed to our mailing list.</p>
            @else
                <i class="bx bx-envelope text-primary" style="font-size:3rem;"></i>
                <h4 class="mt-3">Unsubscribe from our mailing list?</h4>
                <p class="text-muted">This will stop marketing emails to <strong>{{ $subscriber->email }}</strong>.</p>
                <form action="{{ route('email.unsubscribe.confirm', $subscriber->unsubscribe_token) }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-danger">Confirm Unsubscribe</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
