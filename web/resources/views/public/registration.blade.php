@extends('layouts.app')
@section('title', 'Inscripción · '.$event->name)
@section('content')
<h1 class="h3 mb-3">Inscripción: {{ $event->name }}</h1>
<div class="alert alert-info">Solo mayores de {{ $minAge }} años. Tus datos de contacto y fecha de nacimiento no se publican.</div>
@error('error')<div class="alert alert-danger">{{ $message }}</div>@enderror
@if ($categories->isEmpty())
    <div class="alert alert-warning">Este evento aún no tiene categorías disponibles.</div>
@endif
<form method="POST" action="{{ route('public.registration.store', $event) }}">
    @csrf
    <x-select name="category_id" label="Categoría" :options="$categories->pluck('name', 'id')" placeholder="Selecciona una categoría" required />
    @include('panel.competitors._fields')
    <button type="submit" class="btn btn-primary">Enviar inscripción</button>
    <a href="{{ route('public.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
