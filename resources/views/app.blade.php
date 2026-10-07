<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#24206B">
        <meta name="description" content="{{ config('kasi.brand.tagline') }}">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        <title inertia>{{ config('kasi.brand.name') }}</title>
        {{-- Apply the saved or system theme before first paint (no flash of the wrong theme). --}}
        <script>
            (function () {
                try {
                    var saved = localStorage.getItem('kasi-theme');
                    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) document.documentElement.classList.add('dark');
                } catch (e) {}
            })();
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
