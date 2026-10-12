@extends('layouts.app')
@section('title', 'Mis competidores')
@section('content')
<h1 class="h3 mb-1">Mis competidores</h1>
<p class="text-muted">Guarda aquí los datos de los competidores de tu club una sola vez. En cada campeonato donde tu club esté aprobado podrás agregarlos a su categoría con un clic, siempre que cumplan los requisitos de la categoría.</p>

<button type="button" class="btn btn-primary btn-sm btn-icon mb-3" data-bs-toggle="collapse" data-bs-target="#new-athlete" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="new-athlete"><svg width="16" height="16" aria-hidden="true"><use href="#plus"/></svg> Nuevo competidor</button>
<form method="POST" data-athlete-form data-adult-age="{{ config('kumitecontrol.adult_age') }}" action="{{ route('panel.athletes.store') }}" class="collapse{{ $errors->any() ? ' show' : '' }} mb-4" id="new-athlete">@csrf
    <div class="card"><div class="card-body">
        @include('panel.athletes._form')
        <button class="btn btn-primary">Guardar competidor</button>
    </div></div>
</form>

@if ($athletes->isEmpty())
    <p class="text-muted">Aún no tienes competidores.</p>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Competidor</th><th>Nacimiento</th><th>Sexo</th><th>Peso</th><th>Nivel</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            @foreach ($athletes as $athlete)
                <tr>
                    <td>{{ $athlete->displayName() }}</td>
                    <td>{{ $athlete->birth_date->format('d/m/Y') }}</td>
                    <td>{{ $athlete->sex->label() }}</td>
                    <td>{{ $athlete->weight !== null ? $athlete->weight.' kg' : '—' }}</td>
                    <td>{{ $athlete->level }}</td>
                    <td class="text-end">
                        <a href="{{ route('panel.athletes.edit', $athlete) }}" class="btn btn-yellow btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#pencil"/></svg> Editar</a>
                        <form method="POST" action="{{ route('panel.athletes.destroy', $athlete) }}" class="d-inline" onsubmit="return confirm('¿Eliminar a {{ $athlete->displayName() }} de tu plantel? Sus inscripciones ya hechas se mantienen.')">@csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#trash"/></svg> Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
