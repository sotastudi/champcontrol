@extends('layouts.app')
@section('title', 'Editar competidor')
@section('content')
<div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-3">Editar competidor</h1>
    <form method="POST" data-athlete-form data-adult-age="{{ config('kumitecontrol.adult_age', config('champcontrol.adult_age', 18)) }}" action="{{ route('panel.athletes.update', $athlete) }}">@csrf @method('PUT')
        @include('panel.athletes._form')
        <button class="btn btn-primary">Guardar</button>
        <a href="{{ route('panel.athletes.index') }}" class="btn btn-link">Cancelar</a>
    </form>
</div>
@endsection
