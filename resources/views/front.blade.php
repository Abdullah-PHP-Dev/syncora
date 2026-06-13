<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Marketplace</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: ui-sans-serif, system-ui; }
    </style>
</head>

<body class="bg-white text-gray-800">

@include('partials.navbar')

<main>
    @yield('content')
</main>

@include('partials.footer')

</body>
</html>