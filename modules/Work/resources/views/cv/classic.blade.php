{{-- Classic CV - template version 1 (bump WorkCv::TEMPLATE_VERSION when changed). --}}
@extends('work::cv.layout')

@push('styles')
<style>
    .top { background: #24206B; color: #fff; margin: -14mm -16mm 6mm; padding: 12mm 16mm 8mm; }
    .top .contact { color: #e2e0ff; }
    .headline { color: #F5B700; font-weight: 600; margin-top: 1mm; }
    h2 { color: #24206B; border-bottom: 2px solid #F5B700; }
    .tags span { background: #eeedfb; }
    .verified { color: #0E9F8A; }
</style>
@endpush

@section('content')
    <div class="top">
        <h1>{{ $cv['name'] }}</h1>
        @if ($cv['headline'])<div class="headline">{{ $cv['headline'] }}</div>@endif
        <div class="contact">{{ collect([$cv['phone'], $cv['email'], $cv['town'], $cv['age'] ? 'Age '.$cv['age'] : null])->filter()->implode('  ·  ') }}</div>
    </div>
    @include('work::cv._sections')
@endsection
