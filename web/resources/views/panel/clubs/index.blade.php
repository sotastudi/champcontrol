@extends('layouts.app')
@section('title', 'Clubes · '.$event->name)
@section('content')
<a href="{{ route('panel.events.show', $event) }}" class="small">&larr; {{ $event->name }}</a>
<h1 class="h3 mb-3">Clubes registrados</h1>
<p class="text-muted">Solo los clubes aprobados pueden inscribir competidores en el formulario público. Tus altas manuales no dependen de esta lista.</p>
<button type="button" class="btn btn-primary btn-sm btn-icon mb-3" data-bs-toggle="collapse" data-bs-target="#create-club" aria-expanded="{{ $errors->hasAny(['name', 'email', 'password']) ? 'true' : 'false' }}" aria-controls="create-club"><svg width="16" height="16" aria-hidden="true"><use href="#plus"/></svg> Crear cuenta de club</button>
<form method="POST" action="{{ route('panel.clubs.store', $event) }}" class="collapse{{ $errors->hasAny(['name', 'email', 'password']) ? ' show' : '' }} mb-4" id="create-club">@csrf
    <div class="card">
        <div class="card-body">
            <p class="text-muted small">Crea la cuenta de un club y queda aprobado en este evento. Entrega al club su correo y contraseña; si el correo ya es de una cuenta de club, solo se aprueba en el evento y no se cambia su contraseña.</p>
            <div class="row">
                <div class="col-md-4"><x-field name="name" label="Nombre del club" required /></div>
                <div class="col-md-4"><x-field name="email" type="email" label="Correo electrónico" required /></div>
                <div class="col-md-4"><x-field name="password" type="password" label="Contraseña" help="Mínimo 8 caracteres" autocomplete="new-password" /></div>
            </div>
            <button class="btn btn-primary">Crear y aprobar</button>
        </div>
    </div>
</form>

@if ($clubs->isEmpty())
    <p class="text-muted">Ningún club se ha registrado todavía en este evento.</p>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Club</th><th>Correo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @foreach ($clubs as $club)
                <tr>
                    <td>{{ $club->user->name }}</td>
                    <td>{{ $club->user->email }}</td>
                    <td><span class="badge {{ $club->status->badgeClass() }}">{{ $club->status->label() }}</span></td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('panel.clubs.update', [$event, $club]) }}" class="d-inline">@csrf @method('PUT')
                            @if ($club->status !== \App\Enums\ClubStatus::Approved)
                                <button name="status" value="approved" class="btn btn-sm btn-success">Aprobar</button>
                            @endif
                            @if ($club->status !== \App\Enums\ClubStatus::Rejected)
                                <button name="status" value="rejected" class="btn btn-sm btn-outline-secondary">Rechazar</button>
                            @endif
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
