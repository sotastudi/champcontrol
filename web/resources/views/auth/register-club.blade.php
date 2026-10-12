@extends('layouts.app')
@section('title', 'Registrar club')
@section('content')
<div class="col-md-6 col-lg-5 mx-auto">
    <h1 class="h3 mb-3">Crear cuenta de club</h1>
    <p class="text-muted">Las inscripciones las hacen los clubes. Tras crear la cuenta, registra tu club en cada campeonato y espera la aprobación del organizador.</p>
    <form method="POST" action="{{ route('register.club') }}">@csrf
        <x-field name="name" label="Nombre del club" help="Es el club que constará en las inscripciones de tus competidores." required autofocus />
        <x-field name="email" type="email" label="Correo electrónico" required />
        <x-field name="password" type="password" label="Contraseña" help="Mínimo 8 caracteres" required />
        <x-field name="password_confirmation" type="password" label="Repite la contraseña" required />
        <button class="btn btn-primary w-100">Crear cuenta de club</button>
    </form>
    <p class="mt-3 mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
</div>
@endsection
