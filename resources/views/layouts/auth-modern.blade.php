<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ config('app.name') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg,#0d6efd,#6f42c1);
            min-height: 100vh;
        }

        .auth-card {
            border: 0;
            border-radius: 18px;
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
        }

        .btn {
            border-radius: 10px;
            padding: 12px;
        }

        .logo {
            font-weight: 800;
            font-size: 22px;
            color: #0d6efd;
        }

        .left-panel {
            color: white;
        }
    </style>

</head>

<body>

@yield('content')

</body>
</html>