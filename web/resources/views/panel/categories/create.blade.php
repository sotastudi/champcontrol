@extends('layouts.app')
@section('title', 'Nueva categoría')
@section('content')
<h1 class="h3 mb-3">Nueva categoría</h1>
<form method="POST" action="{{ route('panel.categories.store', $event) }}">
    @csrf
    @include('panel.categories._form')
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
