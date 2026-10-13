@extends('layouts.app')
@section('title', 'Administración')
@section('content')
<h1 class="h3 mb-3">Administración</h1>

<h2 class="h5 mt-4">Organizadores ({{ $organizers->count() }})</h2>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Nombre</th><th>Correo</th><th class="text-end">Eventos</th><th>Alta</th></tr></thead>
    <tbody>
    @forelse ($organizers as $u)
        <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td class="text-end">{{ $u->events_count }}</td><td>{{ $u->created_at?->format('d/m/Y') }}</td></tr>
    @empty
        <tr><td colspan="4" class="text-muted">Sin organizadores.</td></tr>
    @endforelse
    </tbody>
</table></div>

<h2 class="h5 mt-4">Clubs ({{ $clubs->count() }})</h2>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Club</th><th>Correo</th><th class="text-end">Eventos vinculados</th><th>Alta</th></tr></thead>
    <tbody>
    @forelse ($clubs as $u)
        <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td class="text-end">{{ $u->club_registrations_count }}</td><td>{{ $u->created_at?->format('d/m/Y') }}</td></tr>
    @empty
        <tr><td colspan="4" class="text-muted">Sin clubs.</td></tr>
    @endforelse
    </tbody>
</table></div>

<h2 class="h5 mt-4">Eventos ({{ $events->count() }})</h2>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Evento</th><th>Organizador</th><th>Fecha</th><th>Estado</th><th class="text-end">Pendientes</th><th class="text-end">Confirmadas</th></tr></thead>
    <tbody>
    @forelse ($events as $event)
        <tr><td>{{ $event->name }}</td><td>{{ $event->user?->name }}</td><td>{{ $event->startsAtLabel('d/m/Y') }}</td><td>{{ $event->status->label() }}</td><td class="text-end">{{ $event->pending_count }}</td><td class="text-end">{{ $event->confirmed_count }}</td></tr>
    @empty
        <tr><td colspan="6" class="text-muted">Sin eventos.</td></tr>
    @endforelse
    </tbody>
</table></div>

<h2 class="h5 mt-4">Inscritos ({{ $registrations->total() }})</h2>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Competidor</th><th>Club</th><th>Evento</th><th>Categoría</th><th>Estado</th></tr></thead>
    <tbody>
    @forelse ($registrations as $r)
        <tr><td>{{ $r->competitor->displayName() }}</td><td>{{ $r->competitor->club }}</td><td>{{ $r->category->event->name }}</td><td>{{ $r->category->name }}</td><td><span class="badge {{ $r->status->badgeClass() }}">{{ $r->status->label() }}</span></td></tr>
    @empty
        <tr><td colspan="5" class="text-muted">Sin inscritos.</td></tr>
    @endforelse
    </tbody>
</table></div>
{{ $registrations->links() }}
@endsection
