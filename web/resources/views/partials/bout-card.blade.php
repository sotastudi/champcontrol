@php
    $editable ??= false;
    $canRecord ??= true;
    $names = [
        'a' => $bout->slotA?->competitor->displayName(),
        'b' => $bout->slotB?->competitor->displayName(),
    ];
    $empty = $bout->isBye() ? 'Pase' : 'Por determinar';
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
                    <span class="side-chip {{ $slot === 'a' ? 'side-aka' : 'side-shiro' }}" tabindex="0" role="img" data-bs-toggle="tooltip" data-bs-title="{{ $slot === 'a' ? 'AKA (rojo)' : 'SHIRO (blanco)' }}" aria-label="{{ $slot === 'a' ? 'AKA (rojo)' : 'SHIRO (blanco)' }}"></span>
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
            </span>
            @if ($editable && $canRecord && $bout->filledSlots() === 2 && ! $bout->isBye() && ! $bout->isWalkover())
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
        if ($bout->abandoned_slot) { $notes[] = 'Abandona '.($bout->abandoned_slot === 'a' ? 'AKA' : 'SHIRO'); }
        if ($bout->judge_votes) {
            $tally = array_count_values(array_filter($bout->judge_votes, fn ($v) => $v !== null));
            $notes[] = 'Jueces: AKA '.($tally['aka'] ?? 0).' · SHIRO '.($tally['shiro'] ?? 0).' · hikiwake '.($tally['hikiwake'] ?? 0);
        }
    @endphp
    @if ($notes)
        <div class="small text-muted px-2 pb-1">{{ implode(' · ', $notes) }}</div>
    @endif
    @if ($editable && $canRecord && $bout->filledSlots() === 2 && ! $bout->isBye() && ! $bout->isWalkover())
        @php
            $judges = in_array((int) $category->referees, [2, 3, 5], true) ? (int) $category->referees : 3;
            $votes = $bout->judge_votes ?? [];
            $voteOptions = [['shiro', 'flag-shiro', 'SHIRO: bandera blanca'], ['aka', 'flag-aka', 'AKA: bandera roja'], ['hikiwake', 'flag-hikiwake', 'Hikiwake, empate: blanca y roja']];
        @endphp
        <details class="px-2 pb-2 no-print">
            <summary class="small text-primary" style="cursor: pointer">Puntuación</summary>
            <form method="POST" action="{{ route('panel.bouts.score', [$event, $category, $bout]) }}" class="small mt-2">
                @csrf @method('PUT')
                @foreach (['a' => 'AKA (rojo)', 'b' => 'SHIRO (blanco)'] as $side => $label)
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <strong class="me-1" style="min-width: 6.5rem">{{ $label }}</strong>
                        <label>Wazari
                            <select name="{{ $side }}_wazari" class="form-select form-select-sm d-inline-block w-auto">
                                @foreach ([0, 1, 2] as $n)<option value="{{ $n }}" @selected($bout->{$side.'_wazari'} === $n)>{{ $n }}</option>@endforeach
                            </select>
                        </label>
                        <input type="hidden" name="{{ $side }}_ippon" value="0">
                        <label class="form-check-label"><input type="checkbox" class="form-check-input" name="{{ $side }}_ippon" value="1" @checked($bout->{$side.'_ippon'})> Ippon</label>
                        <label>Falta
                            <select name="{{ $side }}_warnings" class="form-select form-select-sm d-inline-block w-auto">
                                @foreach ([0, 1, 2, 3, 4] as $n)<option value="{{ $n }}" @selected($bout->{$side.'_warnings'} === $n)>{{ $n === 0 ? '—' : $n }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                @endforeach
                <div class="mb-1">
                    <label>Abandona
                        <select name="abandoned_slot" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="">Nadie</option>
                            <option value="a" @selected($bout->abandoned_slot === 'a')>AKA (gana SHIRO)</option>
                            <option value="b" @selected($bout->abandoned_slot === 'b')>SHIRO (gana AKA)</option>
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
                <p class="text-muted mb-2">Jueces: bandera blanca = SHIRO, roja = AKA, blanca y roja = hikiwake (pulsa otra vez para quitar el voto). 2 wazari = 1 ippon. El ippon gana al instante; si no, decide la mayoría de jueces (el hikiwake no suma) y, sin jueces, la ventaja de wazari. Las faltas (F) solo se registran y solo las ve el organizador. En el cuadro: W = wazari, I = ippon, F = faltas.</p>
                <button type="submit" class="btn btn-sm btn-outline-primary">Guardar puntuación</button>
            </form>
        </details>
    @endif
</div>
