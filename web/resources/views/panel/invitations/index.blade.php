@extends('layouts.app')
@section('title', 'Invitaciones')
@section('content')
<div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-3">Invitaciones a eventos</h1>
    @forelse ($invitations as $invitation)
        @php $event = $invitation->event; @endphp
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">{{ $event->name }}</h2>
                <dl class="row small mb-3">
                    <dt class="col-sm-3">Organizador</dt><dd class="col-sm-9">{{ $event->user->name }}</dd>
                    <dt class="col-sm-3">Disciplina</dt><dd class="col-sm-9">{{ $event->discipline }}</dd>
                    <dt class="col-sm-3">Fecha</dt><dd class="col-sm-9">{{ $event->startsAtLabel() }}</dd>
                    <dt class="col-sm-3">Lugar</dt><dd class="col-sm-9">{{ $event->venue }}</dd>
                    @if ($event->description)<dt class="col-sm-3">Descripción</dt><dd class="col-sm-9" style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($event->description, 400) }}</dd>@endif
                </dl>
                @if ($event->isListed())<p class="small"><a href="{{ route('public.events.show', $event) }}">Ver la página pública del evento</a></p>@endif
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('panel.invitations.accept', $invitation) }}">@csrf
                        <button class="btn btn-success btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#check"/></svg> Confirmar invitación</button>
                    </form>
                    <form method="POST" action="{{ route('panel.invitations.decline', $invitation) }}">@csrf
                        <button class="btn btn-outline-secondary">Rechazar</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted">No tienes invitaciones pendientes.</p>
    @endforelse
</div>
@endsection
