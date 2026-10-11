@php
    $editable ??= false;
    $canRecord ??= true;
    $names = [
        'a' => $bout->slotA?->competitor->displayName(),
        'b' => $bout->slotB?->competitor->displayName(),
    ];
    $empty = $bout->isBye() ? 'Pase' : 'Por determinar';
    $scorable = $editable && $canRecord && $bout->filledSlots() === 2 && ! $bout->isBye() && ! $bout->isWalkover();
    $scoreForm = 'score-form-'.$bout->id;
    $scorePanel = 'score-panel-'.$bout->id;
@endphp
<div class="bout-card mb-2">
    <div class="small text-muted px-2 pt-1">
        {{ $bout->number ? 'Combate '.$bout->number : 'Pase' }}@if ($bout->is_bronze) · Bronce @endif
    </div>
    @foreach (['a' => $bout->slot_a_id, 'b' => $bout->slot_b_id] as $slot => $registrationId)
        @php $registration = $slot === 'a' ? $bout->slotA : $bout->slotB; @endphp
        <div class="slot d-flex justify-content-between gap-2 {{ $registrationId && $registrationId === $bout->winner_id ? 'winner' : '' }}">
            <span class="{{ $registrationId ? '' : 'text-muted fst-italic' }}">
                @if (! $bout->isBye())
                    <span class="side-chip {{ $slot === 'a' ? 'side-aka' : 'side-shiro' }}" tabindex="0" role="img" aria-label="{{ $slot === 'a' ? 'AKA (rojo)' : 'SHIRO (blanco)' }}"><span class="side-text">{{ $slot === 'a' ? 'AKA' : 'SHIRO' }}</span></span>
                @endif
                @if ($registration?->competitor->bib_number)<small class="text-muted">{{ $registration->competitor->bibLabel() }}</small>@endif
                {{ $names[$slot] ?? $empty }}
                @if ($editable && $registration?->isWithdrawn())
                    <small class="text-danger">({{ $registration->isDisqualified() ? 'descalificado' : 'retirado' }})</small>
                @endif
                @if ($registration?->competitor->club)
                    <small class="d-block text-muted">{{ $registration->competitor->club }}</small>
                @endif
                @php
                    // W: wazari (1-2), I: ippon, F: faltas (1-4, solo en el panel).
                    $marks = [];
                    if ($bout->{$slot.'_wazari'} > 0) { $marks[] = 'W:'.$bout->{$slot.'_wazari'}; }
                    if ($bout->{$slot.'_ippon'}) { $marks[] = 'I'; }
                    if ($editable && $bout->{$slot.'_warnings'} > 0) { $marks[] = 'F:'.$bout->{$slot.'_warnings'}; }
                @endphp
                @if ($marks)
                    <small class="d-block fw-semibold score-marks">{{ implode(' ', $marks) }}</small>
                @endif
                @if ($scorable)
                    {{-- Controles de wazari, ippon y falta de este participante: viven en el formulario de puntuación (atributo form). --}}
                    <div class="collapse {{ $scorePanel }} no-print small mt-1">
                        <div class="score-controls d-flex flex-column gap-1 align-items-start">
                            <label>W
                                <select name="{{ $slot }}_wazari" form="{{ $scoreForm }}" class="form-select form-select-sm d-inline-block w-auto" aria-label="Wazari" data-wazari-for="{{ $scoreForm }}-{{ $slot }}">
                                    @foreach ([0, 1, 2] as $n)<option value="{{ $n }}" @selected($bout->{$slot.'_wazari'} === $n)>{{ $n === 0 ? '—' : $n }}</option>@endforeach
                                </select>
                            </label>
                            <input type="hidden" name="{{ $slot }}_ippon" value="0" form="{{ $scoreForm }}">
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" name="{{ $slot }}_ippon" value="1" form="{{ $scoreForm }}" id="{{ $scoreForm }}-{{ $slot }}-ippon" data-ippon-for="{{ $scoreForm }}-{{ $slot }}" @checked($bout->{$slot.'_ippon'})> I (ippon)</label>
                            <label>F
                                <select name="{{ $slot }}_warnings" form="{{ $scoreForm }}" class="form-select form-select-sm d-inline-block w-auto" aria-label="Falta">
                                    @foreach ([0, 1, 2, 3, 4] as $n)<option value="{{ $n }}" @selected($bout->{$slot.'_warnings'} === $n)>{{ $n === 0 ? '—' : $n }}</option>@endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                @endif
            </span>
            @if ($scorable)
                <form method="POST" action="{{ route('panel.bouts.result', [$event, $category, $bout]) }}" class="no-print">
                    @csrf @method('PUT')
                    <input type="hidden" name="winner_id" value="{{ $registrationId }}">
                    <button class="btn btn-sm {{ $registrationId === $bout->winner_id ? 'btn-success' : 'btn-outline-primary' }}">Gana</button>
                </form>
            @endif
        </div>
    @endforeach
    @if ($bout->isWalkover())
        <div class="small text-muted px-2 pb-1">Gana por incomparecencia</div>
    @endif
    @php
        $notes = [];
        if ($bout->abandoned_slot) { $notes[] = 'Abandona '.($bout->abandoned_slot === 'a' ? '🟥' : '⬜'); }
        if ($bout->judge_votes) {
            $tally = array_count_values(array_filter($bout->judge_votes, fn ($v) => $v !== null));
            $notes[] = 'Jueces: 🟥 '.($tally['aka'] ?? 0).' · ⬜ '.($tally['shiro'] ?? 0).' · hikiwake '.($tally['hikiwake'] ?? 0);
        }
    @endphp
    @if ($notes)
        <div class="small text-muted px-2 pb-1">{{ implode(' · ', $notes) }}</div>
    @endif
    @if ($scorable)
        @php
            $judges = in_array((int) $category->referees, [2, 3, 5], true) ? (int) $category->referees : 3;
            $votes = $bout->judge_votes ?? [];
            $voteOptions = [['shiro', 'flag-shiro', 'SHIRO: bandera blanca'], ['aka', 'flag-aka', 'AKA: bandera roja'], ['hikiwake', 'flag-hikiwake', 'Hikiwake, empate: blanca y roja']];
        @endphp
        <div class="px-2 pb-2 no-print">
            <button type="button" class="btn btn-link btn-sm p-0 small" data-bs-toggle="collapse" data-bs-target=".{{ $scorePanel }}" aria-expanded="false">Puntuación</button>
            <form id="{{ $scoreForm }}" method="POST" action="{{ route('panel.bouts.score', [$event, $category, $bout]) }}" class="collapse {{ $scorePanel }} small mt-2">
                @csrf @method('PUT')
                <div class="mb-1">
                    <label>Abandona
                        <select name="abandoned_slot" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="">Nadie</option>
                            <option value="a" @selected($bout->abandoned_slot === 'a')>🟥 (gana ⬜)</option>
                            <option value="b" @selected($bout->abandoned_slot === 'b')>⬜ (gana 🟥)</option>
                        </select>
                    </label>
                </div>
                @for ($j = 0; $j < $judges; $j++)
                    @php $current = $votes[$j] ?? null; @endphp
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="min-width: 4.5rem">Juez {{ $j + 1 }}</span>
                        <div class="btn-group btn-group-sm judge-votes" role="group" aria-label="Voto del juez {{ $j + 1 }}" data-judge-group>
                            @foreach ($voteOptions as [$value, $flag, $label])
                                <input type="radio" class="btn-check" name="judge_votes[{{ $j }}]" id="vote-{{ $bout->id }}-{{ $j }}-{{ $value }}" value="{{ $value }}" autocomplete="off" @checked($current === $value)>
                                <label class="btn btn-outline-secondary" for="vote-{{ $bout->id }}-{{ $j }}-{{ $value }}" title="{{ $label }}"><span class="flag {{ $flag }}" aria-hidden="true"></span><span class="visually-hidden">{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                @endfor
                <div class="d-flex align-items-center gap-2 mb-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Guardar puntuación</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle fw-bold score-help-toggle" data-bs-toggle="collapse" data-bs-target="#score-help-{{ $bout->id }}" aria-expanded="false" aria-controls="score-help-{{ $bout->id }}" title="Más información" aria-label="Más información sobre la puntuación">?</button>
                </div>
                <div class="collapse" id="score-help-{{ $bout->id }}">
                    <p class="text-muted mb-0">Jueces: bandera blanca = SHIRO (⬜), roja = AKA (🟥), blanca y roja = hikiwake (pulsa otra vez para quitar el voto). 2 wazari = 1 ippon. El ippon gana al instante; si no, decide la mayoría de jueces (el hikiwake no suma) y, sin jueces, la ventaja de wazari. Las faltas (F) solo se registran y solo las ve el organizador. En el cuadro: W = wazari, I = ippon, F = faltas.</p>
                </div>
            </form>
        </div>
    @endif
</div>
