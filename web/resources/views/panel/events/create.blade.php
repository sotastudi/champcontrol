@extends('layouts.app')
@section('title', 'Nuevo evento')
@section('content')
<h1 class="h3 mb-3">Nuevo evento</h1>
<form method="POST" enctype="multipart/form-data" action="{{ route('panel.events.store') }}">
    @csrf
    @include('panel.events._form')
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.events.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
