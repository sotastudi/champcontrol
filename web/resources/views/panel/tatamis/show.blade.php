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

@if ($tatami['now'])
    @php $bout = $tatami['now']; @endphp
    <h2 class="h5">Combate actual</h2>
    <p class="text-muted small mb-2">{{ $bout->category->name }} · Combate {{ $bout->number }}</p>
    <div style="max-width: 28rem" class="mb-4">
        @include('partials.bout-card', [
            'bout' => $bout,
            'category' => $bout->category,
            'editable' => true,
            'canRecord' => true,
            'showGana' => false,
            'panelOpen' => true,
            'scoreAction' => route('panel.tatamis.score', [$event, $tatami['number'], $bout]),
        ])
    </div>

    @if ($tatami['next']->isNotEmpty())
        <h2 class="h6">A continuación</h2>
        <ul class="list-unstyled small">
            @foreach ($tatami['next'] as $next)
                <li>{{ $next->category->name }} · Combate {{ $next->number }}: {{ $next->slotA->competitor->displayName() }} <span class="text-muted">vs</span> {{ $next->slotB->competitor->displayName() }}</li>
            @endforeach
        </ul>
    @endif
@else
    <p class="text-muted">Sin combates listos en este tatami (sin cuadro, a la espera de resultados o ya finalizado).</p>
@endif
@endsection
