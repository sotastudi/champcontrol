{{-- Controles de wazari, ippon y falta de un participante; pertenecen al formulario de puntuación (atributo form).
     Tres bloques en horizontal, cada uno con su nombre completo encima. Requiere: $bout, $slot, $scoreForm, $opponentIppon. --}}
<div class="score-controls d-flex flex-wrap align-items-start gap-3">
    <div class="score-field">
        <div class="score-field-label">Wazari</div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Wazari">
            @foreach ([0, 1, 2] as $n)
                <input type="radio" class="btn-check" name="{{ $slot }}_wazari" id="{{ $scoreForm }}-{{ $slot }}-w{{ $n }}" value="{{ $n }}" form="{{ $scoreForm }}" data-wazari-for="{{ $scoreForm }}-{{ $slot }}" autocomplete="off" @checked($bout->{$slot.'_wazari'} === $n) @disabled($n === 2 && $opponentIppon)>
                <label class="btn btn-outline-secondary" for="{{ $scoreForm }}-{{ $slot }}-w{{ $n }}">{{ $n }}</label>
            @endforeach
        </div>
    </div>
    <div class="score-field">
        <input type="hidden" name="{{ $slot }}_ippon" value="0" form="{{ $scoreForm }}">
        <label class="score-field-label" for="{{ $scoreForm }}-{{ $slot }}-ippon">Ippon</label>
        <div class="score-ippon"><input type="checkbox" class="form-check-input" name="{{ $slot }}_ippon" value="1" form="{{ $scoreForm }}" id="{{ $scoreForm }}-{{ $slot }}-ippon" data-ippon-for="{{ $scoreForm }}-{{ $slot }}" @checked($bout->{$slot.'_ippon'}) @disabled($opponentIppon)></div>
    </div>
    <div class="score-field">
        <label class="score-field-label" for="{{ $scoreForm }}-{{ $slot }}-warnings">Falta</label>
        <select name="{{ $slot }}_warnings" id="{{ $scoreForm }}-{{ $slot }}-warnings" form="{{ $scoreForm }}" class="form-select form-select-sm w-auto">
            @foreach ([0, 1, 2, 3, 4] as $n)<option value="{{ $n }}" @selected($bout->{$slot.'_warnings'} === $n)>{{ $n === 0 ? '—' : $n }}</option>@endforeach
        </select>
    </div>
</div>
