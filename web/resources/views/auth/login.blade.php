@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<div class="col-md-6 col-lg-5 mx-auto">
    <h1 class="h3 mb-3">Iniciar sesión</h1>
    <form method="POST" action="{{ route('login') }}">@csrf
        <x-field name="email" type="email" label="Correo electrónico" required autofocus />
        <x-field name="password" type="password" label="Contraseña" required />
        <x-check name="remember" label="Mantener la sesión iniciada" />
        <button class="btn btn-primary w-100">Entrar</button>
    </form>
    <p class="mt-3 mb-1"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></p>
    <p class="mb-0">¿No tienes cuenta? <a href="{{ route('register') }}" class="fw-semibold">Registrarse (crear cuenta)</a></p>
</div>
@endsection
