@extends('layouts.app')
@section('title', 'Nueva inscripción')
@section('content')
<h1 class="h3 mb-3">Nueva inscripción</h1>
@if (empty($categories))
    <div class="alert alert-info">Primero crea alguna categoría en el evento.</div>
@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('panel.registrations.store', $event) }}" @include('panel.competitors._form_data')>
    @csrf
    <x-select name="category_id" label="Categoría" :options="$categories" placeholder="Selecciona una categoría" required />
    @include('panel.competitors._fields')
    <div data-guardian-section>
        <h2 class="h6">Representante legal (competidor menor de edad)</h2>
        @include('panel.competitors._guardian')
        <x-check name="guardian_consent" label="El representante ha dado su consentimiento" help="Solo se registra si indicas el nombre del representante. Eres responsable de haberlo obtenido." />
    </div>
    <x-select name="status" label="Estado" :options="[\App\Enums\RegistrationStatus::Pending->value => 'Pendiente', \App\Enums\RegistrationStatus::Confirmed->value => 'Confirmada']" value="confirmed" required />
    <x-textarea name="notes" label="Notas" />
    <button type="submit" class="btn btn-primary">Registrar</button>
    <a href="{{ route('panel.registrations.index', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
