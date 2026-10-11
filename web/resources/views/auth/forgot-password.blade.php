@extends('layouts.app')
@section('title', 'Recuperar contraseña')
@section('content')
<div class="col-md-6 col-lg-5 mx-auto">
    <h1 class="h3 mb-3">Recuperar contraseña</h1>
    <form method="POST" action="{{ route('password.email') }}">@csrf
        <x-field name="email" type="email" label="Correo electrónico" required autofocus />
        <button class="btn btn-primary w-100">Enviar enlace</button>
    </form>
    <p class="mt-3 mb-0"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
</div>
@endsection
