@extends('layouts.app')
@section('title', 'Mi perfil')
@section('content')
<div class="col-md-8 col-lg-6 mx-auto">
    <h1 class="h3 mb-3">Mi perfil</h1>

    @if ($user->isClubAccount())
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Logo del club</h2>
                <p class="text-muted small">Se muestra junto al nombre del club en las vistas de tatami de los campeonatos donde estés aprobado. JPG, PNG o WebP, máximo 2 MB.</p>
                @if ($user->hasLogo())
                    <div class="mb-3"><img src="{{ route('panel.profile.logo.show') }}?v={{ $user->updated_at?->timestamp }}" alt="Logo actual de {{ $user->name }}" class="img-thumbnail" style="max-height: 8rem"></div>
                @endif
                <form method="POST" enctype="multipart/form-data" action="{{ route('panel.profile.logo') }}">@csrf @method('PUT')
                    <div class="mb-3">
                        <label for="logo" class="form-label">{{ $user->hasLogo() ? 'Cambiar logo' : 'Subir logo' }}</label>
                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control @error('logo') is-invalid @enderror" required>
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary">Guardar logo</button>
                </form>
                @if ($user->hasLogo())
                    <form method="POST" action="{{ route('panel.profile.logo') }}" class="mt-2">@csrf @method('PUT')
                        <input type="hidden" name="remove_logo" value="1">
                        <button class="btn btn-outline-danger btn-sm">Quitar logo</button>
                    </form>
                @endif
                <hr>
                <h2 class="h5 mb-3">Color del club</h2>
                <p class="text-muted small">Colorea el número de tus participantes en los listados para distinguir los clubes. Si no eliges ninguno se propone uno automático.</p>
                <form method="POST" action="{{ route('panel.profile.color') }}" class="d-flex flex-wrap align-items-center gap-3">@csrf @method('PUT')
                    <input type="color" name="club_color" value="{{ $user->club_color ?? '#0072b2' }}" class="form-control form-control-color" aria-label="Color del club" oninput="document.getElementById('auto_color').checked = false">
                    <div class="form-check"><input type="checkbox" class="form-check-input" id="auto_color" name="auto_color" value="1" @checked(! $user->club_color)><label class="form-check-label" for="auto_color">Color automático</label></div>
                    <button class="btn btn-primary">Guardar color</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Datos de la cuenta</h2>
            <form method="POST" action="{{ route('panel.profile.update') }}">@csrf @method('PUT')
                @if ($user->username)
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="{{ $user->username }}" disabled>
                    </div>
                @endif
                <x-field name="name" label="Nombre" :value="$user->name" required />
                <x-field name="email" type="email" label="Correo electrónico" :value="$user->email" :required="! $user->isTatamiAccount()" />
                <button class="btn btn-primary">Guardar datos</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3">Cambiar contraseña</h2>
            <form method="POST" action="{{ route('panel.profile.password') }}">@csrf @method('PUT')
                <x-field name="current_password" type="password" label="Contraseña actual" required autocomplete="current-password" />
                <x-field name="password" type="password" label="Nueva contraseña" help="Mínimo 8 caracteres" required autocomplete="new-password" />
                <x-field name="password_confirmation" type="password" label="Repite la nueva contraseña" required autocomplete="new-password" />
                <button class="btn btn-primary">Cambiar contraseña</button>
            </form>
        </div>
    </div>
</div>
@endsection
