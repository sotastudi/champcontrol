@props(['club', 'event', 'logo' => true])
{{-- Nombre del club con su logo (si el club está aprobado en el evento y lo ha subido); sin logo, solo el nombre. --}}
@php($logoUrl = $logo ? $event->clubLogoUrl($club) : null)
<span {{ $attributes->class(['club-name']) }}>@if ($logoUrl)<img src="{{ $logoUrl }}" alt="Logo de {{ $club }}" class="club-logo" loading="lazy">@endif{{ $club }}</span>
