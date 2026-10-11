@extends('layouts.app')
@section('content')
    <h1 class="h3">Campeonatos de artes marciales</h1>
    <p class="text-muted">Consulta los eventos publicados o accede como organizador.</p>
    @forelse ($events as $event)
        @if ($loop->first)<div class="row g-4">@endif
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                <a href="{{ route('public.events.show', $event) }}" class="text-decoration-none">
                    @if ($event->hasPoster())
                        <img src="{{ route('public.events.poster', $event) }}" class="card-img-top bg-body-secondary" style="max-height: 400px; object-fit: contain" alt="Póster de {{ $event->name }}" loading="lazy">
                    @else
                        <div class="card-img-top bg-body-secondary d-flex align-items-center justify-content-center text-muted" style="height: 200px">Sin póster</div>
                    @endif
                </a>
                <div class="card-body d-flex flex-column">
                    <h2 class="h5 card-title">{{ $event->name }}</h2>
                    <p class="card-text text-muted small mb-3">
                        {{ $event->venue }}<br>
                        {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }}
                    </p>
                    <div class="mt-auto">
                        @if ($event->acceptsPublicRegistrations())
                            <a href="{{ route('public.registration.create', $event) }}" class="btn btn-primary w-100">Inscribirse</a>
                        @else
                            <a href="{{ route('public.events.show', $event) }}" class="btn btn-outline-primary w-100">Ver evento</a>
                        @endif
                        @php $dateNote = $event->registrationDateNote(); @endphp
                        @if ($dateNote)
                            <div class="small text-muted text-center mt-2 registration-date">
                                @if ($dateNote['type'] === 'closes')
                                    Inscripciones abiertas hasta el {{ $dateNote['at']->translatedFormat('j \d\e F \d\e Y, H:i') }}
                                @else
                                    Las inscripciones abren el {{ $dateNote['at']->translatedFormat('j \d\e F \d\e Y, H:i') }}
                                @endif
                            </div>
                            @if (! empty($dateNote['countdown']))
                                @include('partials.registration-countdown', ['closesAt' => $dateNote['at']])
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if ($loop->last)</div>@endif
    @empty
        <p class="text-muted">No hay eventos publicados.</p>
    @endforelse
@endsection
