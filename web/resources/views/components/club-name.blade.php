@props(['club', 'event'])
{{-- Nombre del club con su logo (si el club está aprobado en el evento y lo ha subido); sin logo, solo el nombre. --}}
@php($logo = $event->clubLogoUrl($club))
<span {{ $attributes->class(['club-name']) }}>@if ($logo)<img src="{{ $logo }}" alt="Logo de {{ $club }}" class="club-logo" loading="lazy">@endif{{ $club }}</span>
