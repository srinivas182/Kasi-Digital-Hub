<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $business }} - {{ $summary['month'] }}</title>
<style>body{font-family:sans-serif;font-size:12px;color:#222;margin:32px}h1{font-size:18px}table{border-collapse:collapse;width:100%}td{padding:6px;border-bottom:1px solid #ddd}td.n{text-align:right}</style>
</head><body>
<h1>{{ $business }}</h1>
<p>{{ __('biz.pdf.title', ['month' => \Carbon\CarbonImmutable::parse($summary['month'].'-01')->format('F Y')]) }}</p>
<table>
<tr><td>{{ __('biz.summary.in') }}</td><td class="n">R {{ number_format($summary['in'] / 100, 2) }}</td></tr>
<tr><td>{{ __('biz.summary.out') }}</td><td class="n">R {{ number_format($summary['out'] / 100, 2) }}</td></tr>
<tr><td><strong>{{ __('biz.summary.profit') }}</strong></td><td class="n"><strong>R {{ number_format($summary['profit'] / 100, 2) }}</strong></td></tr>
@foreach ($summary['cash'] as $method => $cents)
<tr><td>{{ __('biz.summary.cash_'.$method) }}</td><td class="n">R {{ number_format($cents / 100, 2) }}</td></tr>
@endforeach
</table>
<p style="color:#666;margin-top:24px">{{ __('biz.pdf.note') }}</p>
</body></html>
