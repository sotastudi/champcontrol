{{-- Campos del formulario de puntuación: abandono, votos de los jueces, guardar y ayuda. Requiere: $bout, $category, $scoreForm. --}}
@php
    $judges = in_array((int) $category->referees, [2, 3, 5], true) ? (int) $category->referees : 3;
    $votes = $bout->judge_votes ?? [];
    $voteOptions = [['shiro', 'flag-shiro', 'SHIRO: bandera blanca'], ['aka', 'flag-aka', 'AKA: bandera roja'], ['hikiwake', 'flag-hikiwake', 'Empate: bandera partida, blanca y roja']];
@endphp
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
    <button type="submit" class="btn btn-sm btn-outline-primary" data-score-save>Guardar puntuación</button>
    <span data-score-status aria-live="polite"></span>
    <small class="text-danger d-none" data-score-hint>Marca la bandera de cada juez (o ippon / abandono).</small>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle fw-bold score-help-toggle" data-bs-toggle="collapse" data-bs-target="#score-help-{{ $bout->id }}" aria-expanded="false" aria-controls="score-help-{{ $bout->id }}" title="Más información" aria-label="Más información sobre la puntuación">?</button>
</div>
<div class="collapse" id="score-help-{{ $bout->id }}">
    <p class="text-muted mb-0">Jueces: bandera blanca <span class="flag flag-shiro flag-sm" aria-label="blanca"></span> = SHIRO, roja <span class="flag flag-aka flag-sm" aria-label="roja"></span> = AKA, bandera partida <span class="flag flag-hikiwake flag-sm" aria-label="empate"></span> = empate (pulsa otra vez para quitar el voto). 2 wazari = 1 ippon. El ippon gana al instante; si no, decide la mayoría de jueces (el empate no suma) y es obligatorio marcar la bandera de todos los jueces salvo con ippon o abandono. Las faltas (F) solo se registran y solo las ve el organizador. En el cuadro: W = wazari, I = ippon, F = faltas.</p>
</div>
