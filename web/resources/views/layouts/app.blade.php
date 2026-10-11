<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Campeonatos') · ChampControl</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/brand/light-icon-logo.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/brand/dark-icon-logo.svg') }}" media="(prefers-color-scheme: dark)">
    <script>
        // Tema claro/oscuro: preferencia guardada o, si no hay, la del sistema. Antes de pintar para evitar parpadeo.
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            if (theme !== 'light' && theme !== 'dark') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-md bg-body border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}" aria-label="ChampControl">
            <img src="{{ asset('img/brand/light-logo-horizontal.svg') }}" alt="ChampControl" height="40" class="brand-logo-light">
            <img src="{{ asset('img/brand/dark-logo-horizontal.svg') }}" alt="" height="40" class="brand-logo-dark" aria-hidden="true">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <li class="nav-item">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="theme-toggle" aria-label="Cambiar entre modo claro y oscuro">
                        <span class="theme-label-light">Modo oscuro</span><span class="theme-label-dark">Modo claro</span>
                    </button>
                </li>
                @auth
                    <li class="nav-item"><a class="nav-link" href="{{ route('panel.events.index') }}">Mis eventos</a></li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="btn btn-link nav-link">Cerrar sesión ({{ auth()->user()->name }})</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a></li>
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
    @yield('content')
</main>
</body>
</html>
