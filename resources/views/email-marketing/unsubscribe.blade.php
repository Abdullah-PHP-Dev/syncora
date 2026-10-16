@extends('layouts.front')

@section('content')
<div class="container py-5" style="max-width:560px;">
    <div class="card shadow-sm">
        <div class="card-body text-center p-5">
            @if (!empty($confirmed))
                <i class="bx bx-check-circle text-success" style="font-size:3rem;"></i>
                <h4 class="mt-3">{{ __('email_marketing.unsubscribe.done_title') }}</h4>
                <p class="text-muted">{{ __('email_marketing.unsubscribe.done_text', ['email' => $subscriber->email]) }}</p>
            @elseif ($subscriber->status !== 'subscribed')
                <i class="bx bx-check-circle text-success" style="font-size:3rem;"></i>
                <h4 class="mt-3">{{ __('email_marketing.unsubscribe.already_title') }}</h4>
                <p class="text-muted">{{ __('email_marketing.unsubscribe.already_text', ['email' => $subscriber->email]) }}</p>
            @else
                <i class="bx bx-envelope text-primary" style="font-size:3rem;"></i>
                <h4 class="mt-3">{{ __('email_marketing.unsubscribe.confirm_title') }}</h4>
                <p class="text-muted">{!! __('email_marketing.unsubscribe.confirm_text', ['email' => '<strong>' . e($subscriber->email) . '</strong>']) !!}</p>
                <form action="{{ route('email.unsubscribe.confirm', $subscriber->unsubscribe_token) }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-danger">{{ __('email_marketing.unsubscribe.confirm_button') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
