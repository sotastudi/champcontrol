@extends('layouts.app')
@section('title', 'Inscripción · '.$event->name)
@section('content')
<div class="col-md-8 col-lg-6 mx-auto">
    <h1 class="h3 mb-3">Inscripción: {{ $event->name }}</h1>
    <div class="alert alert-info">No se admiten inscripciones a título personal: primero se registra el club en el campeonato y, una vez aprobado por el organizador, el club inscribe a sus competidores.</div>

    @guest
        <p>Inicia sesión con la cuenta de tu club o crea una nueva.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sesión</a>
            <a href="{{ route('register.club') }}" class="btn btn-outline-primary">Registrar mi club</a>
        </div>
    @elseif (! $user->isClubAccount())
        <div class="alert alert-warning">Tu cuenta no es de un club. Para inscribir competidores entra con una cuenta de club.</div>
    @elseif (! $club)
        <p>Tu club, <strong>{{ $user->name }}</strong>, aún no está registrado en este campeonato.</p>
        <form method="POST" action="{{ route('public.clubs.store', $event) }}">@csrf
            <button class="btn btn-primary">Registrar mi club en este campeonato</button>
        </form>
    @elseif ($club->status === \App\Enums\ClubStatus::Invited)
        <div class="alert alert-info">El organizador ha invitado a <strong>{{ $user->name }}</strong> a este campeonato. <a href="{{ route('panel.invitations.index') }}" class="alert-link">Confirma la invitación</a> para poder inscribir competidores.</div>
    @elseif ($club->status === \App\Enums\ClubStatus::Pending)
        <div class="alert alert-warning">La solicitud de <strong>{{ $user->name }}</strong> está pendiente de aprobación por el organizador. Cuando la acepte podrás inscribir competidores.</div>
    @else
        <div class="alert alert-danger">El organizador ha rechazado el registro de <strong>{{ $user->name }}</strong> en este campeonato. Contacta con él si crees que es un error.</div>
    @endguest
    <a href="{{ route('public.events.show', $event) }}" class="btn btn-link px-0 mt-3">Volver al evento</a>
</div>
@endsection
