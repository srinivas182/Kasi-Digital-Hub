{{-- Certificate of volunteer mentoring hours - template version 1. --}}
@extends('pdf.layout')

@section('content')
    <style>
        .eyebrow { text-transform: uppercase; letter-spacing: .12em; color: #24206B; font-weight: 700; margin-top: 8mm; }
        .name { font-size: 24pt; margin: 2mm 0; }
        .hours { font-size: 18pt; color: #24206B; margin: 4mm 0; }
    </style>
    <p class="eyebrow">Certificate of volunteer mentoring</p>
    <p>This is to thank and confirm that</p>
    <h1 class="name">{{ $name }}</h1>
    <p>has volunteered as a vetted KasiConnect mentor since {{ \App\Support\Format\SaFormat::date($since) }}, giving</p>
    <p class="hours">{{ rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.') }} hours of mentoring</p>
    <p>to {{ $mentees }} young {{ $mentees === 1 ? 'person' : 'people' }} one-to-one, and to young people in group sessions at Kasi Digital Hubs.</p>
@endsection
