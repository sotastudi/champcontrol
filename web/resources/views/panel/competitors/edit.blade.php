@extends('layouts.app')
@section('title', 'Editar competidor')
@section('content')
<h1 class="h3 mb-3">Editar competidor</h1>
<form method="POST" enctype="multipart/form-data" action="{{ route('panel.competitors.update', [$event, $competitor]) }}" @include('panel.competitors._form_data')>
    @csrf
    @method('PUT')
    @include('panel.competitors._fields')
    <div data-guardian-section>
        <h2 class="h6">Representante legal (competidor menor de edad)</h2>
        @include('panel.competitors._guardian')
    </div>
    <x-textarea name="notes" label="Notas" :value="$competitor->notes" />
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('panel.registrations.index', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
