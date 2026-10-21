@php
    $k = $s['kit'];
    $fonts = ['modern' => 'system-ui, sans-serif', 'friendly' => '"Trebuchet MS", system-ui, sans-serif', 'classic' => 'Georgia, "Times New Roman", serif'];
    $desc = $s['kit']['tagline'] ?? \Illuminate\Support\Str::limit((string) $s['about'], 150);
    $hours = $s['shop']['hours'] ?? [];
    $base = $url;
    $ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $s['name'], 'url' => $url, 'description' => $desc];
    if (($s['shop']['area'] ?? null) !== null) { $ld['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $s['shop']['area'], 'addressCountry' => 'ZA']; }
    if ($s['rating'] !== null) { $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $s['rating'], 'bestRating' => 5]; }
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $s['name'] }}{{ $s['kit']['tagline'] ? ' - '.$s['kit']['tagline'] : '' }}</title>
<meta name="description" content="{{ $desc }}">
<link rel="canonical" href="{{ $url }}">
<meta property="og:type" content="business.business">
<meta property="og:title" content="{{ $s['name'] }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $url }}">
@if (count($s['photos']) > 0)<meta property="og:image" content="{{ $s['photos'][0]['url'] }}">@endif
<meta name="theme-color" content="{{ $k['primary'] }}">
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<style>
*{box-sizing:border-box}body{margin:0;font-family:{!! $fonts[$k['lettering']] ?? $fonts['modern'] !!};color:#1a1a1a;background:#fff;line-height:1.5}
header{background:{{ $k['primary'] }};color:{{ $s['on_primary'] }};padding:28px 20px 24px}header .in,main,footer .in{max-width:720px;margin:0 auto}
.logo{width:72px;height:72px;float:right;border-radius:8px;object-fit:contain;background:transparent}
h1{margin:0 0 6px;font-size:1.9rem;line-height:1.2}.tag{margin:0;opacity:.92}.band{height:6px;background:{{ $k['secondary'] }}}
main{padding:20px}h2{font-size:1.2rem;margin:26px 0 10px;color:{{ $k['primary'] }}}
.btns{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}.btn{display:inline-block;padding:12px 18px;border-radius:8px;font-weight:700;text-decoration:none}
.btn.p{background:{{ $k['primary'] }};color:{{ $s['on_primary'] }}}.btn.s{border:2px solid {{ $k['primary'] }};color:{{ $k['primary'] }}}
ul.items{list-style:none;padding:0;margin:0}ul.items li{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e6e6e6;padding:10px 0}
.price{font-weight:700;white-space:nowrap}.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
.gal img{width:100%;height:150px;object-fit:cover;border-radius:8px}.muted{color:#555}
footer{background:#f6f6f6;padding:18px 20px;font-size:.85rem;color:#555;margin-top:30px}footer a{color:#555}
a:focus-visible{outline:3px solid {{ $k['secondary'] }};outline-offset:2px}
</style>
</head>
<body>
<header><div class="in">
@if ($k['logo_kind'] === 'upload' && $k['logo_url'])<img class="logo" src="{{ $k['logo_url'] }}" alt="{{ $s['name'] }} logo" width="72" height="72">
@else<img class="logo" src="data:image/svg+xml;base64,{{ base64_encode($s['logo_svg']) }}" alt="{{ $s['name'] }} logo" width="72" height="72">@endif
<h1>{{ $s['name'] }}</h1>
@if ($k['tagline'])<p class="tag">{{ $k['tagline'] }}</p>@endif
@if ($s['rating'] !== null)<p class="tag">{{ __('brand.public.rating', ['rating' => $s['rating']]) }}</p>@endif
</div></header>
<div class="band"></div>
<main>
<div class="btns">
@if (($s['shop']['whatsapp'] ?? null) !== null)<a class="btn p" href="{{ $base }}?tap=whatsapp" rel="nofollow">{{ __('brand.public.whatsapp') }}</a>@endif
@if (($s['shop']['url'] ?? null) !== null)<a class="btn s" href="{{ $base }}?tap=order" rel="nofollow">{{ __('brand.public.order') }}</a>@endif
@if (($s['shop']['area'] ?? null) !== null)<a class="btn s" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($s['name'].' '.$s['shop']['area']) }}" rel="nofollow noopener">{{ __('brand.public.directions') }}</a>@endif
</div>
@if ($s['about'])<h2>{{ __('brand.public.about') }}</h2><p>{!! nl2br(e($s['about'])) !!}</p>@endif
@if (count($s['services']) > 0 || count($s['typed']) > 0)
<h2>{{ __('brand.public.offer') }}</h2>
<ul class="items">
@foreach ($s['services'] as $p)<li><span>{{ $p['title'] }}</span><span class="price">R{{ number_format($p['price_cents'] / 100, 2, '.', ' ') }}</span></li>@endforeach
@foreach ($s['typed'] as $p)<li><span>{{ $p['name'] }}</span><span class="price">{{ $p['price'] ?? '' }}</span></li>@endforeach
</ul>
@endif
@if (count($hours) > 0 || ($s['shop']['area'] ?? null) !== null)
<h2>{{ __('brand.public.find_us') }}</h2>
@if (($s['shop']['area'] ?? null) !== null)<p>{{ $s['shop']['area'] }}</p>@endif
@if (count($hours) > 0)<ul class="items">@foreach ($hours as $day => $time)<li><span>{{ __('market.day.'.$day) }}</span><span>{{ $time }}</span></li>@endforeach</ul>@endif
@endif
@if (count($s['photos']) > 0)
<h2>{{ __('brand.public.photos') }}</h2>
<div class="gal">@foreach ($s['photos'] as $ph)<img src="{{ $ph['url'] }}" alt="{{ $ph['alt'] }}" loading="lazy" width="300" height="150">@endforeach</div>
@endif
</main>
<footer><div class="in">
<p>&copy; {{ now()->year }} {{ $s['name'] }} &middot; <a href="{{ url('/help') }}">{{ __('brand.public.report') }}</a></p>
</div></footer>
</body>
</html>
