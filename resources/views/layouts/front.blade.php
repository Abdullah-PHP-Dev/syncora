<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>

    <!-- Bootstrap 5.3 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    {{--@vite(['resources/js/app.js'])--}}

    <style>

        .py-6 {
            padding-top: 5rem;
            padding-bottom: 5rem;
        }

        .text-white-75 {
            color: rgba(255,255,255,0.75);
        }

        .hover-lift {
            transition: 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-5px);
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.15;
            filter: blur(40px);
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            background: white;
            top: -80px;
            left: -80px;
            animation: float1 6s ease-in-out infinite;
        }

        .shape-2 {
            width: 250px;
            height: 250px;
            background: #ffffff;
            bottom: -80px;
            right: -80px;
            animation: float2 8s ease-in-out infinite;
        }

        @keyframes float1 {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(30px); }
        }

        @keyframes float2 {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-25px); }
        }
    </style>
</head>

<body>

@include('partials.navbar')

<main>
    @yield('content')
</main>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>


</body>
</html>