@extends('layouts.app')
@section('title', 'Tatami '.$tatami['number'].' · '.$event->name)
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        @can('manage', $event)
            <a href="{{ route('panel.events.show', $event) }}" class="small">&larr; {{ $event->name }}</a>
        @else
            <a href="{{ route('panel.events.index') }}" class="small">&larr; Mis tatamis</a>
        @endcan
        <h1 class="h3 mb-1">Tatami {{ $tatami['number'] }}</h1>
        <div class="text-muted">{{ $event->name }} · {{ $tatami['categories']->count() }} {{ $tatami['categories']->count() === 1 ? 'categoría' : 'categorías' }}@if ($tatami['categories']->count()), en este orden: {{ $tatami['categories']->pluck('name')->map(fn ($name, $i) => ($i + 1).'. '.$name)->implode(' · ') }}@endif</div>
    </div>
    <a href="{{ route('panel.tatamis.show', [$event, $tatami['number']]) }}" class="btn btn-outline-secondary no-print">Actualizar</a>
</div>

@if ($bout)
    {{-- Navegación entre combates del tatami: se puede volver a un combate ya puntuado para revisarlo. --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 tatami-nav">
        @if ($previous)
            <a href="{{ route('panel.tatamis.show', [$event, $tatami['number'], 'combate' => $previous->id]) }}" class="btn btn-outline-secondary btn-sm">&larr; Combate anterior ({{ $previous->number }})</a>
        @else
            <span></span>
        @endif
        <h2 class="h5 mb-0">{{ $isCurrent ? 'Combate actual' : 'Revisando el combate '.$bout->number }}</h2>
        @if ($following)
            <a href="{{ route('panel.tatamis.show', [$event, $tatami['number'], 'combate' => $following->id]) }}" class="btn btn-outline-secondary btn-sm">Combate siguiente ({{ $following->number }}) &rarr;</a>
        @elseif (! $isCurrent && $tatami['now'])
            <a href="{{ route('panel.tatamis.show', [$event, $tatami['number']]) }}" class="btn btn-primary btn-sm">Volver al combate actual &rarr;</a>
        @else
            <span></span>
        @endif
    </div>
    <div class="mb-4">
        @include('partials.tatami-bout', [
            'bout' => $bout,
            'category' => $bout->category,
            'scoreAction' => route('panel.tatamis.score', [$event, $tatami['number'], $bout]),
        ])
    </div>

    @if ($isCurrent && $tatami['next']->isNotEmpty())
        <h2 class="h6">A continuación</h2>
        <ul class="list-unstyled small">
            @foreach ($tatami['next'] as $next)
                <li>{{ $next->category->name }} · Combate {{ $next->number }}: {{ $next->slotA->competitor->displayName() }} <span class="text-muted">vs</span> {{ $next->slotB->competitor->displayName() }}</li>
            @endforeach
        </ul>
    @endif
@else
    <p class="text-muted">Sin combates en este tatami todavía (sin cuadro o a la espera de resultados).</p>
@endif
@endsection
