@extends('layouts.app')
@section('title', 'Mis eventos')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mis eventos</h1>
    <a href="{{ route('panel.events.create') }}" class="btn btn-primary">Nuevo evento</a>
</div>
@forelse ($events as $event)
    <a href="{{ route('panel.events.show', $event) }}" class="card mb-2 text-decoration-none text-body">
        <div class="card-body d-flex justify-content-between flex-wrap gap-2">
            <div><strong>{{ $event->name }}</strong><div class="text-muted small">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</div></div>
            <div><span class="badge text-bg-secondary">{{ $event->status->label() }}</span> <span class="badge text-bg-{{ $event->is_public ? 'success' : 'light' }}">{{ $event->is_public ? 'Público' : 'Privado' }}</span></div>
        </div>
    </a>
@empty
    <p class="text-muted">Todavía no has creado ningún evento.</p>
@endforelse
@endsection
