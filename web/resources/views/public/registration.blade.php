@extends('layouts.app')
@section('title', 'Inscripción · '.$event->name)
@section('content')
<h1 class="h3 mb-3">Inscripción: {{ $event->name }}</h1>
<div class="alert alert-info">Tus datos de contacto y fecha de nacimiento no se publican.</div>
@if ($categories->isEmpty())
    <div class="alert alert-warning">Este evento aún no tiene categorías disponibles.</div>
@endif
<form method="POST" action="{{ route('public.registration.store', $event) }}" @include('panel.competitors._form_data', ['categoryModels' => $categories])>
    @csrf
    <x-select name="category_id" label="Categoría" :options="$categories->pluck('name', 'id')" placeholder="Selecciona una categoría" required />
    @include('panel.competitors._fields')
    <div data-guardian-section>
        <h2 class="h5 mt-4">Datos del representante legal (obligatorios al ser menor de edad)</h2>
        @include('panel.competitors._guardian')
        <x-check name="guardian_consent" label="Declaro ser el padre, madre o representante legal del menor y consiento su inscripción y el tratamiento de sus datos para este evento." />
        @error('guardian_consent')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <p class="small text-muted">Los datos del representante no se publican.</p>
    </div>
    <button type="submit" class="btn btn-primary">Enviar inscripción</button>
    <a href="{{ route('public.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
