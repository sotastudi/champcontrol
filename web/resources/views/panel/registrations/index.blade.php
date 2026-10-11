@extends('layouts.app')
@section('title', 'Inscripciones')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Inscripciones</h1>
        <a href="{{ route('panel.events.show', $event) }}" class="small">&larr; {{ $event->name }}</a>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('panel.exports.registrations', ['event' => $event] + $filters) }}" class="btn btn-outline-secondary">Exportar CSV</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
        <a href="{{ route('panel.registrations.create', $event) }}" class="btn btn-primary">Nueva inscripción</a>
    </div>
</div>
<h1 class="h4 d-none d-print-block">{{ $event->name }} — Inscritos</h1>
@error('status')<div class="alert alert-danger">{{ $message }}</div>@enderror

<form method="GET" action="{{ route('panel.registrations.index', $event) }}" class="row g-2 mb-3 no-print">
    <div class="col-md-3">
        <select name="category_id" class="form-select" aria-label="Categoría">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\RegistrationStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nombre, apellidos o club" aria-label="Buscar"></div>
    <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filtrar</button></div>
</form>

@if ($registrations->isEmpty())
    <p class="text-muted">No hay inscripciones.</p>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>N.º</th><th>Competidor</th><th>Club</th><th>Categoría</th><th>Estado</th><th class="no-print"></th></tr></thead>
            <tbody>
            @foreach ($registrations as $registration)
                @php($warnings = $registration->category->mismatchesFor($registration->competitor))
                <tr>
                    <td>@if ($registration->competitor->bib_number)<span class="bib-tag">{{ $registration->competitor->bib_number }}</span>@endif</td>
                    <td>
                        {{ $registration->competitor->last_name }}, {{ $registration->competitor->first_name }}
                        @if ($registration->competitor->isMinorOn($event->starts_on))
                            <span class="badge text-bg-info">Menor</span>
                            @unless ($registration->competitor->hasGuardianData())
                                <span class="badge text-bg-warning">Menor sin datos de representante</span>
                            @endunless
                        @endif
                        @foreach ($warnings as $warning)
                            <div><span class="badge text-bg-warning">{{ $warning }}</span></div>
                        @endforeach
                    </td>
                    <td>{{ $registration->competitor->club }}</td>
                    <td>{{ $registration->category->name }}</td>
                    <td><span class="badge {{ $registration->status->badgeClass() }}">{{ $registration->status->label() }}</span></td>
                    <td class="text-end text-nowrap no-print">
                        @foreach ([['confirmed', 'Confirmar'], ['cancelled', 'Cancelar'], ['pending', 'Pendiente']] as [$target, $text])
                            @if ($registration->status->value !== $target)
                                <form method="POST" action="{{ route('panel.registrations.update', [$event, $registration]) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $target }}">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $text }}</button>
                                </form>
                            @endif
                        @endforeach
                        <a href="{{ route('panel.registrations.show', [$event, $registration]) }}" class="btn btn-outline-info btn-sm">Ver ficha</a>
                        <a href="{{ route('panel.competitors.edit', [$event, $registration->competitor]) }}" class="btn btn-outline-primary btn-sm">Editar datos</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
