@extends('layouts.app')
@section('title', 'Editar competidor')
@section('content')
<h1 class="h3 mb-3">Editar competidor</h1>
<form method="POST" action="{{ route('panel.competitors.update', [$event, $competitor]) }}">
    @csrf
    @method('PUT')
    @include('panel.competitors._fields')
    <x-textarea name="notes" label="Notas" :value="$competitor->notes" />
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.registrations.index', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
