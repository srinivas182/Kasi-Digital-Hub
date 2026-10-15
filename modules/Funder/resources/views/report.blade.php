{{-- Funder impact report - template version 1 (S20). Aggregates only; figures below 10 shown as "<10". --}}
@extends('pdf.layout')

@section('content')
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 4mm; font-size: 10pt; }
        th, td { border-bottom: 1px solid #dddbe8; padding: 2mm 1mm; text-align: left; }
        td.r, th.r { text-align: right; }
        .note { font-size: 9pt; color: #4b4868; margin-top: 4mm; }
    </style>
    <h1>{{ $programme->name }}</h1>
    <p>Impact report for {{ $period }}@if ($provisional) (provisional - final figures are frozen on the 5th of next month) @endif</p>
    <table>
        <thead><tr><th>Indicator</th><th class="r">This month</th><th class="r">Since start</th><th class="r">Target</th><th class="r">Progress</th></tr></thead>
        <tbody>
            @foreach ($progress as $key => $p)
                @php($fmt = fn ($v) => is_string($v) ? $v : (($units[$key] ?? 'count') === 'cents' ? 'R'.number_format($v / 100, 0, '.', ' ') : number_format($v, 0, '.', ' ')))
                <tr>
                    <td>{{ __('insights.indicator.'.$key) }}</td>
                    <td class="r">{{ $fmt($month[$key] ?? 0) }}</td>
                    <td class="r">{{ $fmt($p['value']) }}</td>
                    <td class="r">{{ $fmt($p['target']) }}</td>
                    <td class="r">{{ is_string($p['value']) ? '-' : $p['percent'].'%' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="note">Figures are aggregates for the programme's hubs and age group ({{ $programme->age_min }}-{{ $programme->age_max }}). To protect privacy, any figure from 1 to 9 is shown as "&lt;10". Definitions of every indicator are in the funder portal.</p>
@endsection
