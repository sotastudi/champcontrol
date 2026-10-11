@extends('layouts.app')
@section('title', 'Editar categoría')
@section('content')
<h1 class="h3 mb-3">Editar categoría</h1>
<form method="POST" action="{{ route('panel.categories.update', [$event, $category]) }}">
    @csrf
    @method('PUT')
    @include('panel.categories._form')
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
