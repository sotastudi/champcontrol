@extends('layouts.app')
@section('title', $event->name)
@section('content')
@if ($event->hasPoster())
    <img src="{{ route('public.events.poster', $event) }}" alt="Póster de {{ $event->name }}" class="img-fluid rounded mb-3" style="max-height: 400px">
@endif
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
@if ($event->registration_opens_at || $event->registration_closes_at)
    <p class="small text-muted mb-2">
        Inscripciones
        @if ($event->registration_opens_at)desde el {{ $event->registration_opens_at->translatedFormat('j \d\e F \d\e Y, H:i') }}@endif
        @if ($event->registration_closes_at)hasta el {{ $event->registration_closes_at->translatedFormat('j \d\e F \d\e Y, H:i') }}@endif
    </p>
@endif
@if ($event->acceptsPublicRegistrations())<a class="btn btn-primary" href="{{ route('public.registration.create', $event) }}">Inscribirse</a>@endif
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
