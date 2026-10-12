@props(['competitor', 'event' => null])
{{-- Número de competidor en un recuadro fácil de localizar; no pinta nada si aún no se ha asignado. --}}
@if ($competitor?->bib_number)
    @php
        $color = $event?->clubColor($competitor->club);
        if ($color) {
            [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
            $ink = ($r * 299 + $g * 587 + $b * 114) / 1000 > 150 ? '#000' : '#fff';
        }
    @endphp
    <span {{ $attributes->class(['bib-tag', 'bib-tag-club' => $color])->when($color, fn ($a) => $a->merge(['style' => "background:{$color};border-color:{$color};color:{$ink}"])) }} title="Número de competidor">Nº {{ $competitor->bib_number }}</span>
@endif
