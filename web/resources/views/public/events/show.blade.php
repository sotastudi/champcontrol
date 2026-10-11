@extends('layouts.app')
@section('title', $event->name)
@section('content')
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
{{-- Task 7 añade el enlace de inscripción; Task 13 la lista de categorías publicadas. --}}
@endsection
