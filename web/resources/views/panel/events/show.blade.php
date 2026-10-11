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
        <a href="{{ route('panel.registrations.index', $event) }}" class="btn btn-primary">Inscripciones</a>
        <form method="POST" action="{{ route('panel.events.bibs', $event) }}"
              onsubmit="return confirm('Se asignarán (o reasignarán) los números de competidor agrupando por club. ¿Continuar?')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Asignar números de competidor</button>
        </form>
        <a href="{{ route('panel.exports.standings', $event) }}" class="btn btn-outline-secondary">Exportar clasificación (CSV)</a>
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

<section id="categorias" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Categorías</h2>
        <a href="{{ route('panel.categories.create', $event) }}" class="btn btn-primary btn-sm">Nueva categoría</a>
    </div>
    @if ($event->categories->isEmpty())
        <p class="text-muted">Aún no hay categorías.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Criterios</th><th class="text-center">Confirmados</th><th></th></tr></thead>
                <tbody>
                @foreach ($event->categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td class="text-muted small">
                            {{ collect([
                                $category->modality,
                                $category->sex?->label(),
                                ($category->min_age !== null || $category->max_age !== null) ? 'Edad '.($category->min_age ?? '…').'–'.($category->max_age ?? '…') : null,
                                ($category->min_weight !== null || $category->max_weight !== null) ? 'Peso '.($category->min_weight ?? '…').'–'.($category->max_weight ?? '…').' kg' : null,
                                $category->level,
                                $category->tatami ? 'Tatami '.$category->tatami.($category->referees ? ' ('.$category->referees.' '.'jueces'.')' : '') : null,
                            ])->filter()->implode(' · ') ?: 'Sin restricciones' }}
                        </td>
                        <td class="text-center"><a href="{{ route('panel.registrations.index', ['event' => $event, 'category_id' => $category->id]) }}">{{ $category->confirmed_count }}</a></td>
                        <td class="text-end">
                            <a href="{{ route('panel.brackets.show', [$event, $category]) }}" class="btn btn-outline-secondary btn-sm">Cuadro</a>
                            @if ($category->is_published && $event->isListed())
                                <a href="{{ route('public.categories.show', [$event, $category]) }}" class="btn btn-outline-secondary btn-sm">Ver página pública</a>
                            @endif
                            <form method="POST" action="{{ route('panel.categories.publication', [$event, $category]) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline-success btn-sm">{{ $category->is_published ? 'Ocultar' : 'Publicar' }}</button>
                            </form>
                            <a href="{{ route('panel.categories.edit', [$event, $category]) }}" class="btn btn-outline-primary btn-sm">Editar</a>
                            <form method="POST" action="{{ route('panel.categories.destroy', [$event, $category]) }}" class="d-inline" onsubmit="return confirm('¿Eliminar la categoría? Se borrarán también su cuadro, combates e inscripciones.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
