<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ $socialMeta['title'] ?? config('jualanyok.name', 'JualanYok') }}</title>

    @isset($socialMeta)
        <meta name="description" content="{{ $socialMeta['description'] }}">
        <link rel="canonical" href="{{ $socialMeta['url'] }}">

        <meta property="og:locale" content="id_ID">
        <meta property="og:site_name" content="{{ config('jualanyok.name', 'JualanYok') }}">
        <meta property="og:type" content="{{ $socialMeta['type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $socialMeta['title'] }}">
        <meta property="og:description" content="{{ $socialMeta['description'] }}">
        <meta property="og:url" content="{{ $socialMeta['url'] }}">

        @if (! empty($socialMeta['image']))
            <meta property="og:image" content="{{ $socialMeta['image'] }}">
            <meta property="og:image:secure_url" content="{{ $socialMeta['image'] }}">
            <meta property="og:image:alt" content="{{ $socialMeta['image_alt'] ?? $socialMeta['title'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $socialMeta['image'] }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif

        <meta name="twitter:title" content="{{ $socialMeta['title'] }}">
        <meta name="twitter:description" content="{{ $socialMeta['description'] }}">

        @if (($socialMeta['type'] ?? null) === 'product')
            <meta property="product:price:amount" content="{{ number_format((float) ($socialMeta['price'] ?? 0), 2, '.', '') }}">
            <meta property="product:price:currency" content="{{ $socialMeta['currency'] ?? 'IDR' }}">
        @endif
    @endisset

    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/images/jualanyok-mark.png">
    <meta name="theme-color" content="#fcfbfe">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700,800|inter:400,500,600,700,800|lora:400,500,600,700|manrope:400,500,600,700,800|nunito:400,500,600,700,800|outfit:400,500,600,700,800|playfair-display:400,500,600,700|plus-jakarta-sans:400,500,600,700,800|poppins:400,500,600,700,800|sora:400,500,600,700,800|space-grotesk:400,500,600,700&display=swap" rel="stylesheet">

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
