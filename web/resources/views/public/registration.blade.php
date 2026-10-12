@extends('layouts.app')
@section('title', 'Inscripción · '.$event->name)
@section('content')
<h1 class="h3 mb-3">Inscripción: {{ $event->name }}</h1>
<div class="alert alert-info">Inscribes como club <strong>{{ $clubName }}</strong>. Los datos de contacto, la fecha de nacimiento y la foto no se publican.</div>
<section class="card mb-4" id="plantel">
    <div class="card-body">
        <h2 class="h5">Agregar desde mis competidores</h2>
        @if (count($roster) === 0)
            <p class="text-muted mb-0">Aún no tienes competidores guardados. <a href="{{ route('panel.athletes.index') }}">Añádelos en «Mis competidores»</a> y podrás agregarlos a una categoría con un clic, sin rellenar el formulario.</p>
        @else
            <p class="text-muted small">Pulsa en la categoría para inscribir al competidor (queda pendiente de confirmar). Solo se ofrecen las categorías cuyos requisitos cumple; de las demás se indica debajo el motivo.</p>
            <div class="list-group list-group-flush">
                @foreach ($roster as $row)
                    @php $athlete = $row['athlete']; @endphp
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">{{ $athlete->displayName() }} <span class="text-muted fw-normal small">· {{ $athlete->birth_date->format('d/m/Y') }} · {{ $athlete->sex->label() }}@if ($athlete->weight !== null) · {{ $athlete->weight }} kg @endif · {{ $athlete->level }}</span></div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach ($row['options'] as $option)
                                @if ($option['registered'])
                                    <span class="badge text-bg-success align-self-center">Inscrito en {{ $option['category']->name }}</span>
                                @elseif ($option['problems'] === [])
                                    <form method="POST" action="{{ route('public.registration.roster', $event) }}">@csrf
                                        <input type="hidden" name="athlete_id" value="{{ $athlete->id }}"><input type="hidden" name="category_id" value="{{ $option['category']->id }}">
                                        <button class="btn btn-outline-primary btn-sm btn-icon"><svg width="14" height="14" aria-hidden="true"><use href="#plus"/></svg> Agregar a {{ $option['category']->name }}</button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                        @php $blocked = $row['options']->filter(fn ($o) => ! $o['registered'] && $o['problems'] !== []); @endphp
                        @if ($blocked->isNotEmpty())
                            @if ($blocked->count() === $row['options']->reject(fn ($o) => $o['registered'])->count())
                                <div class="small text-danger mt-2">No cumple los requisitos de ninguna categoría disponible:</div>
                            @else
                                <div class="small text-muted mt-2">No puede entrar en:</div>
                            @endif
                            <ul class="small text-muted mb-0">
                                @foreach ($blocked as $option)
                                    <li><strong>{{ $option['category']->name }}</strong>: {{ implode('; ', $option['problems']) }}.</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
<h2 class="h5">O inscribe a un competidor nuevo</h2>
@if ($categories->isEmpty())
    <div class="alert alert-warning">Este evento aún no tiene categorías disponibles.</div>
@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('public.registration.store', $event) }}" @include('panel.competitors._form_data', ['categoryModels' => $categories])>
    @csrf
    <x-select name="category_id" label="Categoría" :options="$categories->pluck('name', 'id')" placeholder="Selecciona una categoría" required />
    @include('panel.competitors._fields', ['lockedClub' => $clubName])
    <div data-guardian-section>
        <h2 class="h5 mt-4">Datos del representante legal (obligatorios al ser menor de edad)</h2>
        @include('panel.competitors._guardian')
        <x-check name="guardian_consent" label="Declaro ser el padre, madre o representante legal del menor y consiento su inscripción y el tratamiento de sus datos para este evento." />
        @error('guardian_consent')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <p class="small text-muted">Los datos del representante no se publican.</p>
    </div>
    <button type="submit" class="btn btn-primary">Enviar inscripción</button>
    <a href="{{ route('public.events.show', $event) }}" class="btn btn-link">Cancelar</a>
</form>
@endsection
