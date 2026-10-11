@php
    $editable ??= false;
    $totalRounds = $bouts->where('is_bronze', false)->max('round');
    $rounds = $bouts->where('is_bronze', false)->groupBy('round')->sortKeys();
    $bronze = $bouts->firstWhere('is_bronze', true);
@endphp
<div class="bracket d-flex gap-3 overflow-auto pb-3">
    @foreach ($rounds as $round => $roundBouts)
        <div class="bracket-round flex-shrink-0">
            <h3 class="h6 text-center text-muted">{{ \App\Tournament\RoundLabel::for($round, $totalRounds) }}</h3>
            @foreach ($roundBouts->sortBy('position') as $bout)
                @include('partials.bout-card', ['bout' => $bout])
            @endforeach
        </div>
    @endforeach
</div>
@if ($bronze)
    <h3 class="h6 text-muted mt-3">Combate por el bronce</h3>
    <div style="max-width: 20rem">@include('partials.bout-card', ['bout' => $bronze])</div>
@endif
