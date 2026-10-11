@extends('layouts.app')
@section('content')
    <h1 class="h3">Campeonatos de artes marciales</h1>
    <p class="text-muted">Consulta los eventos publicados o accede como organizador.</p>
    @forelse ($events as $event)
        <a href="{{ route('public.events.show', $event) }}" class="card mb-2 text-decoration-none text-body">
            <div class="card-body">
                <strong>{{ $event->name }}</strong>
                <div class="text-muted small">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</div>
            </div>
        </a>
    @empty
        <p class="text-muted">No hay eventos publicados.</p>
    @endforelse
@endsection
