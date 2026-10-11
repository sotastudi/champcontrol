@extends('layouts.app')
@section('title', $event->name)
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $event->name }}</h1>
        <div class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</div>
        <div class="mt-2">
            <span class="badge text-bg-secondary">{{ $event->status->label() }}</span>
            <span class="badge text-bg-{{ $event->is_public ? 'success' : 'light' }}">{{ $event->is_public ? 'Público' : 'Privado' }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('panel.events.edit', $event) }}" class="btn btn-outline-primary">Editar</a>
        @if ($event->isListed())
            <a href="{{ route('public.events.show', $event) }}" class="btn btn-outline-secondary">Ver página pública</a>
        @endif
        <form method="POST" action="{{ route('panel.events.destroy', $event) }}" onsubmit="return confirm('¿Eliminar el evento y todos sus datos?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Eliminar</button>
        </form>
    </div>
</div>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif

<section id="categorias">
    {{-- Las Tasks 5 y 6 añaden aquí categorías e inscripciones. --}}
</section>
@endsection
