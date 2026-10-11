@extends('layouts.app')
@section('title', $event->name)
@section('content')
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
@if ($event->acceptsPublicRegistrations())<a class="btn btn-primary" href="{{ route('public.registration.create', $event) }}">Inscribirse</a>@endif
{{-- Task 13 añade la lista de categorías publicadas. --}}
@endsection
