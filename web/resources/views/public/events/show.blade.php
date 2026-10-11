@extends('layouts.app')
@section('title', $event->name)
@section('content')
@if ($event->hasPoster())
    <img src="{{ route('public.events.poster', $event) }}" alt="Póster de {{ $event->name }}" class="img-fluid rounded mb-3" style="max-height: 400px">
@endif
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->hasRulesPdf())
    <p><a class="btn btn-outline-secondary btn-sm" href="{{ route('public.events.rules', $event) }}">Descargar normas (PDF)</a></p>
@endif
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
@if ($event->registration_opens_at || $event->registration_closes_at)
    <p class="small text-muted mb-2">
        Inscripciones
        @if ($event->registration_opens_at)desde el {{ $event->registration_opens_at->translatedFormat('j \d\e F \d\e Y, H:i') }}@endif
        @if ($event->registration_closes_at)hasta el {{ $event->registration_closes_at->translatedFormat('j \d\e F \d\e Y, H:i') }}@endif
    </p>
@endif
@if ($event->acceptsPublicRegistrations())<a class="btn btn-primary" href="{{ route('public.registration.create', $event) }}">Inscribirse</a>@endif
@if ($event->rules_text)
    <h2 class="h5 mt-4">Normas</h2>
    <p style="white-space: pre-line">{{ $event->rules_text }}</p>
@endif
@unless ($canSeeResults)
    <div class="card mt-4">
        <div class="card-body">
            <h2 class="h5">Resultados</h2>
            <p class="text-muted">Para ver los cuadros, resultados y el estado de los tatamis necesitas el código de acceso que te facilitará el organizador.</p>
            <form method="POST" action="{{ route('public.events.access', $event) }}" class="row g-2 align-items-start">
                @csrf
                <div class="col-12 col-sm-6 col-md-4">
                    <input type="text" name="code" value="{{ old('code') }}" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="Código de acceso" autocomplete="off" autocapitalize="characters" aria-label="Código de acceso" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">Ver resultados</button></div>
            </form>
        </div>
    </div>
@endunless
@if (collect($tatamis)->contains(fn ($t) => $t['categories']->isNotEmpty()))
    <h2 class="h5 mt-4">Tatamis ahora</h2>
    <div class="row g-3">
        @foreach ($tatamis as $tatami)
            @continue($tatami['categories']->isEmpty())
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 tatami-block">
                    <div class="card-header"><strong>Tatami {{ $tatami['number'] }}</strong></div>
                    <div class="card-body">
                        @if ($tatami['now'])
                            @php $bout = $tatami['now']; @endphp
                            <div class="small text-muted mb-1">{{ $bout->category->name }} · Combate {{ $bout->number }}</div>
                            <div><span class="side-chip side-aka" role="img" aria-label="AKA (rojo)"></span>{{ $bout->slotA->competitor->displayName() }}@if ($bout->slotA->competitor->club) <small class="text-muted">({{ $bout->slotA->competitor->club }})</small>@endif</div>
                            <div><span class="side-chip side-shiro" role="img" aria-label="SHIRO (blanco)"></span>{{ $bout->slotB->competitor->displayName() }}@if ($bout->slotB->competitor->club) <small class="text-muted">({{ $bout->slotB->competitor->club }})</small>@endif</div>
                        @else
                            <p class="text-muted mb-0">Sin combate en este momento.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@if ($categories->isNotEmpty())
    <h2 class="h5 mt-4">Cuadros y resultados</h2>
    <div class="list-group">
        @foreach ($categories as $category)
            @php
                $url = $category->is_published ? route('public.categories.show', [$event, $category]) : route('panel.brackets.show', [$event, $category]);
                $done = $category->total_bouts > 0 && $category->played_bouts === $category->total_bouts;
            @endphp
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap gap-2" href="{{ $url }}">
                <span>
                    {{ $category->name }}
                    @if ($category->tatami)<small class="text-muted">· Tatami {{ $category->tatami }}</small>@endif
                    @if (! $category->is_published)<span class="badge text-bg-warning">Sin publicar</span>@endif
                </span>
                <span class="small text-muted">
                    @if ($category->total_bouts === 0)
                        Cuadro sin generar
                    @elseif ($done)
                        <span class="badge text-bg-success">Finalizada</span>@if (! empty($champions[$category->id])) 🥇 {{ $champions[$category->id] }}@endif
                    @else
                        <span class="badge text-bg-primary">En curso</span> {{ $category->played_bouts }}/{{ $category->total_bouts }} combates
                    @endif
                </span>
            </a>
        @endforeach
    </div>
@endif
@endsection
