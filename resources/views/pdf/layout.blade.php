{{-- Shared A4 layout for generated PDFs (S8). Rendered by Gotenberg; no external requests. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Poppins, 'Noto Sans', 'Liberation Sans', Arial, sans-serif; color: #1c1a3a; }
        .page { width: 210mm; min-height: 297mm; padding: 18mm 18mm 14mm; position: relative; }
        .band { position: absolute; inset: 0 0 auto 0; height: 9mm; background: #24206B; }
        .band::after { content: ''; position: absolute; left: 0; bottom: -2mm; width: 60mm; height: 2mm; background: #F5B700; }
        .brand { margin-top: 6mm; font-size: 13pt; font-weight: 700; color: #24206B; letter-spacing: .02em; }
        .muted { color: #5b5878; }
        h1 { font-size: 26pt; margin: 18mm 0 4mm; color: #24206B; }
        .footer { position: absolute; left: 18mm; right: 18mm; bottom: 14mm; display: flex; align-items: flex-end; justify-content: space-between; gap: 10mm; border-top: 1px solid #dddbe8; padding-top: 5mm; font-size: 8.5pt; }
        .qr svg { width: 30mm; height: 30mm; }
        .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 11pt; letter-spacing: .12em; color: #24206B; }
    </style>
    @stack('styles')
</head>
<body>
<div class="page">
    <div class="band"></div>
    <div class="brand">{{ config('kasi.brand.name') }}</div>
    @yield('content')
    <div class="footer">
        <div>
            <div>Issued {{ \App\Support\Format\SaFormat::date($issuedAt) }} by {{ config('kasi.brand.name') }}.</div>
            <div>Check that this document is genuine at <strong>{{ preg_replace('#^https?://#', '', route('verify')) }}</strong> with the code</div>
            <div class="code">{{ $code }}</div>
        </div>
        <div class="qr">{!! $qr !!}</div>
    </div>
</div>
</body>
</html>
