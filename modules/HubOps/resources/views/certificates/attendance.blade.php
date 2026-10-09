{{-- Certificate of attendance for a hub event. Template version: 1 (bump EventCertificates::TEMPLATE_VERSION when changed). --}}
@extends('pdf.layout')

@push('styles')
<style>
    .lead { font-size: 13pt; line-height: 1.6; margin-top: 10mm; }
    .name { font-size: 22pt; font-weight: 700; margin: 8mm 0; color: #1c1a3a; }
    .event { font-size: 15pt; font-weight: 600; color: #3B34B5; }
    .details { margin-top: 10mm; font-size: 11pt; line-height: 1.8; }
    .details dt { float: left; width: 38mm; color: #5b5878; }
</style>
@endpush

@section('content')
    <h1>Certificate of attendance</h1>
    <p class="lead">This certifies that</p>
    <p class="name">{{ $person }}</p>
    <p class="lead">attended</p>
    <p class="event">{{ $eventTitle }}</p>
    <dl class="details">
        <dt>Type</dt><dd>{{ $eventType }}</dd>
        <dt>Date</dt><dd>{{ $eventDate }}</dd>
        <dt>Hub</dt><dd>{{ $hubName }}{{ $hubPlace ? ', '.$hubPlace : '' }}</dd>
    </dl>
@endsection
