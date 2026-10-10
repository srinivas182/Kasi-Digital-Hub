{{-- KasiLearn learning record - template version 1. --}}
@extends('pdf.layout')

@section('content')
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; font-size: 10pt; }
        th, td { border-bottom: 1px solid #dddbe8; padding: 2mm 1mm; text-align: left; vertical-align: top; }
        th { color: #24206B; }
        .code { font-family: 'DejaVu Sans Mono', monospace; }
    </style>
    <h1>Learning record</h1>
    <p>{{ $name }}</p>
    <table>
        <thead><tr><th>Course</th><th>Provider</th><th>Completed</th><th>Type</th><th>Check code</th></tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['course'] }}</td>
                    <td>{{ $row['provider'] }}</td>
                    <td>{{ \App\Support\Format\SaFormat::date($row['date']) }}</td>
                    <td>{{ $row['kind'] === 'statement' ? 'Statement of results' : 'Certificate of completion' }}</td>
                    <td class="code">{{ $row['code'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
