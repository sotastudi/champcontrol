@extends('layouts.app')
@section('title', $event->name)
@section('content')
@if ($event->hasPoster())
    <img src="{{ route('public.events.poster', $event) }}" alt="Póster de {{ $event->name }}" class="img-fluid rounded mb-3" style="max-height: 28rem">
@endif
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
@if ($event->acceptsPublicRegistrations())<a class="btn btn-primary" href="{{ route('public.registration.create', $event) }}">Inscribirse</a>@endif
@if ($categories->isNotEmpty())
    <h2 class="h5 mt-4">Cuadros y resultados</h2>
    <div class="list-group">
        @foreach ($categories as $category)
            <a class="list-group-item list-group-item-action" href="{{ route('public.categories.show', [$event, $category]) }}">{{ $category->name }}</a>
        @endforeach
    </div>
@endif
@endsection
