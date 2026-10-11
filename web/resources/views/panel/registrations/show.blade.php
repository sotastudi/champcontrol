@extends('layouts.app')
@section('title', 'Ficha de inscripción')
@section('content')
@php($age = $competitor->ageOn($event->starts_on))
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $competitor->last_name }}, {{ $competitor->first_name }}</h1>
        <a href="{{ route('panel.registrations.index', $event) }}" class="small">&larr; Inscripciones de {{ $event->name }}</a>
    </div>
    <span class="badge fs-6 {{ $registration->status->badgeClass() }}">{{ $registration->status->label() }}</span>
</div>

@if ($competitor->isMinorOn($event->starts_on))
    <div class="mb-2">
        <span class="badge text-bg-info">Menor</span>
        @unless ($competitor->hasGuardianData())
            <span class="badge text-bg-warning">Menor sin datos de representante</span>
        @endunless
    </div>
@endif
@foreach ($warnings as $warning)
    <div class="alert alert-warning py-2">{{ $warning }}</div>
@endforeach

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <h2 class="h5">Competidor</h2>
        @if ($competitor->hasPhoto())
            <img src="{{ route('panel.competitors.photo', [$event, $competitor]) }}?v={{ $competitor->updated_at?->timestamp }}" alt="Foto de {{ $competitor->first_name }}" class="img-thumbnail mb-3" style="max-height: 12rem">
        @endif
        <dl class="row mb-0">
            <dt class="col-5">Número</dt><dd class="col-7">{{ $competitor->bib_number ?: '—' }}</dd>
            <dt class="col-5">Club</dt><dd class="col-7">{{ $competitor->club ?: '—' }}</dd>
            <dt class="col-5">Fecha de nacimiento</dt><dd class="col-7">{{ $competitor->birth_date->format('d/m/Y') }}</dd>
            <dt class="col-5">Edad en el evento</dt><dd class="col-7">{{ $age }} años ({{ $event->starts_on->format('d/m/Y') }})</dd>
            <dt class="col-5">Sexo</dt><dd class="col-7">{{ $competitor->sex?->label() ?? '—' }}</dd>
            <dt class="col-5">Peso</dt><dd class="col-7">{{ $competitor->weight !== null ? $competitor->weight.' kg' : '—' }}</dd>
            <dt class="col-5">Nivel</dt><dd class="col-7">{{ $competitor->level ?: '—' }}</dd>
            <dt class="col-5">Correo</dt><dd class="col-7">{{ $competitor->email ?: '—' }}</dd>
            <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $competitor->phone ?: '—' }}</dd>
        </dl>
    </div>
    <div class="col-md-6">
        <h2 class="h5">Inscripción</h2>
        <dl class="row mb-3">
            <dt class="col-5">Categoría</dt><dd class="col-7">{{ $registration->category->name }}</dd>
            <dt class="col-5">Inscrito el</dt><dd class="col-7">{{ $registration->created_at->format('d/m/Y H:i') }}</dd>
        </dl>
        @if ($competitor->isMinorOn($event->starts_on) || $competitor->guardian_name)
            <h2 class="h5">Representante legal</h2>
            <dl class="row mb-0">
                <dt class="col-5">Nombre</dt><dd class="col-7">{{ $competitor->guardian_name ?: '—' }}</dd>
                <dt class="col-5">Contacto</dt><dd class="col-7">{{ $competitor->guardian_contact ?: '—' }}</dd>
                <dt class="col-5">Consentimiento</dt><dd class="col-7">{{ $competitor->guardian_consent_at?->format('d/m/Y H:i') ?? 'No registrado' }}</dd>
            </dl>
        @endif
    </div>
</div>

@if ($competitor->notes)
    <h2 class="h5">Notas</h2>
    <p class="text-break" style="white-space: pre-line">{{ $competitor->notes }}</p>
@endif

<p class="text-muted small">Estos datos son privados: no se muestran en las páginas públicas.</p>

<div class="d-flex flex-wrap gap-2 no-print">
    @foreach ([['confirmed', 'Confirmar', 'btn-success'], ['cancelled', 'Cancelar', 'btn-outline-danger'], ['pending', 'Pendiente', 'btn-outline-secondary']] as [$target, $text, $class])
        @if ($registration->status->value !== $target)
            <form method="POST" action="{{ route('panel.registrations.update', [$event, $registration]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $target }}">
                <button type="submit" class="btn {{ $class }}">{{ $text }}</button>
            </form>
        @endif
    @endforeach
    <a href="{{ route('panel.competitors.edit', [$event, $competitor]) }}" class="btn btn-outline-primary">Editar datos</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
</div>
@endsection
