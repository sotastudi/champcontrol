@extends('layouts.app')
@section('title', 'Crear cuenta')
@section('content')
<div class="col-md-6 col-lg-5 mx-auto">
    <h1 class="h3 mb-3">Crear cuenta de organizador</h1>
    <form method="POST" action="{{ route('register') }}">@csrf
        <x-field name="name" label="Nombre" required autofocus />
        <x-field name="email" type="email" label="Correo electrónico" required />
        <x-field name="password" type="password" label="Contraseña" help="Mínimo 8 caracteres" required />
        <x-field name="password_confirmation" type="password" label="Repite la contraseña" required />
        <button class="btn btn-primary w-100">Crear cuenta</button>
    </form>
    <p class="mt-3 mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
</div>
@endsection
