<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-mode="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Campeonatos') · ChampControl</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/brand/light-icon-logo.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/brand/dark-icon-logo.svg') }}" media="(prefers-color-scheme: dark)">
    <script>
        // Modo de tema: light | dark | auto (sistema). Se aplica antes de pintar para evitar parpadeo.
        (function () {
            var mode = null;
            try { mode = localStorage.getItem('theme'); } catch (e) {}
            if (mode !== 'light' && mode !== 'dark') { mode = 'auto'; }
            var theme = mode === 'auto'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-theme-mode', mode);
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<svg xmlns="http://www.w3.org/2000/svg" class="d-none" aria-hidden="true">
    <defs>
        <clipPath id="bulb-left-half"><rect x="0" y="0" width="12" height="24"/></clipPath>
    </defs>
    {{-- Bombilla encendida (claro), apagada (oscuro) y mitad/mitad (automático). --}}
    {{-- Estado de sincronización: nube con check (todo guardado), nube (pendiente) y triángulo de aviso. --}}
    <symbol id="cloud-check" viewBox="0 0 24 24">
        <path d="M7 19a4.5 4.5 0 0 1-.6-8.96A6.5 6.5 0 0 1 18.9 9.6 4.7 4.7 0 0 1 18 19H7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M9 13.5l2.2 2.2L15.5 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="cloud-pending" viewBox="0 0 24 24">
        <path d="M7 19a4.5 4.5 0 0 1-.6-8.96A6.5 6.5 0 0 1 18.9 9.6 4.7 4.7 0 0 1 18 19H7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-dasharray="3 2"/>
    </symbol>
    <symbol id="warn" viewBox="0 0 24 24">
        <path d="M12 3.5 22 20.5H2L12 3.5z" fill="currentColor"/>
        <path d="M12 9.5v5M12 17.5h.01" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
    </symbol>
    <symbol id="user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.7"/>
        <path d="M4 20a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
    </symbol>
    <symbol id="bulb-on" viewBox="0 0 24 24">
        <path d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2z" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M9.5 21h5M10 18v0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </symbol>
    <symbol id="bulb-off" viewBox="0 0 24 24">
        <path d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M9.5 21h5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </symbol>
    <symbol id="bulb-half" viewBox="0 0 24 24">
        <path d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2z" fill="currentColor" clip-path="url(#bulb-left-half)"/>
        <path d="M12 2a7 7 0 0 0-4 12.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26A7 7 0 0 0 12 2z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M9.5 21h5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </symbol>
</svg>
<nav class="navbar navbar-expand-md bg-body border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}" aria-label="ChampControl">
            {{-- Logo horizontal desde tablet (md) hacia arriba; icono en móvil. Claro/oscuro según el tema activo. --}}
            <img src="{{ asset('img/brand/light-logo-horizontal.svg') }}" alt="ChampControl" height="40" class="brand-logo brand-full brand-light">
            <img src="{{ asset('img/brand/dark-logo-horizontal.svg') }}" alt="" height="40" class="brand-logo brand-full brand-dark" aria-hidden="true">
            <img src="{{ asset('img/brand/light-icon-logo.svg') }}" alt="ChampControl" height="36" class="brand-logo brand-icon brand-light">
            <img src="{{ asset('img/brand/dark-icon-logo.svg') }}" alt="" height="36" class="brand-logo brand-icon brand-dark" aria-hidden="true">
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto order-md-last nav-actions">
        @auth
            <button type="button" id="sync-indicator" class="btn btn-sm btn-link text-decoration-none d-flex align-items-center gap-1 sync-indicator" data-sync-state="synced" aria-live="polite" title="Todo guardado en el servidor">
                <svg class="sync-cloud-ok text-success" width="22" height="22" aria-hidden="true"><use href="#cloud-check"/></svg>
                <span class="sync-pending d-none align-items-center gap-1 text-warning-emphasis">
                    <svg class="text-warning" width="18" height="18" aria-hidden="true"><use href="#warn"/></svg>
                    <svg class="text-warning" width="22" height="22" aria-hidden="true"><use href="#cloud-pending"/></svg>
                    <span class="sync-count small fw-semibold"></span>
                </span>
                <span class="visually-hidden sync-label">Todo guardado en el servidor</span>
            </button>
            <div id="sync-panel" class="card shadow-sm d-none no-print" role="dialog" aria-label="Resultados pendientes de sincronizar"></div>
        @endauth
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" id="theme-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tema de color">
                    <svg class="theme-icon theme-icon-light" width="18" height="18" aria-hidden="true"><use href="#bulb-on"/></svg>
                    <svg class="theme-icon theme-icon-dark" width="18" height="18" aria-hidden="true"><use href="#bulb-off"/></svg>
                    <svg class="theme-icon theme-icon-auto" width="18" height="18" aria-hidden="true"><use href="#bulb-half"/></svg>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="theme-menu">
                    <li><button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-value="light"><svg width="18" height="18" aria-hidden="true"><use href="#bulb-on"/></svg> Claro</button></li>
                    <li><button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-value="dark"><svg width="18" height="18" aria-hidden="true"><use href="#bulb-off"/></svg> Oscuro</button></li>
                    <li><button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-value="auto"><svg width="18" height="18" aria-hidden="true"><use href="#bulb-half"/></svg> Automático (sistema)</button></li>
                </ul>
            </div>
            {{-- Usuario: solo el icono; el texto aparece al pasar el cursor o al tocar (en tablet, un primer toque lo muestra y el segundo entra). --}}
            <a class="btn btn-outline-secondary btn-sm d-flex align-items-center user-chip" href="{{ auth()->check() ? route('panel.events.index') : route('login') }}" aria-label="{{ auth()->check() ? 'Mi panel' : 'Iniciar sesión' }}" data-user-chip>
                <svg width="18" height="18" aria-hidden="true"><use href="#user"/></svg>
                <span class="user-chip-text">{{ auth()->check() ? 'Mi panel' : 'Iniciar sesión' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Menú">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                @auth
                    <li class="nav-item"><a class="nav-link" href="{{ route('panel.events.index') }}">{{ auth()->user()->isTatamiAccount() ? 'Mis tatamis' : 'Mis eventos' }}</a></li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="btn btn-link nav-link">Cerrar sesión ({{ auth()->user()->name }})</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Registrarse</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
<main class="container pb-5">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @error('error')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    {{-- Errores de campos de formularios del cuadro (resultado, bajas, intercambio) que no se pintan junto al campo. --}}
    @foreach (['winner_id', 'registration_id', 'type', 'reason', 'registration_a', 'registration_b', 'judge_votes', 'abandoned_slot', 'a_wazari', 'b_wazari', 'a_warnings', 'b_warnings'] as $field)
        @error($field)
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
    @endforeach
    @yield('content')
</main>
</body>
</html>
