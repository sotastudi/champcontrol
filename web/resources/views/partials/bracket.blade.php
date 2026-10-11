@php
    $editable ??= false;
    $totalRounds = $bouts->where('is_bronze', false)->max('round');
    $rounds = $bouts->where('is_bronze', false)->groupBy('round')->sortKeys();
    $bronze = $bouts->firstWhere('is_bronze', true);
@endphp
<div class="bracket d-flex pb-3">
    @foreach ($rounds as $round => $roundBouts)
        @php $sorted = $roundBouts->sortBy('position')->values(); $hasNext = $round < $totalRounds; @endphp
        <div class="bracket-round flex-shrink-0 {{ $hasNext ? 'has-next' : '' }}">
            <h3 class="h6 text-center text-muted">{{ \App\Tournament\RoundLabel::for($round, $totalRounds) }}</h3>
            <div class="bracket-bouts">
                @if ($hasNext)
                    @foreach ($sorted->chunk(2) as $pair)
                        <div class="bracket-pair">
                            @foreach ($pair as $bout)
                                <div class="bracket-slot">@include('partials.bout-card', ['bout' => $bout])</div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    @foreach ($sorted as $bout)
                        <div class="bracket-slot">@include('partials.bout-card', ['bout' => $bout])</div>
                    @endforeach
                @endif
            </div>
        </div>
    @endforeach
</div>
@if ($bronze)
    <h3 class="h6 text-muted mt-3">Combate por el bronce</h3>
    <div style="max-width: 20rem">@include('partials.bout-card', ['bout' => $bronze])</div>
@endif
