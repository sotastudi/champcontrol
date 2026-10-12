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
        <ul class="list-unstyled small mb-2">
            @foreach ($tatami['next'] as $next)
                @include('panel.tatamis._upcoming-bout')
            @endforeach
        </ul>
    @endif

    {{-- Lista completa del tatami por categorías (jugados y por jugar); cada combate enlaza a su revisión. --}}
    <button class="btn btn-outline-secondary btn-sm no-print mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#tatami-full-list" aria-expanded="false" aria-controls="tatami-full-list">
        Ver lista completa del tatami ({{ $sequence->count() }} {{ $sequence->count() === 1 ? 'combate' : 'combates' }})
    </button>
    <div class="collapse" id="tatami-full-list">
        @foreach ($sequence->groupBy('category_id') as $categoryBouts)
            <div class="card mb-2">
                <div class="card-header py-1 fw-semibold">{{ $categoryBouts->first()->category->name }}</div>
                <ul class="list-unstyled small mb-0 p-2">
                    @foreach ($categoryBouts as $next)
                        @include('panel.tatamis._upcoming-bout', [
                            'showCategory' => false,
                            'reviewUrl' => route('panel.tatamis.show', [$event, $tatami['number'], 'combate' => $next->id]),
                            'status' => $next->isCompleted() ? ['Resuelto', 'success'] : ($tatami['now'] && $tatami['now']->id === $next->id ? ['Actual', 'primary'] : ['Pendiente', 'secondary']),
                        ])
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted">Sin combates en este tatami todavía (sin cuadro o a la espera de resultados).</p>
@endif
@can('manage', $event)
    @include('partials.reset-modal')
@endcan
@endsection
