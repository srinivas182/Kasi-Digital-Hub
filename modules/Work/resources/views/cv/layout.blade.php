{{-- CV layout (S9). No ID number, photo, date of birth or street address unless the person chose to show their age. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; margin: 14mm 16mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Poppins, 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #1c1a3a; }
        h1 { margin: 0; font-size: 22pt; line-height: 1.15; }
        h2 { font-size: 11pt; text-transform: uppercase; letter-spacing: .08em; margin: 7mm 0 2.5mm; padding-bottom: 1mm; }
        .contact { margin-top: 2mm; color: #4b4868; }
        .item { margin-bottom: 3.5mm; page-break-inside: avoid; }
        .item-head { display: flex; justify-content: space-between; gap: 4mm; }
        .item-title { font-weight: 700; }
        .muted { color: #5b5878; }
        ul { margin: 1mm 0 0; padding-left: 5mm; }
        li { margin-bottom: .6mm; }
        .tags span { display: inline-block; margin: 0 1.5mm 1.5mm 0; padding: .6mm 2.5mm; border-radius: 3mm; }
        .verified { font-size: 8.5pt; font-weight: 700; margin-left: 2mm; white-space: nowrap; }
        .footer { margin-top: 8mm; display: flex; align-items: center; gap: 4mm; font-size: 8pt; color: #5b5878; border-top: 1px solid #dddbe8; padding-top: 3mm; page-break-inside: avoid; }
        .footer svg { width: 18mm; height: 18mm; }
        .code { font-family: 'DejaVu Sans Mono', monospace; letter-spacing: .1em; color: #1c1a3a; }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')
    <div class="footer">
        <div>{!! $qr !!}</div>
        <div>
            Created with {{ config('kasi.brand.name') }} on {{ \App\Support\Format\SaFormat::date($issuedAt) }}.
            Items marked "Verified" were checked against original documents. Check this CV at
            <strong>{{ preg_replace('#^https?://#', '', route('verify')) }}</strong> with code <span class="code">{{ $code }}</span>.
        </div>
    </div>
</body>
</html>
