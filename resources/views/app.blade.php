<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#24206B">
        @php($seo = $page['props']['seo'] ?? null)
        @if ($seo)
            <meta name="description" content="{{ $seo['description'] }}">
            <link rel="canonical" href="{{ $seo['canonical'] }}">
            @if ($seo['noindex'])
                <meta name="robots" content="noindex">
            @endif
            <meta property="og:site_name" content="{{ config('kasi.brand.full_name') }}">
            <meta property="og:type" content="{{ $seo['type'] }}">
            <meta property="og:title" content="{{ $seo['title'] }}">
            <meta property="og:description" content="{{ $seo['description'] }}">
            <meta property="og:url" content="{{ $seo['canonical'] }}">
            <meta property="og:image" content="{{ $seo['image'] }}">
            <meta property="og:locale" content="en_ZA">
            <meta name="twitter:card" content="summary">
            @foreach ($seo['jsonLd'] as $block)
                <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endforeach
        @else
            <meta name="description" content="{{ config('kasi.brand.tagline') }}">
            <meta name="robots" content="noindex">
        @endif
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        <title inertia>{{ $seo ? $seo['title'].' | '.config('kasi.brand.name') : config('kasi.brand.name') }}</title>
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
