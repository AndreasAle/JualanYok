<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('jualanyok.name', 'JualanYok') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#fcfbfe">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet">

    {{-- JualanYok uses one consistent light visual direction across public,
         creator, member, affiliate, and admin workspaces. --}}
    <script>
        (function () {
            try {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
                localStorage.setItem('jy-theme', 'light');
            } catch (e) {}
        })();
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-app text-fg antialiased">
    @inertia
</body>
</html>
