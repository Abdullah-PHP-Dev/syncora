<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-200">

<div class="flex">

    @include('partials.sidebar')

    <div class="flex-1">

        @include('partials.navbar')

        <main class="p-6">
            @yield('content')
        </main>

    </div>

</div>

</body>
</html>