{{-- Combate actual de un tatami: un participante a cada lado y la puntuación en una tercera columna (en móvil, una debajo de otra).
     Requiere: $bout, $category, $scoreAction. --}}
@php
    $scoreForm = 'score-form-'.$bout->id;
@endphp
@php $totalRounds = $bout->category->bouts()->where('is_bronze', false)->max('round'); @endphp
<div class="d-flex align-items-center flex-wrap gap-2 mb-3 tatami-bout-header">
    <span class="bout-number-tag">Combate {{ $bout->number }}</span>
    <span class="fw-semibold">{{ $bout->category->name }}</span>
    <span class="text-muted">· {{ $bout->is_bronze ? 'Bronce' : \App\Tournament\RoundLabel::for($bout->round, $totalRounds) }}</span>
</div>
<div class="row g-3 tatami-bout">
    @foreach (['a' => $bout->slotA, 'b' => $bout->slotB] as $slot => $registration)
        @php
            $opponentIppon = (bool) $bout->{($slot === 'a' ? 'b' : 'a').'_ippon'};
            $winner = $registration && $registration->id === $bout->winner_id;
        @endphp
        <div class="col-12 col-md-4">
            <div class="card h-100 tatami-competitor tatami-competitor-{{ $slot === 'a' ? 'aka' : 'shiro' }} {{ $winner ? 'is-winner' : '' }}">
                <div class="card-body">
                    @if ($registration->competitor->bib_number)
                        <div class="mb-2"><x-bib :competitor="$registration->competitor" class="bib-tag-lg" /></div>
                    @endif
                    <div class="fs-5 fw-semibold d-flex align-items-center gap-2">
                        <span class="side-chip {{ $slot === 'a' ? 'side-aka' : 'side-shiro' }}" tabindex="0" role="img" aria-label="{{ $slot === 'a' ? 'AKA (rojo)' : 'SHIRO (blanco)' }}"><span class="side-text">{{ $slot === 'a' ? 'AKA' : 'SHIRO' }}</span></span>
                        <span class="text-break">{{ $registration->competitor->displayName() }}</span>
                        @if ($winner)<span class="badge text-bg-success">Ganador</span>@endif
                    </div>
                    <div class="text-muted{{ $registration->competitor->club ? '' : ' fst-italic' }} mb-3">{{ $registration->competitor->club ?: 'Sin club' }}</div>
                    @include('partials.score-participant-controls')
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-12 col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h6">Puntuación</h3>
                <form id="{{ $scoreForm }}" data-score-form method="POST" action="{{ $scoreAction }}" class="small">
                    @csrf @method('PUT')
                    @include('partials.score-form-fields')
                </form>
            </div>
        </div>
    </div>
</div>
