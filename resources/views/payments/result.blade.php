<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('Payment status') }}</title>
    <style>
        body { margin:0; background:#f5f6fa; color:#182238; font-family:system-ui,sans-serif; }
        main { max-width:560px; margin:12vh auto; padding:32px; background:white; border-radius:16px; }
        h1 { font-size:26px; } p { line-height:1.7; } a { display:inline-block; margin-top:16px; color:#4338ca; }
        @media(max-width:640px) { main { margin:48px 16px; padding:24px; } }
    </style>
</head>
<body>
<main>
    <h1>{{ __('Payment status') }}</h1>
    @if ($status === 'paid')
        <p>{{ __('Payment confirmed. Your subscription is now active.') }}</p>
        <a href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
    @elseif ($status === 'failed')
        <p>{{ __('Your payment was not completed. Please return to your subscription page to try again.') }}</p>
        <a href="{{ route('admin.subscription.plans') }}">{{ __('Return to subscription') }}</a>
    @elseif ($status === 'pending')
        <p>{{ __('Your payment confirmation is processing. Please check again shortly before starting another payment.') }}</p>
        <a href="{{ $refreshUrl }}">{{ __('Check payment status') }}</a>
    @else
        <p>{{ __('This payment return link is incomplete. Please return using the link provided after checkout.') }}</p>
        <a href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
    @endif
</main>
</body>
</html>
