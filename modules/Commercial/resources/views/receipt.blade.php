{{-- Tax invoice / receipt - template version 1 (S19). --}}
@extends('pdf.layout')

@section('content')
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; font-size: 10pt; }
        th, td { border-bottom: 1px solid #dddbe8; padding: 2mm 1mm; text-align: left; }
        td.r, th.r { text-align: right; }
        .meta { color: #4b4868; }
    </style>
    <h1>{{ $order->vat_cents > 0 ? 'Tax invoice' : 'Receipt' }} {{ $order->number }}</h1>
    <p class="meta">Seller: {{ $seller }}@if ($sellerVat) · VAT no. {{ $sellerVat }} @endif</p>
    <p class="meta">Buyer: {{ $buyer }} · {{ \App\Support\Format\SaFormat::date($order->paid_at) }}</p>
    <table>
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $product->name }}</td>
                <td class="r">{{ $order->quantity }}</td>
                <td class="r">{{ \Modules\Commercial\Services\Money::format($order->unit_price_cents) }}</td>
                <td class="r">{{ \Modules\Commercial\Services\Money::format($order->total_cents) }}</td>
            </tr>
        </tbody>
        <tfoot>
            @if ($order->vat_cents > 0)
                <tr><td colspan="3" class="r">Amount excluding VAT</td><td class="r">{{ \Modules\Commercial\Services\Money::format($order->total_cents - $order->vat_cents) }}</td></tr>
                <tr><td colspan="3" class="r">VAT (15%)</td><td class="r">{{ \Modules\Commercial\Services\Money::format($order->vat_cents) }}</td></tr>
            @endif
            <tr><th colspan="3" class="r">Total (paid)</th><th class="r">{{ \Modules\Commercial\Services\Money::format($order->total_cents) }}</th></tr>
            @if ($order->refunded_cents > 0)
                <tr><td colspan="3" class="r">Refunded</td><td class="r">-{{ \Modules\Commercial\Services\Money::format($order->refunded_cents) }}</td></tr>
            @endif
        </tfoot>
    </table>
@endsection
