@props(['competitor'])
{{-- Número de competidor en un recuadro fácil de localizar; no pinta nada si aún no se ha asignado. --}}
@if ($competitor?->bib_number)
    <span {{ $attributes->class(['bib-tag']) }} title="Número de competidor">Nº {{ $competitor->bib_number }}</span>
@endif
