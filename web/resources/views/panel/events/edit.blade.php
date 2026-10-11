@extends('layouts.app')
@section('title', 'Editar evento')
@section('content')
<h1 class="h3 mb-3">Editar evento</h1>
<form method="POST" action="{{ route('panel.events.update', $event) }}">
    @csrf
    @method('PUT')
    @include('panel.events._form')
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
