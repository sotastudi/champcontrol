@extends('layouts.app')
@section('title', 'Mis eventos')
@section('content')
<h1 class="h3 mb-1">Mis eventos</h1>
<p class="text-muted">Los campeonatos a los que está vinculado tu club, con el estado de su participación.</p>

@if ($links->isEmpty())
    <p class="text-muted">Tu club aún no está vinculado a ningún evento. Entra en un evento desde la <a href="{{ route('home') }}">portada</a> y pulsa «Inscribirse» para registrar tu club.</p>
@else
    <div class="row g-3">
        @foreach ($links as $link)
            @php
                $event = $link->event;
                $status = $link->status;
                $label = match ($status) {
                    \App\Enums\ClubStatus::Approved => 'Confirmado',
                    \App\Enums\ClubStatus::Pending => 'Pendiente de confirmar por el organizador',
                    \App\Enums\ClubStatus::Invited => 'Invitación pendiente de tu confirmación',
                    \App\Enums\ClubStatus::Rejected => 'Rechazado',
                };
            @endphp
            <div class="col-md-6">
                <div class="card h-100 {{ $status === \App\Enums\ClubStatus::Rejected ? 'opacity-75' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h2 class="h5 mb-1">{{ $event->name }}</h2>
                            <span class="badge {{ $status->badgeClass() }} text-wrap text-end">{{ $label }}</span>
                        </div>
                        <div class="text-muted small mb-2">{{ $event->discipline }} · {{ $event->startsAtLabel() }} · {{ $event->venue }}</div>
                        <div class="small mb-3">Organiza: {{ $event->user->name }}</div>
                        <div class="mt-auto d-flex flex-wrap gap-2">
                            @if ($status === \App\Enums\ClubStatus::Invited)
                                <a href="{{ route('panel.invitations.index') }}" class="btn btn-primary btn-sm">Ver invitación</a>
                            @elseif ($status === \App\Enums\ClubStatus::Approved && $event->acceptsPublicRegistrations())
                                <a href="{{ route('public.registration.create', $event) }}" class="btn btn-primary btn-sm">Inscribir competidores</a>
                            @endif
                            @if ($event->isListed())
                                <a href="{{ route('public.events.show', $event) }}" class="btn btn-outline-secondary btn-sm">Ver evento</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
