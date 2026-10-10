{{-- One-page business summary (verifiable) - template version 1. --}}
@extends('pdf.layout')

@section('content')
    <style>
        h1 { margin-bottom: 1mm; }
        .meta { color: #4b4868; margin: 0 0 4mm; }
        h3 { color: #24206B; margin: 4mm 0 1mm; font-size: 11pt; }
        p { margin: 0 0 2mm; }
        .grid { display: flex; gap: 6mm; margin-top: 3mm; }
        .grid div { flex: 1; border: 1px solid #dddbe8; border-radius: 2mm; padding: 2mm 3mm; font-size: 9.5pt; }
    </style>
    <h1>{{ $business->name }}</h1>
    <p class="meta">{{ $business->sells }} · {{ $business->place_name }} · {{ $business->people }} {{ $business->people === 1 ? 'person' : 'people' }} working</p>

    @foreach (['problem' => 'The need we meet', 'offer' => 'What we sell', 'pricing' => 'Prices and costs', 'marketing' => 'How we find customers', 'money' => 'The money we need', 'next_steps' => 'Our next 3 months'] as $key => $label)
        @if (trim((string) ($plan['sections'][$key] ?? '')) !== '')
            <h3>{{ $label }}</h3>
            <p>{{ $plan['sections'][$key] }}</p>
        @endif
    @endforeach

    <div class="grid">
        @if ($calc['price'] !== null)
            <div><strong>Price per item:</strong> R{{ number_format($calc['price'], 2, '.', ' ') }}@if ($calc['profit_per_item'] !== null)<br>Profit per item: R{{ number_format($calc['profit_per_item'], 2, '.', ' ') }} @endif
                @if ($calc['break_even_items'] !== null)<br>Items a month to cover fixed costs: {{ $calc['break_even_items'] }} @endif</div>
        @endif
        <div><strong>Formalisation:</strong> {{ $steps === [] ? 'not started' : implode(', ', $steps) }}<br><strong>Readiness:</strong> {{ $readiness['score'] }}/100</div>
    </div>
@endsection
