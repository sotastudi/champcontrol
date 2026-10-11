{{-- Cuenta atrás hasta el cierre de inscripciones. Requiere: $closesAt (Carbon). El texto inicial lo calcula el servidor y el JS lo actualiza cada segundo. --}}
@php
    $seconds = max(0, (int) now()->diffInSeconds($closesAt, false));
    $d = intdiv($seconds, 86400);
    $initial = ($d > 0 ? $d.' d ' : '').sprintf('%02d:%02d:%02d', intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
@endphp
<div class="registration-countdown small text-center mt-1 {{ $seconds < 86400 ? 'text-danger fw-semibold' : 'text-body-emphasis' }}" data-countdown-to="{{ $closesAt->toIso8601String() }}" role="timer" aria-label="Tiempo restante para el cierre de inscripciones">
    Cierra en <strong data-countdown-text>{{ $initial }}</strong>
</div>
