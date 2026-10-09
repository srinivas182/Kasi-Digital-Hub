{{-- Simple (low-ink) CV - prints cheaply on hub printers. Template version 1. --}}
@extends('work::cv.layout')

@push('styles')
<style>
    h2 { border-bottom: 1px solid #1c1a3a; }
    .tags span { border: 1px solid #8a87a8; }
    .verified { color: #1c1a3a; }
    .headline { font-weight: 600; margin-top: 1mm; }
</style>
@endpush

@section('content')
    <h1>{{ $cv['name'] }}</h1>
    @if ($cv['headline'])<div class="headline">{{ $cv['headline'] }}</div>@endif
    <div class="contact">{{ collect([$cv['phone'], $cv['email'], $cv['town'], $cv['age'] ? 'Age '.$cv['age'] : null])->filter()->implode('  ·  ') }}</div>
    @include('work::cv._sections')
@endsection
