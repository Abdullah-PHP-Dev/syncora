@php($rtl = app()->getLocale() === 'ar')
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · Socialeaz</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F4F5F9;font-family:{{ $rtl ? "Tahoma,'Segoe UI',Arial" : "-apple-system,'Segoe UI',Roboto,Arial" }},sans-serif;color:#161B2B;padding:16px;box-sizing:border-box}
.card{max-width:440px;width:100%;background:#fff;border:1px solid #E7E9F0;border-radius:16px;padding:32px;box-sizing:border-box;text-align:center}
h1{font-size:20px;margin:0 0 10px}p{font-size:14px;line-height:1.7;color:#4B5165;margin:0 0 22px}
a,button{display:inline-block;background:#5B3DF5;color:#fff;border:0;border-radius:9px;padding:11px 22px;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer}
</style>
</head>
<body>
<main class="card">
    <h1>{{ $title }}</h1>
    <p>{{ $body }}</p>
    @if(Route::has('admin.notifications.center'))
        <a href="{{ route('admin.notifications.center') }}">{{ __('notifications.ui.go_to_notifications') }}</a>
    @endif
</main>
</body>
</html>
