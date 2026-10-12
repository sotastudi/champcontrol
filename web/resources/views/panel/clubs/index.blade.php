@extends('layouts.app')
@section('title', 'Clubes · '.$event->name)
@section('content')
<a href="{{ route('panel.events.show', $event) }}" class="small">&larr; {{ $event->name }}</a>
<h1 class="h3 mb-3">Clubes registrados</h1>
<p class="text-muted">Solo los clubes aprobados pueden inscribir competidores en el formulario público. Tus altas manuales no dependen de esta lista.</p>
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
