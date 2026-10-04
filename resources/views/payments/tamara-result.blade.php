<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Tamara payment') }}</title></head>
<body>
<main style="max-width:640px;margin:64px auto;padding:24px;font-family:system-ui">
    <h1>{{ __('Tamara payment') }}</h1>
    @if ($order->paid_at)
        <p>{{ __('Payment confirmed. Your subscription is now active.') }}</p>
        <a href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
    @elseif (in_array($order->status, ['declined', 'expired', 'canceled', 'cancelled', 'failed']) || in_array($result, ['cancel', 'failure']))
        <p>{{ __('Your payment was not completed. If you approved payment, confirmation may still be processing; refresh before starting another payment.') }}</p>
        <a href="{{ route('admin.subscription.checkout', ['plan_id' => $order->bundle_id, 'cycle' => $order->cycle]) }}">{{ __('Return to checkout') }}</a>
    @else
        <p>{{ __('Your payment confirmation is processing. Refresh this page shortly to check its status.') }}</p>
    @endif
</main>
</body>
</html>
