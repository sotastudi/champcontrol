@extends('layouts.app')
@section('title', $category->name.' · '.$event->name)
@section('content')
@php $totalRounds = $bouts->where('is_bronze', false)->max('round'); @endphp
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <a href="{{ route('public.events.show', $event) }}" class="small no-print">&larr; {{ $event->name }}</a>
        <h1 class="h3 mb-1">{{ $category->name }}</h1>
        <div class="text-muted">{{ $event->name }}@if ($category->tatami) · Tatami {{ $category->tatami }}@endif</div>
    </div>
    <button type="button" class="btn btn-outline-secondary no-print" onclick="window.print()">Imprimir</button>
</div>

@if (count($placements))
    <section class="mb-4">
        <h2 class="h5">Podio</h2>
        @include('partials.podium')
    </section>
@endif

<section class="mb-4">
    <h2 class="h5">Cuadro</h2>
    @include('partials.bracket')
</section>

<section>
    <h2 class="h5">Resultados</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>N.º</th><th>Ronda</th><th>Participantes</th><th>Ganador</th></tr></thead>
            <tbody>
            @foreach ($bouts->whereNotNull('number')->sortBy('number') as $bout)
                <tr>
                    <td>{{ $bout->number }}</td>
                    <td>{{ $bout->is_bronze ? 'Bronce' : \App\Tournament\RoundLabel::for($bout->round, $totalRounds) }}</td>
                    <td>
                        @foreach ([$bout->slotA, $bout->slotB] as $slot)
                            {{ $slot?->competitor->displayName() ?? 'Por determinar' }}@if ($slot?->competitor->club) <small class="text-muted">({{ $slot->competitor->club }})</small>@endif @unless ($loop->last) — @endunless
                        @endforeach
                    </td>
                    <td>{{ $bout->winner?->competitor->displayName() ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
