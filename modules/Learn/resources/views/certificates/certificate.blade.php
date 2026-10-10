{{-- KasiLearn certificate / statement of results - template version 1 (Certificates::TEMPLATE_VERSION). --}}
@extends('pdf.layout')

@section('content')
    <style>
        .eyebrow { text-transform: uppercase; letter-spacing: .12em; color: #24206B; font-weight: 700; margin-top: 8mm; }
        .lead { margin: 4mm 0 1mm; color: #4b4868; }
        .name { font-size: 26pt; margin: 0; color: #1c1a3a; }
        .course { font-size: 17pt; margin: 0 0 3mm; color: #24206B; }
        .note { margin-top: 6mm; padding: 3mm 4mm; border-left: 3px solid #F5B700; background: #fff8e1; font-size: 9.5pt; }
        .signature { margin-top: 10mm; font-weight: 700; }
        .signature span { font-weight: 400; color: #4b4868; }
        .small { font-size: 8.5pt; color: #6b6888; }
    </style>
    <p class="eyebrow">{{ $kind === 'statement' ? 'Statement of results' : 'Certificate of completion' }}</p>
    <p class="lead">This is to confirm that</p>
    <h1 class="name">{{ $name }}</h1>
    <p class="lead">completed the course</p>
    <h2 class="course">{{ $course }}</h2>
    <p>offered by <strong>{{ $provider }}</strong> on {{ \App\Support\Format\SaFormat::date($completed) }}@if ($hours) ({{ rtrim(rtrim((string) $hours, '0'), '.') }} hours) @endif.</p>

    @if ($nqf)
        <p>NQF level {{ $nqf }}@if ($credits) · {{ $credits }} credits @endif · {{ $body }}</p>
    @endif

    @if ($outcomes !== [])
        <h3>What was learned</h3>
        <ul>
            @foreach ($outcomes as $outcome)
                <li>{{ $outcome }}</li>
            @endforeach
        </ul>
    @endif

    @if ($kind === 'statement')
        <p class="note">This statement records the learning completed on KasiHub. The official qualification certificate is issued
            by {{ $body ?? 'the quality council (QCTO) or SETA' }}, not by KasiHub.</p>
    @endif

    @if ($signatory)
        <p class="signature">{{ $signatory }}<br><span>{{ $signatoryTitle }}, {{ $provider }}</span></p>
    @endif
    <p class="small">Course version {{ $version }}.</p>
@endsection
