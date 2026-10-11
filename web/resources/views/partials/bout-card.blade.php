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
                    <span class="badge {{ $slot === 'a' ? 'text-bg-danger' : 'bg-light text-dark border' }}">{{ $slot === 'a' ? 'AKA' : 'SHIRO' }}</span>
                @endif
                @if ($registration?->competitor->bib_number)<small class="text-muted">{{ $registration->competitor->bibLabel() }}</small>@endif
                {{ $names[$slot] ?? $empty }}
                @if ($editable && $registration?->isWithdrawn())
                    <small class="text-danger">({{ $registration->isDisqualified() ? 'descalificado' : 'retirado' }})</small>
                @endif
                @if ($registration?->competitor->club)
                    <small class="d-block text-muted">{{ $registration->competitor->club }}</small>
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
        foreach (['a' => 'AKA', 'b' => 'SHIRO'] as $side => $label) {
            if ($bout->{$side.'_ippon'}) { $notes[] = 'Ippon '.$label; }
            elseif ($bout->{$side.'_wazari'} > 0) { $notes[] = 'Wazari '.$label.' ×'.$bout->{$side.'_wazari'}; }
            if ($editable && $bout->{$side.'_warnings'} > 0) { $notes[] = '⚠ '.$bout->{$side.'_warnings'}.' amonest. '.$label; }
        }
        if ($bout->abandoned_slot) { $notes[] = 'Abandona '.($bout->abandoned_slot === 'a' ? 'AKA' : 'SHIRO'); }
        if ($bout->judge_votes) {
            $tally = array_count_values($bout->judge_votes);
            $notes[] = 'Jueces: AKA '.($tally['aka'] ?? 0).' · SHIRO '.($tally['shiro'] ?? 0).' · hikiwake '.($tally['hikiwake'] ?? 0);
        }
    @endphp
    @if ($notes)
        <div class="small text-muted px-2 pb-1">{{ implode(' · ', $notes) }}</div>
    @endif
    @if ($editable && $canRecord && $bout->filledSlots() === 2 && ! $bout->isBye() && ! $bout->isWalkover())
        @php $judges = max(1, min(9, (int) ($category->referees ?: 3))); $votes = $bout->judge_votes ?? []; @endphp
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
                        <label>Amonest.
                            <input type="number" min="0" max="9" name="{{ $side }}_warnings" value="{{ $bout->{$side.'_warnings'} }}" class="form-control form-control-sm d-inline-block" style="width: 4rem">
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
                    <div class="mb-1">
                        <label>Juez {{ $j + 1 }}
                            <select name="judge_votes[]" class="form-select form-select-sm d-inline-block w-auto">
                                <option value="">—</option>
                                @foreach (\App\Enums\JudgeVote::cases() as $vote)
                                    <option value="{{ $vote->value }}" @selected(($votes[$j] ?? null) === $vote->value)>{{ $vote->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endfor
                <p class="text-muted mb-2">2 wazari = 1 ippon. El ippon gana al instante; si no, decide la mayoría de jueces (el hikiwake no suma) y, sin jueces, la ventaja de wazari. Las amonestaciones solo se registran.</p>
                <button type="submit" class="btn btn-sm btn-outline-primary">Guardar puntuación</button>
            </form>
        </details>
    @endif
</div>
