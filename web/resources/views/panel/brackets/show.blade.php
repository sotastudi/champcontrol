@extends('layouts.app')
@section('title', 'Cuadro · '.$category->name)
@section('content')
@php
    $participants = $bouts->where('round', 1)->where('is_bronze', false)->flatMap(fn ($b) => [$b->slotA, $b->slotB])->filter()->unique('id');
@endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <a href="{{ route('panel.events.show', $event) }}" class="small">&larr; {{ $event->name }}</a>
        <h1 class="h3 mb-1">{{ $category->name }}</h1>
        <div class="text-muted">{{ $confirmedCount }} {{ $confirmedCount === 1 ? 'inscrito confirmado' : 'inscritos confirmados' }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap no-print">
        @unless ($hasResults)
            <form method="POST" action="{{ route('panel.brackets.store', [$event, $category]) }}"
                  @if ($bouts->isNotEmpty()) onsubmit="return confirm('Se descartará el cuadro actual y se generará uno nuevo. ¿Continuar?')" @endif>
                @csrf
                <button type="submit" class="btn btn-primary">{{ $bouts->isEmpty() ? 'Generar cuadro' : 'Regenerar cuadro' }}</button>
            </form>
        @endunless
        @if ($bouts->isNotEmpty() || $category->is_published)
            <form method="POST" action="{{ route('panel.categories.publication', [$event, $category]) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline-success">{{ $category->is_published ? 'Ocultar' : 'Publicar' }}</button>
            </form>
        @endif
        @if ($category->is_published && $event->isListed())
            <a href="{{ route('public.categories.show', [$event, $category]) }}" class="btn btn-outline-secondary">Ver página pública</a>
        @endif
        <a href="{{ route('panel.exports.standings', $event) }}" class="btn btn-outline-secondary">Exportar clasificación (CSV)</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
    </div>
</div>

@if ($stale && ! $hasResults)
    <div class="alert alert-warning">El cuadro está <strong>desactualizado</strong> respecto a las inscripciones confirmadas: regenera el cuadro.</div>
@elseif ($stale && $hasResults)
    <div class="alert alert-warning">Hay cambios posteriores en las inscripciones: resuélvelos manualmente según el reglamento.</div>
@endif

<div class="alert alert-info">Regla del bronce: <strong>DECISIÓN PENDIENTE</strong> (configurada: {{ $category->bronze_rule->label() }})</div>

@if ($confirmedCount === 1)
    <div class="alert alert-secondary">Solo hay un inscrito confirmado: la clasificación se resuelve manualmente según el reglamento.</div>
@endif

@if ($bouts->isNotEmpty())
    <section class="mb-4">
        <h2 class="h5">Cuadro</h2>
        @include('partials.bracket', ['editable' => true])
    </section>

    @unless ($hasResults)
        <section class="mb-4 no-print">
            <h2 class="h5">Intercambiar participantes</h2>
            <form method="POST" action="{{ route('panel.brackets.swap', [$event, $category]) }}" class="row g-2 align-items-end">
                @csrf
                @foreach (['registration_a' => 'Participante A', 'registration_b' => 'Participante B'] as $field => $label)
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                        <select class="form-select" id="{{ $field }}" name="{{ $field }}" required>
                            @foreach ($participants as $registration)
                                <option value="{{ $registration->id }}">{{ $registration->competitor->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-12 col-md-auto"><button type="submit" class="btn btn-outline-primary">Intercambiar</button></div>
            </form>
        </section>
    @endunless

    <section class="mb-4">
        <h2 class="h5">Orden de combates</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>N.º</th><th>Ronda</th><th>Participantes</th><th>Estado</th></tr></thead>
                <tbody>
                @php $totalRounds = $bouts->where('is_bronze', false)->max('round'); @endphp
                @foreach ($bouts->whereNotNull('number')->sortBy('number') as $bout)
                    <tr>
                        <td>{{ $bout->number }}</td>
                        <td>{{ $bout->is_bronze ? 'Bronce' : \App\Tournament\RoundLabel::for($bout->round, $totalRounds) }}</td>
                        <td>{{ $bout->slotA?->competitor->displayName() ?? 'Por determinar' }} — {{ $bout->slotB?->competitor->displayName() ?? 'Por determinar' }}</td>
                        <td>{{ $bout->isCompleted() ? 'Finalizado' : ($bout->isReady() ? 'Listo' : 'Pendiente') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="h5">Podio</h2>
        @include('partials.podium')
    </section>
@endif
@endsection
