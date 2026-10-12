# KumiteControl v1 — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicación web Laravel para que un organizador cree un evento con categorías, inscriba y confirme competidores, genere un cuadro de eliminación directa con pases, registre resultados (final y bronce) y publique la clasificación sin exponer datos privados.

**Architecture:** Laravel 13 monolito con Blade + Bootstrap 5.3 (Vite) y MySQL. La lógica de torneo vive en `app/Tournament/` (servicios sin dependencias de HTTP/Blade: `BracketGenerator`, `BracketAdvancer`, `ResultRecorder`, `BracketAdjuster`, `PodiumCalculator`) y opera sobre el modelo persistente `Bout` (tabla `bouts`, enlaces `next_bout_id`/`loser_next_bout_id`). Las inscripciones se gestionan en `app/Registrations/RegistrationService`. La autorización es una única policy (`EventPolicy::manage`) aplicada por middleware `can:manage,event` a todo el grupo `panel/eventos/{event}` con `scopeBindings()`; un test recorre todas las rutas para garantizarlo.

**Tech Stack:** PHP 8.4 (MAMP `php8.4.17`), Laravel 13.17, MySQL (MAMP) y SQLite en memoria para tests, PHPUnit 12, Bootstrap 5.3 + Popper, Vite 8, Node 26 (`/usr/local/opt/node@26`). Sin Livewire/Breeze/Tailwind/Boost ni paquetes extra.

**Spec:** el prompt del usuario (sesión del 2026-10-11) + `/README.md` del repositorio (raíz). Estructura real del repo: el proyecto Laravel ya existe en `web/` (esqueleto sin migraciones propias, con Tailwind que hay que sustituir).

## Global Constraints

- Todos los comandos se ejecutan desde `web/` tras `source scripts/dev-env.sh` (PHP 8.4 + Node en el PATH). En el tool Bash cada llamada es un shell nuevo: encadenar `source scripts/dev-env.sh && <comando>`.
- PHP 8.4 obligatorio (terminal y Composer). La terminal del usuario trae por defecto PHP 8.3.30 (alias MAMP) y Composer de Homebrew con PHP 8.5: no usarlos tal cual.
- Laravel 13, Blade, Bootstrap 5.3, Vite, interfaz en español (`APP_LOCALE=es`), responsive. JavaScript solo si aporta; el cuadro se dibuja con Blade+CSS (sin JS).
- Apache/MAMP debe servir **`kumitecontrol/web/public`** (no la raíz del repo ni `web/`). Host `kumitecontrol.test`.
- Credenciales MySQL: las del `.env` local ya configurado con MAMP; no inventarlas ni tocarlas. `.env` nunca se versiona; `.env.example` sin secretos.
- Los tests usan SQLite en memoria (`phpunit.xml`): nunca tocan la base MySQL de desarrollo.
- Nada de `match` como nombre de clase (palabra reservada): el combate se llama `Bout` / tabla `bouts`.
- Alcance excluido: pagos, marcador en vivo, tatamis, calendario, rankings, equipos, cabezas de serie, liguillas, doble eliminación, app móvil, equipos de organizadores. Retiradas/descalificaciones: **no** se implementan (decisión pendiente documentada).
- Regla del bronce configurable; la propuesta inicial (combate entre perdedores de semifinal) se marca siempre como **DECISIÓN PENDIENTE** en la UI y en `docs/decisiones-pendientes.md`.
- Antes de cada commit: `vendor/bin/pint --dirty` y `php artisan test`. Mensajes de commit terminan con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- Estilo de modelos: el de `app/Models/User.php` (atributo `#[Fillable([...])]`, método `casts()`).
- Rama de trabajo: `develop` (ya activa).

## Review Focus

Entradas que el spec implica pero que ninguna tarea cubriría por defecto; cada una tiene su test en la tarea indicada.

1. **Mismo competidor con otra escritura** (`"JOSÉ  García"` vs `"jose garcia"`, misma fecha de nacimiento) debe detectarse como el mismo y rechazar la inscripción duplicada en la categoría — Task 6.
2. **Cancelar una inscripción que ya está en un cuadro**: con resultados se bloquea; sin resultados se permite y el cuadro se marca "desactualizado" para regenerar — Task 9.
3. **Edad en la fecha del evento**: cumpleaños exactamente el día del evento cuenta como cumplido; el día anterior no; 29 de febrero — Task 5.
4. **Ganador manipulado**: id de otra categoría/evento, combate sin ambos participantes, pase automático, ganador repetido → rechazado sin cambiar nada (y repetir el mismo ganador es idempotente) — Tasks 10 y 12.
5. **Formulario público**: evento privado/cerrado/deshabilitado, campo `status=confirmed` inyectado, y menores de edad → 404, ignorado o rechazado respectivamente — Task 7.

Además, la exportación CSV neutraliza fórmulas (`=`, `+`, `-`, `@`) — Task 8.

## Decisiones de diseño (resumen)

| Tema | Decisión |
| --- | --- |
| Competidor | Pertenece a un **evento** (`competitors.event_id`), no es global: evita compartir datos personales entre organizadores. Identidad = nombre normalizado + apellidos + fecha de nacimiento dentro del evento. |
| Menores | El formulario público rechaza competidores con edad < `PUBLIC_FORM_MIN_AGE` (18) en la fecha del evento hasta definir el consentimiento del representante (`docs/menores.md`). Valor conservador, a confirmar. |
| Byes | `size` = potencia de 2 ≥ n. Orden de siembra estándar (`1,size,…`) sobre el sorteo aleatorio ⇒ nunca dos pases enfrentados (n > size/2). Un combate con `required_participants = 1` es un pase y se resuelve solo. |
| Ajuste del cuadro | Solo intercambio de dos participantes de primera ronda, sin resultados. La estructura de pases no cambia. |
| Bronce | `BronzeRule::BronzeMatch` (propuesta, pendiente) o `SharedBronze` (dos terceros). Con 3 competidores el combate por el bronce tiene un solo participante y se resuelve como pase. |
| Correcciones | Se puede cambiar el ganador de un combate solo si los combates dependientes no tienen resultado. Evento `finalizado` bloquea resultados (hay que reabrirlo). |
| CSV | Delimitador `;`, UTF-8 con BOM (Excel en español), neutralización de fórmulas. |

## Estructura de ficheros

```
web/
  scripts/dev-env.sh                         # PATH PHP 8.4 + Node
  config/kumitecontrol.php                    # public_form_min_age
  lang/es/{auth,passwords,validation}.php
  app/Enums/{EventStatus,RegistrationStatus,Sex,BronzeRule}.php  + Concerns/HasOptions.php
  app/Models/{User,Event,Category,Competitor,Registration,Bout}.php
  app/Policies/EventPolicy.php
  app/Registrations/{RegistrationService,RegistrationException}.php
  app/Tournament/{BracketGenerator,BracketAdvancer,ResultRecorder,BracketAdjuster,PodiumCalculator,Placement,RoundLabel,TournamentException}.php
  app/Support/Csv.php
  app/Http/Controllers/Auth/*                # Register, Login, PasswordResetLink, NewPassword
  app/Http/Controllers/{HomeController,PublicEventController,PublicCategoryController,PublicRegistrationController}.php
  app/Http/Controllers/Panel/{Event,Category,Registration,Competitor,Bracket,BoutResult,Publication,Export}Controller.php
  app/Http/Requests/{EventRequest,CategoryRequest,RegistrationRequest,ManualRegistrationRequest,CompetitorRequest}.php
  database/migrations/*  database/factories/*
  resources/views/{layouts,components,auth,home,public,panel,partials}/...
  tests/Feature/*  tests/Unit/*  tests/Concerns/BuildsBrackets.php
docs/{decisiones-pendientes,menores}.md
```

---

### Task 1: Entorno PHP 8.4, Node y línea base

**Files:**
- Create: `web/scripts/dev-env.sh`
- Modify: `web/composer.json` (`"php": "^8.4"`), `web/composer.lock` (vía `composer update --lock`), `web/.env.example`, `web/phpunit.xml`

**Interfaces:**
- Produces: `scripts/dev-env.sh` (todas las tareas lo usan); `.env.example` con claves `PUBLIC_FORM_MIN_AGE`, `APP_TIMEZONE`.

- [ ] **Step 1: Crear el script de entorno**

```bash
#!/usr/bin/env bash
# Uso (desde web/): source scripts/dev-env.sh
# Pone PHP 8.4 de MAMP y Node 26 por delante en el PATH de esta shell.
export PATH="/Applications/MAMP/bin/php/php8.4.17/bin:/usr/local/opt/node@26/bin:$PATH"
if ! php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'; then
    echo "ERROR: php no es 8.4 (es $(php -r 'echo PHP_VERSION;'))" >&2
fi
```

- [ ] **Step 2: Verificar versiones y extensiones**

Run: `cd web && source scripts/dev-env.sh && php -v | head -1 && composer --version && node -v && npm -v && php -m | grep -E "^(pdo_mysql|pdo_sqlite|mbstring|intl|openssl|tokenizer|xml|ctype|fileinfo)$"`
Expected: `PHP 8.4.17`; la línea de Composer contiene `PHP version 8.4`; Node 26.x; las 8 extensiones listadas. Si Composer sigue mostrando PHP 8.5, ejecutarlo como `php /Applications/MAMP/bin/php/composer` y usar esa forma en el resto del plan.

- [ ] **Step 3: Subir el requisito de PHP y refrescar el lock**

Modify `web/composer.json`: `"php": "^8.3"` → `"php": "^8.4"`.

Run: `composer update --lock && composer validate`
Expected: lock actualizado, `./composer.json is valid`.

- [ ] **Step 4: Ajustar `.env.example` y `phpunit.xml`**

En `web/.env.example` dejar (sin secretos): `APP_NAME=KumiteControl`, `APP_URL=https://kumitecontrol.test`, `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`, `APP_TIMEZONE=Europe/Madrid`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=kumitecontrol`, `DB_USERNAME=`, `DB_PASSWORD=`, `MAIL_MAILER=log`, y añadir al final `PUBLIC_FORM_MIN_AGE=18`. Añadir también `APP_TIMEZONE=Europe/Madrid` al `.env` local (sin tocar las credenciales).

En `web/phpunit.xml`, dentro de `<php>`, añadir:

```xml
<env name="APP_LOCALE" value="es"/>
<env name="APP_FALLBACK_LOCALE" value="es"/>
<env name="APP_TIMEZONE" value="Europe/Madrid"/>
```

- [ ] **Step 5: Comprobar Laravel, MySQL de MAMP y tests de base**

Run: `php artisan --version && php artisan migrate:status && php artisan test`
Expected: `Laravel Framework 13.x`; `migrate:status` conecta (si falla por credenciales/BD inexistente: **parar y preguntar al usuario**, no inventar datos); los 2 tests de ejemplo pasan.

- [ ] **Step 6: Commit**

```bash
git add scripts composer.json composer.lock .env.example phpunit.xml
git commit -m "chore: entorno PHP 8.4, script dev-env y .env.example" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Bootstrap 5.3, layout Blade y textos en español

**Files:**
- Modify: `web/package.json`, `web/vite.config.js`, `web/resources/css/app.css`, `web/resources/js/app.js`, `web/routes/web.php`, `web/tests/TestCase.php`
- Create: `web/resources/views/layouts/app.blade.php`, `web/resources/views/home.blade.php`, `web/resources/views/components/{field,select,textarea,check}.blade.php`, `web/lang/es/{auth,passwords,validation}.php`, `web/tests/Feature/HomeTest.php`
- Delete: `web/resources/views/welcome.blade.php`

**Interfaces:**
- Produces: layout `layouts.app` con `@yield('title')` y `@section('content')`; errores no asociados a campo se muestran con la clave `error`; mensaje flash con `session('status')`. Componentes: `<x-field name label type value required help/>`, `<x-select name label :options value required placeholder/>`, `<x-textarea name label value rows/>`, `<x-check name label :checked help/>`. Clase CSS `.no-print`.

- [ ] **Step 1: Test que falla**

`web/tests/Feature/HomeTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_home_is_spanish_and_offers_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Iniciar sesión');
    }
}
```

En `web/tests/TestCase.php` evitar que los tests exijan el build de Vite:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->withoutVite();
}
```

Run: `php artisan test --filter=HomeTest` → Expected: FAIL (`Iniciar sesión` no aparece).

- [ ] **Step 2: Sustituir Tailwind por Bootstrap**

Run: `npm install && npm uninstall tailwindcss @tailwindcss/vite && npm install bootstrap@^5.3 @popperjs/core`

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

`resources/js/app.js`:

```js
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;
```

`resources/css/app.css`:

```css
@import 'bootstrap/dist/css/bootstrap.min.css';

.bracket-round { min-width: 16rem; }
.bout-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; background: var(--bs-body-bg); }
.bout-card .slot { padding: .35rem .6rem; }
.bout-card .slot + .slot { border-top: 1px solid var(--bs-border-color); }
.bout-card .winner { font-weight: 600; background: var(--bs-success-bg-subtle); }

@media print {
    .no-print, .navbar, .alert { display: none !important; }
    body { background: #fff !important; }
    .bracket { overflow: visible !important; }
    a { text-decoration: none; color: inherit; }
}
```

- [ ] **Step 3: Layout, componentes y home**

`resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Campeonatos') · KumiteControl</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">KumiteControl</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
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
```

`resources/views/components/field.blade.php`:

```blade
@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}" @required($required)
           {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

`components/select.blade.php`:

```blade
@props(['name', 'label', 'options', 'value' => null, 'required' => false, 'placeholder' => null])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
            {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

`components/textarea.blade.php`:

```blade
@props(['name', 'label', 'value' => null, 'rows' => 4])
<div class="mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

`components/check.blade.php`:

```blade
@props(['name', 'label', 'checked' => false, 'help' => null])
<div class="form-check mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" class="form-check-input" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked))>
    <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
```

`resources/views/home.blade.php`:

```blade
@extends('layouts.app')
@section('content')
    <h1 class="h3">Campeonatos de artes marciales</h1>
    <p class="text-muted">Consulta los eventos publicados o accede como organizador.</p>
@endsection
```

`routes/web.php` (sustituye el contenido):

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
```

Los enlaces a `login`/`register` aún no existen: añadir **temporalmente** en `routes/web.php` `Route::view('/acceso', 'home')->name('login'); Route::view('/registro', 'home')->name('register');` (se reemplazan en la Task 3). Borrar `resources/views/welcome.blade.php`.

- [ ] **Step 4: Ficheros de idioma**

`lang/es/auth.php`:

```php
<?php

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Inténtalo de nuevo en :seconds segundos.',
];
```

`lang/es/passwords.php`:

```php
<?php

return [
    'reset' => 'Tu contraseña ha sido restablecida.',
    'sent' => 'Si el correo existe, te hemos enviado un enlace para restablecer la contraseña.',
    'throttled' => 'Espera antes de volver a intentarlo.',
    'token' => 'El enlace de restablecimiento no es válido o ha caducado.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',
];
```

`lang/es/validation.php`:

```php
<?php

return [
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'before_or_equal' => ':Attribute debe ser una fecha anterior o igual a :date.',
    'between' => ['numeric' => ':Attribute debe estar entre :min y :max.'],
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':Attribute no es una fecha válida.',
    'email' => ':Attribute debe ser un correo electrónico válido.',
    'enum' => 'El valor seleccionado en :attribute no es válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'gte' => ['numeric' => ':Attribute debe ser mayor o igual que :value.'],
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'max' => [
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => ':Attribute debe ser un número.',
    'password' => ['min' => ':Attribute debe tener al menos :min caracteres.'],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => ':Attribute debe ser texto.',
    'unique' => ':Attribute ya está en uso.',
    'attributes' => [
        'name' => 'el nombre', 'email' => 'el correo electrónico', 'password' => 'la contraseña',
        'discipline' => 'la disciplina', 'description' => 'la descripción', 'starts_on' => 'la fecha',
        'venue' => 'el lugar', 'status' => 'el estado', 'modality' => 'la modalidad', 'sex' => 'el sexo',
        'min_age' => 'la edad mínima', 'max_age' => 'la edad máxima', 'min_weight' => 'el peso mínimo',
        'max_weight' => 'el peso máximo', 'level' => 'el nivel', 'bronze_rule' => 'la regla del bronce',
        'first_name' => 'el nombre', 'last_name' => 'los apellidos', 'club' => 'el club',
        'birth_date' => 'la fecha de nacimiento', 'weight' => 'el peso', 'phone' => 'el teléfono',
        'category_id' => 'la categoría', 'winner_id' => 'el ganador',
    ],
];
```

- [ ] **Step 5: Verificar**

Run: `php artisan test --filter=HomeTest && npm run build`
Expected: test PASS; el build de Vite termina sin errores.

- [ ] **Step 6: Commit**

```bash
git add -A . ':!.env'
git commit -m "feat: Bootstrap 5.3, layout Blade y textos en español" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Cuentas de organizador (registro, acceso, recuperación)

**Files:**
- Create: `web/app/Http/Controllers/Auth/{RegisterController,LoginController,PasswordResetLinkController,NewPasswordController}.php`, `web/resources/views/auth/{login,register,forgot-password,reset-password}.blade.php`, `web/resources/views/panel/events/index.blade.php` (provisional), `web/tests/Feature/AuthTest.php`
- Modify: `web/routes/web.php`

**Interfaces:**
- Produces: rutas `register`, `login`, `logout`, `password.request`, `password.email`, `password.reset`, `password.update`; redirección tras entrar a `route('panel.events.index')` (URL `/panel/eventos`).

- [ ] **Step 1: Tests que fallan**

`tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_register_and_is_logged_in(): void
    {
        $this->post('/registro', [
            'name' => 'Ana', 'email' => 'ana@example.com',
            'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1',
        ])->assertRedirect(route('panel.events.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
        $this->assertNotSame('clave-segura-1', User::first()->password);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->post('/registro', [
            'name' => '', 'email' => 'ana@example.com', 'password' => 'corta', 'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_login_logout_and_bad_credentials(): void
    {
        $user = User::factory()->create(['password' => 'clave-segura-1']);

        $this->post('/acceso', ['email' => $user->email, 'password' => 'mala'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/acceso', ['email' => $user->email, 'password' => 'clave-segura-1'])
            ->assertRedirect(route('panel.events.index'));
        $this->assertAuthenticatedAs($user);

        $this->post('/salir')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_login_from_the_panel(): void
    {
        $this->get('/panel/eventos')->assertRedirect(route('login'));
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/recuperar-contrasena', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/recuperar-contrasena', ['email' => 'nadie@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/restablecer-contrasena', [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'nueva-clave-123', 'password_confirmation' => 'nueva-clave-123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post('/acceso', ['email' => $user->email, 'password' => 'nueva-clave-123']);
        $this->assertAuthenticatedAs($user);
    }
}
```

Run: `php artisan test --filter=AuthTest` → Expected: FAIL (rutas inexistentes).

- [ ] **Step 2: Controladores**

`RegisterController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Auth::login(User::create($data));
        $request->session()->regenerate();

        return redirect()->route('panel.events.index');
    }
}
```

`LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.events.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
```

`PasswordResetLinkController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Mensaje idéntico exista o no la cuenta: no se revela qué correos están registrados.
        return back()->with('status', __('passwords.sent'));
    }
}
```

`NewPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 3: Rutas** (sustituir las temporales de la Task 2)

`routes/web.php`:

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('registro', [RegisterController::class, 'create'])->name('register');
    Route::post('registro', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('acceso', [LoginController::class, 'create'])->name('login');
    Route::post('acceso', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('salir', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Provisional: la Task 4 lo sustituye por EventController.
Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::view('eventos', 'panel.events.index', ['events' => collect()])->name('events.index');
});
```

- [ ] **Step 4: Vistas** (las cuatro con el mismo patrón; formulario en `col-md-6 col-lg-5`)

`auth/login.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<div class="col-md-6 col-lg-5 mx-auto">
    <h1 class="h3 mb-3">Iniciar sesión</h1>
    <form method="POST" action="{{ route('login') }}">@csrf
        <x-field name="email" type="email" label="Correo electrónico" required autofocus />
        <x-field name="password" type="password" label="Contraseña" required />
        <x-check name="remember" label="Mantener la sesión iniciada" />
        <button class="btn btn-primary w-100">Entrar</button>
    </form>
    <p class="mt-3 mb-0"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a> · <a href="{{ route('register') }}">Crear cuenta</a></p>
</div>
@endsection
```

`auth/register.blade.php`: mismo layout, `<h1>Crear cuenta de organizador</h1>`, `POST route('register')`, campos `name`, `email`, `password` (help "Mínimo 8 caracteres"), `password_confirmation` (label "Repite la contraseña").

`auth/forgot-password.blade.php`: `<h1>Recuperar contraseña</h1>`, `POST route('password.email')`, campo `email`, botón "Enviar enlace".

`auth/reset-password.blade.php`: `POST route('password.update')`, `<input type="hidden" name="token" value="{{ $token }}">`, `<x-field name="email" type="email" label="Correo electrónico" :value="$email" required />`, `password`, `password_confirmation`, botón "Restablecer contraseña".

`panel/events/index.blade.php` (provisional): `@extends('layouts.app') @section('content')<h1 class="h3">Mis eventos</h1>@endsection`.

- [ ] **Step 5: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: cuentas de organizador (registro, acceso, recuperación)" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Eventos, propiedad y garantía global de autorización

**Files:**
- Create: `app/Enums/Concerns/HasOptions.php`, `app/Enums/EventStatus.php`, `app/Models/Event.php`, `app/Policies/EventPolicy.php`, `database/migrations/2026_10_11_100000_create_events_table.php`, `database/factories/EventFactory.php`, `app/Http/Requests/EventRequest.php`, `app/Http/Controllers/Panel/EventController.php`, `app/Http/Controllers/{HomeController,PublicEventController}.php`, vistas `panel/events/{index,create,edit,show,_form}.blade.php`, `public/events/show.blade.php`, tests `tests/Feature/{EventTest,PublicEventTest,OrganizerIsolationTest}.php`
- Modify: `app/Models/User.php` (relación `events()`), `routes/web.php`, `resources/views/home.blade.php`

**Interfaces:**
- Produces: `Event` (campos `user_id, slug, name, discipline, description, starts_on (Carbon date), venue, status (EventStatus), is_public, registration_form_enabled`); `Event::scopeListed()`; `Event::isListed(): bool` (`is_public && status !== Draft`); `Event::acceptsPublicRegistrations(): bool` (`isListed && registration_form_enabled && status === RegistrationOpen`); `EventStatus::options(): array<string,string>`; trait `HasOptions` (`options()`); rutas `panel.events.{index,create,store,show,edit,update,destroy}`, `home`, `public.events.show` (`/eventos/{event:slug}`); middleware `can:manage,event` + `scopeBindings()` en el grupo `panel/eventos/{event}`; `OrganizerIsolationTest::fixtureParameters(User $owner): array` (las tareas posteriores lo amplían).

- [ ] **Step 1: Test de aislamiento (genérico) y tests de eventos — fallan**

`tests/Feature/OrganizerIsolationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Red de seguridad: recorre TODAS las rutas del panel. Si alguien añade una ruta
 * sin auth o sin comprobar la propiedad del evento, o sin fixture aquí, este test falla.
 */
class OrganizerIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_panel_route_requires_authentication(): void
    {
        $routes = $this->panelRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri().' sin auth');
        }
    }

    public function test_every_event_scoped_route_rejects_other_organizers(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $params = $this->fixtureParameters($owner);
        $checked = 0;

        foreach ($this->panelRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'panel/eventos/{event}')) {
                continue;
            }

            $this->assertContains('can:manage,event', $route->gatherMiddleware(), $route->uri().' sin policy');

            $uri = $route->uri();
            foreach ($route->parameterNames() as $name) {
                $this->assertArrayHasKey($name, $params, 'Falta fixture para {'.$name.'} en '.$route->uri());
                $uri = str_replace('{'.$name.'}', (string) $params[$name], $uri);
            }

            $this->actingAs($intruder)->call($route->methods()[0], '/'.$uri)->assertForbidden();
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    /** @return array<string,int> id por nombre de parámetro de ruta, todo perteneciente a $owner */
    private function fixtureParameters(User $owner): array
    {
        $event = Event::factory()->for($owner)->create();

        return ['event' => $event->id];
    }

    /** @return list<LaravelRoute> */
    private function panelRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $r) => str_starts_with($r->uri(), 'panel'),
        ));
    }
}
```

`tests/Feature/EventTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Open de Primavera', 'discipline' => 'Judo', 'description' => 'Campeonato local',
            'starts_on' => '2026-11-20', 'venue' => 'Pabellón Municipal', 'status' => 'draft',
            'is_public' => '0', 'registration_form_enabled' => '0',
        ], $overrides);
    }

    public function test_organizer_creates_an_event_owned_by_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['user_id' => 999]))
            ->assertRedirect();

        $event = Event::firstOrFail();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertNotEmpty($event->slug);
    }

    public function test_event_requires_valid_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('panel.events.store'), $this->payload(['name' => '', 'status' => 'xx', 'starts_on' => 'no']))
            ->assertSessionHasErrors(['name', 'status', 'starts_on']);
    }

    public function test_index_lists_only_own_events(): void
    {
        $user = User::factory()->create();
        Event::factory()->for($user)->create(['name' => 'Mi evento']);
        Event::factory()->create(['name' => 'Evento ajeno']);

        $this->actingAs($user)->get(route('panel.events.index'))
            ->assertOk()->assertSee('Mi evento')->assertDontSee('Evento ajeno');
    }

    public function test_owner_can_update_and_delete_but_others_cannot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)->put(route('panel.events.update', $event), $this->payload(['name' => 'Nuevo']))
            ->assertRedirect(route('panel.events.show', $event));
        $this->assertSame('Nuevo', $event->fresh()->name);

        $this->actingAs(User::factory()->create())->delete(route('panel.events.destroy', $event))->assertForbidden();
        $this->assertModelExists($event);

        $this->actingAs($owner)->delete(route('panel.events.destroy', $event))->assertRedirect(route('panel.events.index'));
        $this->assertModelMissing($event);
    }
}
```

`tests/Feature/PublicEventTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listed_event_is_visible_and_on_home(): void
    {
        $event = Event::factory()->create(['is_public' => true, 'status' => EventStatus::RegistrationOpen, 'name' => 'Copa Visible']);

        $this->get(route('home'))->assertSee('Copa Visible');
        $this->get(route('public.events.show', $event))->assertOk()->assertSee('Copa Visible');
    }

    public function test_private_and_draft_events_are_not_public(): void
    {
        $private = Event::factory()->create(['is_public' => false, 'status' => EventStatus::InProgress, 'name' => 'Copa Privada']);
        $draft = Event::factory()->create(['is_public' => true, 'status' => EventStatus::Draft, 'name' => 'Copa Borrador']);

        $this->get(route('home'))->assertDontSee('Copa Privada')->assertDontSee('Copa Borrador');
        $this->get(route('public.events.show', $private))->assertNotFound();
        $this->get(route('public.events.show', $draft))->assertNotFound();
    }
}
```

Run: `php artisan test --filter="EventTest|PublicEventTest|OrganizerIsolationTest"` → Expected: FAIL.

- [ ] **Step 2: Enums, migración, modelo, policy, factory**

`app/Enums/Concerns/HasOptions.php`:

```php
<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** @return array<string,string> valor => etiqueta, para <select> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
```

`app/Enums/EventStatus.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case InProgress = 'in_progress';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::RegistrationOpen => 'Inscripciones abiertas',
            self::RegistrationClosed => 'Inscripciones cerradas',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizado',
        };
    }
}
```

Migración `2026_10_11_100000_create_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name', 150);
            $table->string('discipline', 100);
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->string('venue', 200);
            $table->string('status', 30)->default('draft');
            $table->boolean('is_public')->default(false);
            $table->boolean('registration_form_enabled')->default(false);
            $table->timestamps();

            $table->index(['is_public', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

`app/Models/Event.php` (las relaciones `categories`, `competitors`, `registrations` se añaden en las Tasks 5 y 6):

```php
<?php

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'discipline', 'description', 'starts_on', 'venue', 'status', 'is_public', 'registration_form_enabled'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'status' => EventStatus::class,
            'is_public' => 'boolean',
            'registration_form_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->slug ??= Str::slug($event->name).'-'.Str::lower(Str::random(6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeListed(Builder $query): void
    {
        $query->where('is_public', true)->where('status', '!=', EventStatus::Draft->value);
    }

    public function isListed(): bool
    {
        return $this->is_public && $this->status !== EventStatus::Draft;
    }

    public function acceptsPublicRegistrations(): bool
    {
        return $this->isListed()
            && $this->registration_form_enabled
            && $this->status === EventStatus::RegistrationOpen;
    }
}
```

`app/Policies/EventPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Único punto de autorización: solo el propietario gestiona el evento y todo lo que cuelga de él. */
    public function manage(User $user, Event $event): bool
    {
        return (int) $event->user_id === (int) $user->getKey();
    }
}
```

`app/Models/User.php`: añadir `use Illuminate\Database\Eloquent\Relations\HasMany;` y

```php
public function events(): HasMany
{
    return $this->hasMany(Event::class);
}
```

`database/factories/EventFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'discipline' => fake()->randomElement(['Judo', 'Kárate', 'Taekwondo']),
            'description' => fake()->paragraph(),
            'starts_on' => now()->addMonth()->toDateString(),
            'venue' => fake()->city(),
            'status' => EventStatus::Draft,
            'is_public' => false,
            'registration_form_enabled' => false,
        ];
    }

    public function registrationOpen(): static
    {
        return $this->state(fn () => [
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
        ]);
    }
}
```

- [ ] **Step 3: Request, controladores, rutas, vistas**

`app/Http/Requests/EventRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La propiedad se comprueba en el middleware can:manage,event; crear solo exige sesión.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'discipline' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'is_public' => ['boolean'],
            'registration_form_enabled' => ['boolean'],
        ];
    }
}
```

`app/Http/Controllers/Panel/EventController.php`:

```php
<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        return view('panel.events.index', [
            'events' => $request->user()->events()->orderByDesc('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.events.create');
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Evento creado.');
    }

    public function show(Event $event): View
    {
        return view('panel.events.show', ['event' => $event]);
    }

    public function edit(Event $event): View
    {
        return view('panel.events.edit', ['event' => $event]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Evento actualizado.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('panel.events.index')->with('status', 'Evento eliminado.');
    }
}
```

`app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', ['events' => Event::listed()->orderBy('starts_on')->get()]);
    }
}
```

`app/Http/Controllers/PublicEventController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless($event->isListed(), 404);

        return view('public.events.show', ['event' => $event]);
    }
}
```

Rutas (`routes/web.php`): sustituir `Route::view('/', ...)` por `Route::get('/', HomeController::class)->name('home');`, añadir `Route::get('eventos/{event:slug}', [PublicEventController::class, 'show'])->name('public.events.show');` y reemplazar el grupo provisional por:

```php
Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('eventos', [EventController::class, 'index'])->name('events.index');
    Route::get('eventos/crear', [EventController::class, 'create'])->name('events.create');
    Route::post('eventos', [EventController::class, 'store'])->name('events.store');

    // Todo lo que cuelga de un evento exige ser su propietario; scopeBindings impide mezclar recursos de otro evento.
    Route::middleware('can:manage,event')->scopeBindings()->prefix('eventos/{event}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('events.show');
        Route::get('editar', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/', [EventController::class, 'update'])->name('events.update');
        Route::delete('/', [EventController::class, 'destroy'])->name('events.destroy');
        // Las tareas siguientes añaden aquí categorías, inscripciones, cuadros, exportaciones…
    });
});
```

Vistas:

`panel/events/_form.blade.php` (variable `$event` opcional):

```blade
@php($event ??= null)
<x-field name="name" label="Nombre" :value="$event?->name" required />
<x-field name="discipline" label="Disciplina" :value="$event?->discipline" required />
<x-field name="starts_on" type="date" label="Fecha" :value="$event?->starts_on?->format('Y-m-d')" required />
<x-field name="venue" label="Lugar" :value="$event?->venue" required />
<x-textarea name="description" label="Descripción" :value="$event?->description" />
<x-select name="status" label="Estado" :options="\App\Enums\EventStatus::options()" :value="$event?->status?->value ?? 'draft'" required />
<x-check name="is_public" label="Evento público" :checked="$event?->is_public ?? false"
         help="Un evento público y no en borrador aparece en la portada y tiene página pública." />
<x-check name="registration_form_enabled" label="Habilitar formulario público de inscripción" :checked="$event?->registration_form_enabled ?? false"
         help="Solo funciona con el evento público y en estado «Inscripciones abiertas». Ver docs/menores.md." />
```

`panel/events/create.blade.php` y `edit.blade.php`: `@extends('layouts.app')`, formulario `POST route('panel.events.store')` / `@method('PUT')` a `route('panel.events.update', $event)`, `@csrf`, `@include('panel.events._form')`, botón Guardar y enlace Cancelar.

`panel/events/index.blade.php`:

```blade
@extends('layouts.app')
@section('title', 'Mis eventos')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mis eventos</h1>
    <a href="{{ route('panel.events.create') }}" class="btn btn-primary">Nuevo evento</a>
</div>
@forelse ($events as $event)
    <a href="{{ route('panel.events.show', $event) }}" class="card mb-2 text-decoration-none text-body">
        <div class="card-body d-flex justify-content-between flex-wrap gap-2">
            <div><strong>{{ $event->name }}</strong><div class="text-muted small">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</div></div>
            <div><span class="badge text-bg-secondary">{{ $event->status->label() }}</span> <span class="badge text-bg-{{ $event->is_public ? 'success' : 'light' }}">{{ $event->is_public ? 'Público' : 'Privado' }}</span></div>
        </div>
    </a>
@empty
    <p class="text-muted">Todavía no has creado ningún evento.</p>
@endforelse
@endsection
```

`panel/events/show.blade.php`: cabecera con nombre, estado, datos, enlaces "Editar", "Ver página pública" (si `isListed`, a `route('public.events.show', $event)`), botón borrar (`DELETE`, con `onsubmit="return confirm('¿Eliminar el evento y todos sus datos?')"`), y un `@yield`-less bloque `<section id="categorias">` que las Tasks 5–6 completan.

`public/events/show.blade.php`:

```blade
@extends('layouts.app')
@section('title', $event->name)
@section('content')
<h1 class="h3">{{ $event->name }}</h1>
<p class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</p>
<span class="badge text-bg-secondary mb-3">{{ $event->status->label() }}</span>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif
{{-- Task 7 añade el enlace de inscripción; Task 13 la lista de categorías publicadas. --}}
@endsection
```

`home.blade.php`: tras el párrafo, `@forelse($events as $event)` tarjeta con enlace a `public.events.show` (nombre, disciplina, fecha, lugar) `@empty <p class="text-muted">No hay eventos publicados.</p>`.

- [ ] **Step 4: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS (incluido `OrganizerIsolationTest`).

```bash
git add -A . ':!.env'
git commit -m "feat: eventos con propiedad por organizador y test global de autorización" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Categorías y edad en la fecha del evento

**Files:**
- Create: `app/Enums/{Sex,BronzeRule}.php`, `app/Models/Category.php`, `database/migrations/2026_10_11_100100_create_categories_table.php`, `database/factories/CategoryFactory.php`, `app/Http/Requests/CategoryRequest.php`, `app/Http/Controllers/Panel/CategoryController.php`, vistas `panel/categories/{create,edit,_form}.blade.php`, `tests/Feature/CategoryTest.php`, `tests/Unit/CompetitorAgeTest.php`
- Modify: `app/Models/Event.php` (`categories()`), `routes/web.php`, `resources/views/panel/events/show.blade.php`, `tests/Feature/OrganizerIsolationTest.php` (fixture `category`)
- Create (modelo mínimo para la edad; se completa en Task 6): `app/Models/Competitor.php`

**Interfaces:**
- Consumes: `Event`, `EventStatus::options()`.
- Produces: `Sex` (`Male='male'`, `Female='female'`, `options()`); `BronzeRule` (`BronzeMatch='bronze_match'`, `SharedBronze='shared_bronze'`, `label()`, `options()`); `Category` con campos `event_id, name, modality, sex (Sex|null), min_age, max_age, min_weight, max_weight, level, bronze_rule (BronzeRule), show_fourth, is_published, bracket_generated_at`; `Category::event()`; `Category::mismatchesFor(Competitor): list<string>`; `Competitor::ageOn(CarbonInterface): int` (`birth_date` Carbon); rutas `panel.categories.{create,store,edit,update,destroy}`; `CategoryFactory`.

- [ ] **Step 1: Tests que fallan**

`tests/Unit/CompetitorAgeTest.php` (sin BD):

```php
<?php

namespace Tests\Unit;

use App\Models\Competitor;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CompetitorAgeTest extends TestCase
{
    private function competitor(string $birth): Competitor
    {
        $competitor = new Competitor;
        $competitor->birth_date = Carbon::parse($birth);

        return $competitor;
    }

    public function test_birthday_on_the_event_day_counts_as_turned(): void
    {
        $this->assertSame(15, $this->competitor('2010-06-15')->ageOn(Carbon::parse('2025-06-15')));
    }

    public function test_day_before_birthday_has_not_turned_yet(): void
    {
        $this->assertSame(14, $this->competitor('2010-06-15')->ageOn(Carbon::parse('2025-06-14')));
    }

    public function test_leap_day_birthday_in_non_leap_year(): void
    {
        $this->assertSame(16, $this->competitor('2008-02-29')->ageOn(Carbon::parse('2025-02-28'))); // aún no cumple 17
        $this->assertSame(17, $this->competitor('2008-02-29')->ageOn(Carbon::parse('2025-03-01')));
    }
}
```


`tests/Feature/CategoryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\BronzeRule;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Sub-18 -60kg', 'modality' => 'Combate', 'sex' => 'male', 'min_age' => 15, 'max_age' => 17,
            'min_weight' => 55, 'max_weight' => 60, 'level' => 'Cinturón verde',
            'bronze_rule' => 'bronze_match', 'show_fourth' => '1',
        ], $o);
    }

    public function test_owner_creates_updates_and_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload())
            ->assertRedirect(route('panel.events.show', $event));
        $category = $event->categories()->firstOrFail();
        $this->assertSame(BronzeRule::BronzeMatch, $category->bronze_rule);
        $this->assertTrue($category->show_fourth);

        $this->actingAs($user)->put(route('panel.categories.update', [$event, $category]), $this->payload(['name' => 'Otro']))
            ->assertRedirect(route('panel.events.show', $event));
        $this->assertSame('Otro', $category->fresh()->name);

        $this->actingAs($user)->delete(route('panel.categories.destroy', [$event, $category]));
        $this->assertModelMissing($category);
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload([
            'name' => '', 'min_age' => 20, 'max_age' => 10, 'min_weight' => 70, 'max_weight' => 60, 'bronze_rule' => 'x',
        ]))->assertSessionHasErrors(['name', 'max_age', 'max_weight', 'bronze_rule']);
    }

    public function test_fourth_place_is_ignored_with_shared_bronze(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), $this->payload(['bronze_rule' => 'shared_bronze', 'show_fourth' => '1']));
        $this->assertFalse($event->categories()->first()->show_fourth);
    }

    public function test_category_of_another_event_is_not_found_through_my_event(): void
    {
        $user = User::factory()->create();
        $mine = Event::factory()->for($user)->create();
        $foreign = Category::factory()->create();

        $this->actingAs($user)->get(route('panel.categories.edit', [$mine, $foreign]))->assertNotFound();
    }

    public function test_mismatches_use_age_on_event_date(): void
    {
        $event = Event::factory()->create(['starts_on' => '2026-06-15']);
        $category = Category::factory()->for($event)->create(['min_age' => 15, 'max_age' => 17, 'sex' => 'male', 'min_weight' => 55, 'max_weight' => 60]);

        $ok = Competitor::factory()->for($event)->create(['birth_date' => '2011-06-15', 'sex' => 'male', 'weight' => 58]); // 15 exactos
        $young = Competitor::factory()->for($event)->create(['birth_date' => '2011-06-16', 'sex' => 'male', 'weight' => 58]); // 14
        $heavy = Competitor::factory()->for($event)->create(['birth_date' => '2010-01-01', 'sex' => 'female', 'weight' => 70]);

        $this->assertSame([], $category->mismatchesFor($ok));
        $this->assertCount(1, $category->mismatchesFor($young));
        $this->assertCount(2, $category->mismatchesFor($heavy)); // sexo y peso
    }
}
```

El test de la regla del bronce con resultados se añade en la Task 10.

Run: `php artisan test --filter="CategoryTest|CompetitorAgeTest"` → Expected: FAIL.

- [ ] **Step 2: Enums, migración, modelos y factories**

`app/Enums/Sex.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Sex: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Femenino',
        };
    }
}
```

`app/Enums/BronzeRule.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * DECISIÓN PENDIENTE: la regla real del bronce depende del reglamento de cada campeonato.
 * BronzeMatch es solo la propuesta inicial. Ver docs/decisiones-pendientes.md.
 */
enum BronzeRule: string
{
    use HasOptions;

    case BronzeMatch = 'bronze_match';
    case SharedBronze = 'shared_bronze';

    public function label(): string
    {
        return match ($this) {
            self::BronzeMatch => 'Combate por el bronce entre perdedores de semifinales (propuesta — DECISIÓN PENDIENTE)',
            self::SharedBronze => 'Dos terceros puestos, sin combate',
        };
    }
}
```

Migración `2026_10_11_100100_create_categories_table.php`:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('name', 150);
    $table->string('modality', 100)->nullable();
    $table->string('sex', 10)->nullable();            // null = mixta / sin restricción
    $table->unsignedTinyInteger('min_age')->nullable();
    $table->unsignedTinyInteger('max_age')->nullable();
    $table->decimal('min_weight', 5, 2)->nullable();
    $table->decimal('max_weight', 5, 2)->nullable();
    $table->string('level', 100)->nullable();
    $table->string('bronze_rule', 30)->default('bronze_match');
    $table->boolean('show_fourth')->default(false);
    $table->boolean('is_published')->default(false);
    $table->timestamp('bracket_generated_at')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'name']);
});
```

(Con las cabeceras habituales `use` y `return new class extends Migration { up/down }`.)

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use App\Enums\BronzeRule;
use App\Enums\Sex;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'modality', 'sex', 'min_age', 'max_age', 'min_weight', 'max_weight', 'level', 'bronze_rule', 'show_fourth'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'bronze_rule' => BronzeRule::class,
            'min_weight' => 'float',
            'max_weight' => 'float',
            'show_fourth' => 'boolean',
            'is_published' => 'boolean',
            'bracket_generated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Avisos (no bloqueos): el organizador asigna o valida la categoría.
     *
     * @return list<string>
     */
    public function mismatchesFor(Competitor $competitor): array
    {
        $problems = [];
        $age = $competitor->ageOn($this->event->starts_on);

        if ($this->min_age !== null && $age < $this->min_age) {
            $problems[] = "Edad {$age}: inferior al mínimo ({$this->min_age})";
        }
        if ($this->max_age !== null && $age > $this->max_age) {
            $problems[] = "Edad {$age}: superior al máximo ({$this->max_age})";
        }
        if ($this->sex !== null && $competitor->sex !== null && $competitor->sex !== $this->sex) {
            $problems[] = 'Sexo distinto al de la categoría';
        }
        if ($competitor->weight !== null) {
            if (($this->min_weight !== null && $competitor->weight < $this->min_weight)
                || ($this->max_weight !== null && $competitor->weight > $this->max_weight)) {
                $problems[] = "Peso {$competitor->weight} kg fuera del rango";
            }
        }

        return $problems;
    }
}
```

`Event`: añadir `categories(): HasMany` (`return $this->hasMany(Category::class);`).

`app/Models/Competitor.php` (versión mínima; Task 6 añade relaciones/normalización):

```php
<?php

namespace App\Models;

use App\Enums\Sex;
use Carbon\CarbonInterface;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes'])]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    /** Campos de datos personales que se copian de un formulario. */
    public const FIELDS = ['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'weight' => 'float',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Años cumplidos en $date (el día del cumpleaños ya cuenta como cumplido). */
    public function ageOn(CarbonInterface $date): int
    {
        $age = $date->year - $this->birth_date->year;

        if ($date->format('md') < $this->birth_date->format('md')) {
            $age--;
        }

        return $age;
    }
}
```

Migración `competitors` (se crea ya aquí para que el test de categorías funcione): `2026_10_11_100200_create_competitors_table.php`:

```php
Schema::create('competitors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('first_name', 100);
    $table->string('last_name', 100);
    $table->string('club', 150)->nullable();
    $table->date('birth_date');
    $table->string('sex', 10)->nullable();
    $table->decimal('weight', 5, 2)->nullable();
    $table->string('level', 100)->nullable();
    $table->string('email')->nullable();
    $table->string('phone', 30)->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'first_name', 'last_name', 'birth_date']);
});
```

`database/factories/CompetitorFactory.php`:

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName().' '.fake()->lastName(),
        'club' => fake()->company(),
        'birth_date' => fake()->dateTimeBetween('-40 years', '-12 years')->format('Y-m-d'),
        'sex' => fake()->randomElement(['male', 'female']),
        'weight' => fake()->randomFloat(1, 40, 100),
        'level' => null,
        'email' => fake()->safeEmail(),
        'phone' => fake()->numerify('6########'),
        'notes' => null,
    ];
}
```

`database/factories/CategoryFactory.php`:

```php
public function definition(): array
{
    return [
        'event_id' => Event::factory(),
        'name' => fake()->unique()->words(2, true),
        'bronze_rule' => BronzeRule::BronzeMatch,
        'show_fourth' => false,
        'is_published' => false,
    ];
}
```

- [ ] **Step 3: Request, controlador, rutas, vistas**

`CategoryRequest::rules()`:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:150',
            Rule::unique('categories', 'name')->where('event_id', $this->route('event')->id)->ignore($this->route('category')?->id)],
        'modality' => ['nullable', 'string', 'max:100'],
        'sex' => ['nullable', Rule::enum(Sex::class)],
        'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
        'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
        'min_weight' => ['nullable', 'numeric', 'min:0', 'max:300'],
        'max_weight' => ['nullable', 'numeric', 'min:0', 'max:300', 'gte:min_weight'],
        'level' => ['nullable', 'string', 'max:100'],
        'bronze_rule' => ['required', Rule::enum(BronzeRule::class)],
        'show_fourth' => ['boolean'],
    ];
}

protected function prepareForValidation(): void
{
    // El 4.º puesto solo existe con combate por el bronce.
    if ($this->input('bronze_rule') !== BronzeRule::BronzeMatch->value) {
        $this->merge(['show_fourth' => false]);
    }
}
```

(`authorize()` devuelve `true`; la propiedad la garantiza el middleware.) `sex` vacío llega como `''` → Laravel lo normaliza a `null` por el middleware `ConvertEmptyStringsToNull`.

`CategoryController` (create, store, edit, update, destroy; sin index ni show: la lista vive en `panel.events.show`):

```php
public function store(CategoryRequest $request, Event $event): RedirectResponse
{
    $event->categories()->create($request->validated());

    return redirect()->route('panel.events.show', $event)->with('status', 'Categoría creada.');
}

public function update(CategoryRequest $request, Event $event, Category $category): RedirectResponse
{
    $category->update($request->validated());

    return redirect()->route('panel.events.show', $event)->with('status', 'Categoría actualizada.');
}

public function destroy(Event $event, Category $category): RedirectResponse
{
    $category->delete();

    return redirect()->route('panel.events.show', $event)->with('status', 'Categoría eliminada.');
}
```

`create`/`edit` devuelven `view('panel.categories.create', compact('event'))` / `edit` con `$event, $category`.

Rutas, dentro del grupo `can:manage,event`:

```php
Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
Route::get('categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
```

`panel/categories/_form.blade.php`: campos `name`, `modality`, `x-select sex` (opciones `Sex::options()` con `placeholder="Mixta / sin restricción"`), dos `x-field type="number"` para edad mín/máx, dos para peso (`step="0.1"`), `level`, `x-select bronze_rule` con `BronzeRule::options()` y un `<div class="alert alert-warning">` "La regla del bronce es una decisión pendiente: confírmala con el reglamento antes de generar el cuadro.", `x-check show_fourth` ("Mostrar también el 4.º puesto", solo aplica con combate por el bronce). Vistas `create`/`edit` como las de eventos.

`panel/events/show.blade.php`, sección categorías: tabla (nombre, criterios resumidos, nº de confirmados) con enlaces Editar/Eliminar y botón "Nueva categoría". Para el conteo: en `EventController@show` pasar `$event->categories()->withCount(['registrations as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')])->orderBy('name')->get()` (la relación `registrations` se crea en la Task 6; **hasta entonces mostrar solo `$event->categories`** y completar el conteo en la Task 6).

- [ ] **Step 4: Ampliar el test de aislamiento**

En `OrganizerIsolationTest::fixtureParameters` añadir:

```php
$category = Category::factory()->for($event)->create();

return ['event' => $event->id, 'category' => $category->id];
```

(con `use App\Models\Category;`).

- [ ] **Step 5: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: categorías con criterios configurables y edad en la fecha del evento" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Competidores e inscripciones manuales

**Files:**
- Create: `app/Enums/RegistrationStatus.php`, `app/Models/Registration.php`, `database/migrations/2026_10_11_100300_create_registrations_table.php`, `database/factories/RegistrationFactory.php`, `app/Registrations/{RegistrationService,RegistrationException}.php`, `app/Http/Requests/{RegistrationRequest,ManualRegistrationRequest,CompetitorRequest}.php`, `app/Http/Controllers/Panel/{RegistrationController,CompetitorController}.php`, vistas `panel/registrations/{index,create}.blade.php`, `panel/competitors/edit.blade.php`, `tests/Feature/RegistrationTest.php`
- Modify: `app/Models/Competitor.php` (relaciones y `normalizeName`), `app/Models/Event.php` (`competitors()`, `registrations()`), `app/Models/Category.php` (`registrations()`), `routes/web.php`, `panel/events/show.blade.php`, `EventController@show`, `OrganizerIsolationTest`

**Interfaces:**
- Consumes: `Event`, `Category`, `Competitor::FIELDS`.
- Produces: `RegistrationStatus` (`Pending='pending'`, `Confirmed='confirmed'`, `Cancelled='cancelled'`, `label()`, `options()`); `Registration` (`category_id, competitor_id, status`; `category()`, `competitor()`; `scopeFilter(array $filters)` con claves `category_id,status,q`); `Category::registrations()`; `Event::registrations()` (hasManyThrough Category); `Event::competitors()`; `Competitor::normalizeName(string): string` (estático); `RegistrationService::register(Category, array $data, RegistrationStatus = Pending): Registration` (lanza `RegistrationException` si ya inscrito y no cancelado; reactiva si estaba cancelado); `RegistrationService::changeStatus(Registration, RegistrationStatus): void`; `RegistrationRequest::rules()` (campos del competidor + `category_id` del evento de la ruta); `ManualRegistrationRequest` (añade `status` pending|confirmed y `notes`); `RegistrationFactory::confirmed()`, `RegistrationFactory::forCategory(Category)`; rutas `panel.registrations.{index,create,store,update}`, `panel.competitors.{edit,update}`.

- [ ] **Step 1: Tests que fallan**

`tests/Feature/RegistrationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $o = []): array
    {
        return array_merge([
            'first_name' => 'José', 'last_name' => 'García Pérez', 'club' => 'Dojo Norte',
            'birth_date' => '2010-06-15', 'sex' => 'male', 'weight' => 58.5,
        ], $o);
    }

    public function test_organizer_registers_a_competitor_manually(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data([
            'category_id' => $category->id, 'status' => 'confirmed',
        ]))->assertRedirect(route('panel.registrations.index', $event));

        $registration = Registration::firstOrFail();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame($event->id, $registration->competitor->event_id);
    }

    public function test_cannot_register_into_a_category_of_another_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $foreign = Category::factory()->create();

        $this->actingAs($user)->post(route('panel.registrations.store', $event), $this->data(['category_id' => $foreign->id, 'status' => 'pending']))
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_duplicate_registration_in_same_category_is_rejected(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $service->register($category, $this->data());

        $this->expectException(RegistrationException::class);
        $service->register($category, $this->data());
    }

    /** Review Focus 1: misma persona con otra capitalización, acentos y espacios. */
    public function test_duplicate_detection_ignores_case_accents_and_extra_spaces(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $service->register($category, $this->data());

        try {
            $service->register($category, $this->data(['first_name' => '  jose ', 'last_name' => 'GARCIA   PEREZ']));
            $this->fail('Debió detectar el duplicado');
        } catch (RegistrationException) {
            $this->assertDatabaseCount('competitors', 1);
            $this->assertDatabaseCount('registrations', 1);
        }
    }

    public function test_same_competitor_can_join_two_categories_and_reuses_the_competitor(): void
    {
        $event = Event::factory()->create();
        $a = Category::factory()->for($event)->create();
        $b = Category::factory()->for($event)->create();
        $service = app(RegistrationService::class);

        $service->register($a, $this->data());
        $service->register($b, $this->data());

        $this->assertDatabaseCount('competitors', 1);
        $this->assertDatabaseCount('registrations', 2);
    }

    public function test_cancelled_registration_is_reactivated_instead_of_duplicated(): void
    {
        $category = Category::factory()->create();
        $service = app(RegistrationService::class);
        $registration = $service->register($category, $this->data(), RegistrationStatus::Confirmed);
        $service->changeStatus($registration, RegistrationStatus::Cancelled);

        $again = $service->register($category, $this->data());

        $this->assertSame($registration->id, $again->id);
        $this->assertSame(RegistrationStatus::Pending, $again->fresh()->status);
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_same_name_and_birthdate_in_another_event_is_a_different_competitor(): void
    {
        $service = app(RegistrationService::class);
        $service->register(Category::factory()->create(), $this->data());
        $service->register(Category::factory()->create(), $this->data());

        $this->assertDatabaseCount('competitors', 2);
    }

    public function test_status_change_and_filters(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $category = Category::factory()->for($event)->create(['name' => 'A']);
        $other = Category::factory()->for($event)->create(['name' => 'B']);
        $r1 = Registration::factory()->forCategory($category)->create();
        Registration::factory()->forCategory($other)->confirmed()->create();

        $this->actingAs($user)->patch(route('panel.registrations.update', [$event, $r1]), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame(RegistrationStatus::Confirmed, $r1->fresh()->status);

        $this->actingAs($user)->patch(route('panel.registrations.update', [$event, $r1]), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $page = $this->actingAs($user)->get(route('panel.registrations.index', ['event' => $event, 'category_id' => $other->id]));
        $page->assertOk()->assertSee($other->registrations()->first()->competitor->last_name)
            ->assertDontSee($r1->competitor->last_name);
    }

    public function test_competitor_data_can_be_edited_by_owner(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $competitor = Competitor::factory()->for($event)->create();

        $this->actingAs($user)->put(route('panel.competitors.update', [$event, $competitor]), $this->data(['club' => 'Nuevo Club']))
            ->assertRedirect(route('panel.registrations.index', $event));
        $this->assertSame('Nuevo Club', $competitor->fresh()->club);
    }
}
```

Run: `php artisan test --filter=RegistrationTest` → Expected: FAIL.

- [ ] **Step 2: Enum, migración, modelos, factory**

`RegistrationStatus` (patrón de `EventStatus`): `Pending='pending'` («Pendiente»), `Confirmed='confirmed'` («Confirmada»), `Cancelled='cancelled'` («Cancelada»), con `HasOptions`.

Migración `registrations`:

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
    $table->string('status', 20)->default('pending');
    $table->timestamps();

    $table->unique(['category_id', 'competitor_id']);   // sin duplicados por categoría
    $table->index(['category_id', 'status']);
});
```

`Registration`:

```php
#[Fillable(['category_id', 'competitor_id', 'status'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function competitor(): BelongsTo { return $this->belongsTo(Competitor::class); }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('registrations.category_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('registrations.status', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->whereHas('competitor', fn ($c) => $c->where(fn ($w) => $w
                    ->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('club', 'like', $like)));
            });
    }
}
```

`RegistrationFactory`:

```php
public function definition(): array
{
    return [
        'category_id' => Category::factory(),
        'competitor_id' => Competitor::factory(),
        'status' => RegistrationStatus::Pending,
    ];
}

public function confirmed(): static
{
    return $this->state(fn () => ['status' => RegistrationStatus::Confirmed]);
}

/** Crea un competidor nuevo en el mismo evento que la categoría (evita mezclar eventos en tests). */
public function forCategory(Category $category): static
{
    return $this->state(fn () => [
        'category_id' => $category->id,
        'competitor_id' => Competitor::factory()->create(['event_id' => $category->event_id])->id,
    ]);
}
```

`Competitor`: añadir `registrations(): HasMany` y

```php
public static function normalizeName(string $value): string
{
    return Str::of($value)->squish()->ascii()->lower()->toString();
}
```

`Event`: `competitors(): HasMany` y `registrations(): HasManyThrough` (`$this->hasManyThrough(Registration::class, Category::class)`). `Category`: `registrations(): HasMany`.

- [ ] **Step 3: Servicio**

`app/Registrations/RegistrationException.php`: `class RegistrationException extends \DomainException {}`.

`app/Registrations/RegistrationService.php`:

```php
<?php

namespace App\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class RegistrationService
{
    /**
     * @param  array<string,mixed>  $data  campos de Competitor::FIELDS
     *
     * @throws RegistrationException si el competidor ya está inscrito (y no cancelado) en la categoría
     */
    public function register(Category $category, array $data, RegistrationStatus $status = RegistrationStatus::Pending): Registration
    {
        return DB::transaction(function () use ($category, $data, $status) {
            $competitor = $this->findOrCreateCompetitor($category->event, $data);

            $existing = Registration::where('category_id', $category->id)
                ->where('competitor_id', $competitor->id)->first();

            if ($existing && $existing->status !== RegistrationStatus::Cancelled) {
                throw new RegistrationException('Este competidor ya está inscrito en la categoría.');
            }

            if ($existing) {
                $existing->update(['status' => $status]);

                return $existing;
            }

            return Registration::create([
                'category_id' => $category->id,
                'competitor_id' => $competitor->id,
                'status' => $status,
            ]);
        });
    }

    public function changeStatus(Registration $registration, RegistrationStatus $status): void
    {
        $registration->update(['status' => $status]);
    }

    /** Identidad del competidor dentro del evento: nombre y apellidos normalizados + fecha de nacimiento. */
    private function findOrCreateCompetitor(Event $event, array $data): Competitor
    {
        $first = Competitor::normalizeName($data['first_name']);
        $last = Competitor::normalizeName($data['last_name']);

        $existing = $event->competitors()->whereDate('birth_date', $data['birth_date'])->get()
            ->first(fn (Competitor $c) => Competitor::normalizeName($c->first_name) === $first
                && Competitor::normalizeName($c->last_name) === $last);

        return $existing ?? $event->competitors()->create(Arr::only($data, Competitor::FIELDS));
    }
}
```

- [ ] **Step 4: Requests, controladores, rutas, vistas**

`RegistrationRequest::rules()`:

```php
public function rules(): array
{
    return [
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'club' => ['nullable', 'string', 'max:150'],
        'birth_date' => ['required', 'date', 'before_or_equal:today'],
        'sex' => ['nullable', Rule::enum(Sex::class)],
        'weight' => ['nullable', 'numeric', 'min:0', 'max:300'],
        'level' => ['nullable', 'string', 'max:100'],
        'email' => ['nullable', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:30'],
        'category_id' => ['required', Rule::exists('categories', 'id')->where('event_id', $this->route('event')->id)],
    ];
}
```

`ManualRegistrationRequest extends RegistrationRequest`:

```php
public function rules(): array
{
    return parent::rules() + [
        'status' => ['required', Rule::in([RegistrationStatus::Pending->value, RegistrationStatus::Confirmed->value])],
        'notes' => ['nullable', 'string', 'max:2000'],
    ];
}
```

`CompetitorRequest::rules()`: los mismos campos del competidor (sin `category_id`) + `notes`, con una regla extra sobre `birth_date`/nombres para no colisionar con otro competidor del evento:

```php
'last_name' => ['required', 'string', 'max:100',
    Rule::unique('competitors')->where(fn ($q) => $q
        ->where('event_id', $this->route('event')->id)
        ->where('first_name', $this->input('first_name'))
        ->where('birth_date', $this->input('birth_date')))
        ->ignore($this->route('competitor')->id)],
```

`RegistrationController`:

```php
public function index(Request $request, Event $event): View
{
    $filters = $request->only('category_id', 'status', 'q');

    return view('panel.registrations.index', [
        'event' => $event,
        'categories' => $event->categories()->orderBy('name')->get(),
        'registrations' => $event->registrations()->with(['competitor', 'category.event'])->filter($filters)
            ->orderBy('categories.name')->orderBy('registrations.id')->get(),
        'filters' => $filters,
    ]);
}

public function create(Event $event): View { /* categorías + vista */ }

public function store(ManualRegistrationRequest $request, Event $event, RegistrationService $service): RedirectResponse
{
    $data = $request->validated();
    $category = $event->categories()->findOrFail($data['category_id']);

    try {
        $service->register($category, $data, RegistrationStatus::from($data['status']));
    } catch (RegistrationException $e) {
        return back()->withInput()->withErrors(['error' => $e->getMessage()]);
    }

    return redirect()->route('panel.registrations.index', $event)->with('status', 'Inscripción registrada.');
}

public function update(Request $request, Event $event, Registration $registration, RegistrationService $service): RedirectResponse
{
    $data = $request->validate(['status' => ['required', Rule::enum(RegistrationStatus::class)]]);

    try {
        $service->changeStatus($registration, RegistrationStatus::from($data['status']));
    } catch (RegistrationException $e) {
        return back()->withErrors(['error' => $e->getMessage()]);
    }

    return back()->with('status', 'Estado actualizado.');
}
```

> `store` pasa `$data` completo a `register`: `Arr::only(..., Competitor::FIELDS)` descarta `category_id` y `status`. `notes` solo llega desde el alta manual.

`CompetitorController` (`edit`, `update`: `$competitor->update($request->validated())` → redirect a `panel.registrations.index` con status «Datos actualizados»). Las rutas de `registration` y `competitor` van con `scopeBindings` por estar dentro del grupo del evento (`Event::registrations()` y `Event::competitors()` existen).

Rutas dentro del grupo `can:manage,event`:

```php
Route::get('inscripciones', [RegistrationController::class, 'index'])->name('registrations.index');
Route::get('inscripciones/crear', [RegistrationController::class, 'create'])->name('registrations.create');
Route::post('inscripciones', [RegistrationController::class, 'store'])->name('registrations.store');
Route::patch('inscripciones/{registration}', [RegistrationController::class, 'update'])->name('registrations.update');
Route::get('competidores/{competitor}/editar', [CompetitorController::class, 'edit'])->name('competitors.edit');
Route::put('competidores/{competitor}', [CompetitorController::class, 'update'])->name('competitors.update');
```

Vistas: `panel/registrations/index.blade.php` con formulario GET de filtros (`category_id`, `status`, `q`), tabla (competidor, club, categoría, estado, avisos `mismatchesFor` como `badge text-bg-warning`, botones «Confirmar»/«Cancelar»/«Pendiente» que son mini-formularios `PATCH`, enlace «Editar datos»), enlace «Nueva inscripción». `create.blade.php`: `x-select category_id`, campos del competidor, `x-select status` (pendiente/confirmada) y `x-textarea notes`. `competitors/edit.blade.php`: campos del competidor.

En `panel/events/show.blade.php` y `EventController@show`: añadir enlace a «Inscripciones» y completar el conteo de confirmados (`withCount(['registrations as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')])`).

- [ ] **Step 5: Ampliar el test de aislamiento**

En `fixtureParameters` añadir (tras `$category`):

```php
$registration = Registration::factory()->forCategory($category)->create();

return [
    'event' => $event->id, 'category' => $category->id,
    'registration' => $registration->id, 'competitor' => $registration->competitor_id,
];
```

- [ ] **Step 6: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: competidores e inscripciones manuales sin duplicados" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Formulario público de inscripción

**Files:**
- Create: `config/kumitecontrol.php`, `app/Http/Controllers/PublicRegistrationController.php`, `resources/views/public/registration.blade.php`, `tests/Feature/PublicRegistrationTest.php`
- Modify: `routes/web.php`, `resources/views/public/events/show.blade.php`

**Interfaces:**
- Consumes: `Event::acceptsPublicRegistrations()`, `RegistrationRequest`, `RegistrationService::register`, `Competitor::ageOn`.
- Produces: rutas `public.registration.create` (GET `/eventos/{event:slug}/inscripcion`) y `public.registration.store` (POST); `config('kumitecontrol.public_form_min_age')`.

- [ ] **Step 1: Tests que fallan** (Review Focus 5)

`tests/Feature/PublicRegistrationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function data(Category $category, array $o = []): array
    {
        return array_merge([
            'first_name' => 'Lucía', 'last_name' => 'Martín Gil', 'club' => 'Club Sur',
            'birth_date' => '2000-03-10', 'sex' => 'female', 'weight' => 55,
            'email' => 'lucia@example.com', 'category_id' => $category->id,
        ], $o);
    }

    private function openEvent(): array
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);

        return [$event, Category::factory()->for($event)->create()];
    }

    public function test_visitor_registers_without_account_and_is_always_pending(): void
    {
        [$event, $category] = $this->openEvent();

        $this->get(route('public.registration.create', $event))->assertOk()->assertSee($category->name);

        // `status=confirmed` inyectado debe ignorarse.
        $this->post(route('public.registration.store', $event), $this->data($category, ['status' => 'confirmed']))
            ->assertRedirect(route('public.events.show', $event));

        $this->assertSame(RegistrationStatus::Pending, Registration::firstOrFail()->status);
    }

    public function test_form_is_not_available_when_event_is_private_closed_or_disabled(): void
    {
        [$event, $category] = $this->openEvent();

        foreach ([
            ['is_public' => false],
            ['status' => EventStatus::RegistrationClosed],
            ['registration_form_enabled' => false],
            ['status' => EventStatus::Draft],
        ] as $change) {
            $event->update($change);
            $this->get(route('public.registration.create', $event))->assertNotFound();
            $this->post(route('public.registration.store', $event), $this->data($category))->assertNotFound();
            $event->refresh()->forceFill(['is_public' => true, 'status' => EventStatus::RegistrationOpen, 'registration_form_enabled' => true])->save();
        }

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_minors_are_rejected_until_consent_flow_exists(): void
    {
        [$event, $category] = $this->openEvent();

        // 17 años y 364 días el día del evento.
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21']))
            ->assertSessionHasErrors('birth_date');
        // 18 exactos el día del evento: permitido.
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-20']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_category_of_another_event_and_duplicates_are_rejected(): void
    {
        [$event, $category] = $this->openEvent();
        $foreign = Category::factory()->create();

        $this->post(route('public.registration.store', $event), $this->data($foreign))->assertSessionHasErrors('category_id');

        $this->post(route('public.registration.store', $event), $this->data($category));
        $this->post(route('public.registration.store', $event), $this->data($category))->assertSessionHasErrors('error');
        $this->assertDatabaseCount('registrations', 1);
    }
}
```

Run: `php artisan test --filter=PublicRegistrationTest` → Expected: FAIL.

- [ ] **Step 2: Config, controlador, rutas, vistas**

`config/kumitecontrol.php`:

```php
<?php

return [
    /*
     * Edad mínima (en la fecha del evento) para usar el formulario público.
     * Valor conservador hasta definir el consentimiento del representante legal: ver docs/menores.md.
     */
    'public_form_min_age' => (int) env('PUBLIC_FORM_MIN_AGE', 18),
];
```

`PublicRegistrationController`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\Competitor;
use App\Models\Event;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function create(Event $event): View
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        return view('public.registration', [
            'event' => $event,
            'categories' => $event->categories()->orderBy('name')->get(),
            'minAge' => config('kumitecontrol.public_form_min_age'),
        ]);
    }

    public function store(RegistrationRequest $request, Event $event, RegistrationService $service): RedirectResponse
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        // Solo se usan los campos validados: cualquier `status` enviado se descarta.
        $data = $request->validated();

        $competitor = new Competitor(['birth_date' => $data['birth_date']]);
        if ($competitor->ageOn($event->starts_on) < config('kumitecontrol.public_form_min_age')) {
            return back()->withInput()->withErrors([
                'birth_date' => 'Este formulario no admite menores de edad. Contacta con el organizador.',
            ]);
        }

        try {
            $service->register($event->categories()->findOrFail($data['category_id']), $data);
        } catch (RegistrationException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('public.events.show', $event)
            ->with('status', 'Solicitud recibida. El organizador confirmará tu inscripción.');
    }
}
```

> `RegistrationRequest` usa `$this->route('event')`; con `{event:slug}` el binding entrega el `Event` ✓. `authorize()` devuelve `true`. El `Event` público se busca con el slug, nunca con el id.

Rutas públicas (fuera del panel):

```php
Route::get('eventos/{event:slug}/inscripcion', [PublicRegistrationController::class, 'create'])->name('public.registration.create');
Route::post('eventos/{event:slug}/inscripcion', [PublicRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')->name('public.registration.store');
```

`public/registration.blade.php`: título, aviso («Solo mayores de {{ $minAge }} años. Tus datos de contacto y fecha de nacimiento no se publican.»), `form POST`, `x-select category_id` (`$categories->pluck('name','id')`), campos del competidor (`first_name`, `last_name`, `club`, `birth_date`, `x-select sex`, `weight`, `level`, `email`, `phone`).

`public/events/show.blade.php`: sustituir el comentario por `@if ($event->acceptsPublicRegistrations())<a class="btn btn-primary" href="{{ route('public.registration.create', $event) }}">Inscribirse</a>@endif`.

- [ ] **Step 3: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: formulario público de inscripción con salvaguardas" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Listas, impresión y exportación CSV de inscritos

**Files:**
- Create: `app/Support/Csv.php`, `app/Http/Controllers/Panel/ExportController.php`, `tests/Unit/CsvTest.php`, `tests/Feature/RegistrationExportTest.php`
- Modify: `routes/web.php`, `resources/views/panel/registrations/index.blade.php`

**Interfaces:**
- Consumes: `Registration::scopeFilter`, `Event::registrations()`.
- Produces: `Csv::download(string $filename, array $header, iterable $rows): StreamedResponse`; `Csv::cell(mixed): string` (neutraliza fórmulas); ruta `panel.exports.registrations` (`/panel/eventos/{event}/exportar/inscritos.csv`, acepta los mismos filtros `category_id,status,q`).

- [ ] **Step 1: Tests que fallan**

`tests/Unit/CsvTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_cell_neutralises_formulas(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Csv::cell($input));
    }

    public static function cells(): array
    {
        return [
            'texto' => ['Dojo Norte', 'Dojo Norte'],
            'null' => [null, ''],
            'número' => [58.5, '58.5'],
            'igual' => ['=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'],
            'más' => ['+34600', '\'+34600'],
            'menos' => ['-1', '\'-1'],
            'arroba' => ['@SUM(A1)', '\'@SUM(A1)'],
        ];
    }
}
```

`tests/Feature/RegistrationExportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_downloads_csv_with_filters_and_safe_cells(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $a = Category::factory()->for($event)->create(['name' => 'Cat A']);
        $b = Category::factory()->for($event)->create(['name' => 'Cat B']);
        $competitor = Competitor::factory()->for($event)->create(['first_name' => 'Ana', 'last_name' => 'Ruiz', 'club' => '=1+1']);
        Registration::factory()->confirmed()->create(['category_id' => $a->id, 'competitor_id' => $competitor->id]);
        Registration::factory()->forCategory($b)->create();

        $response = $this->actingAs($user)->get(route('panel.exports.registrations', ['event' => $event, 'category_id' => $a->id]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Categoría;Apellidos;Nombre', $csv);
        $this->assertStringContainsString('Cat A;Ruiz;Ana;\'=1+1', $csv);
        $this->assertStringNotContainsString('Cat B', $csv);
    }

    public function test_other_organizers_cannot_export(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('panel.exports.registrations', $event))->assertForbidden();
    }
}
```

Run: `php artisan test --filter="CsvTest|RegistrationExportTest"` → Expected: FAIL.

- [ ] **Step 2: Implementación**

`app/Support/Csv.php`:

```php
<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * CSV para Excel en español: UTF-8 con BOM y separador «;».
     *
     * @param  list<string>  $header
     * @param  iterable<list<mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(self::cell(...), $header), ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), ';', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita la inyección de fórmulas: una celda que empieza por = + - @ (o tab/CR) se prefija con '. */
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
```

`ExportController::registrations`:

```php
public function registrations(Request $request, Event $event): StreamedResponse
{
    $registrations = $event->registrations()->with(['competitor', 'category'])
        ->filter($request->only('category_id', 'status', 'q'))
        ->orderBy('categories.name')->orderBy('registrations.id')->get();

    $rows = $registrations->map(fn (Registration $r) => [
        $r->category->name, $r->competitor->last_name, $r->competitor->first_name, $r->competitor->club,
        $r->competitor->birth_date->format('d/m/Y'), $r->competitor->sex?->label(), $r->competitor->weight,
        $r->competitor->email, $r->competitor->phone, $r->status->label(),
    ]);

    return Csv::download(
        'inscritos-'.$event->slug.'.csv',
        ['Categoría', 'Apellidos', 'Nombre', 'Club', 'Fecha de nacimiento', 'Sexo', 'Peso', 'Email', 'Teléfono', 'Estado'],
        $rows,
    );
}
```

Ruta en el grupo del evento: `Route::get('exportar/inscritos.csv', [ExportController::class, 'registrations'])->name('exports.registrations');`

Vista `panel/registrations/index.blade.php`: añadir botones `no-print` «Exportar CSV» (`route('panel.exports.registrations', ['event' => $event] + $filters)`) e «Imprimir» (`<button class="btn btn-outline-secondary" onclick="window.print()">`); añadir una cabecera visible solo al imprimir: `<h1 class="h4 d-none d-print-block">{{ $event->name }} — Inscritos</h1>`; los botones de acción de cada fila y el formulario de filtros llevan `no-print`.

- [ ] **Step 3: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: listas con filtros, impresión y exportación CSV de inscritos" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Núcleo del torneo — persistencia, generador y avance (pases)

**Files:**
- Create: `database/migrations/2026_10_11_100400_create_bouts_table.php`, `app/Models/Bout.php`, `app/Tournament/{TournamentException,BracketGenerator,BracketAdvancer,RoundLabel}.php`, `tests/Concerns/BuildsBrackets.php`, `tests/Feature/Tournament/BracketGeneratorTest.php`, `tests/Feature/Tournament/RegistrationInBracketTest.php`
- Modify: `app/Models/Category.php` (`bouts()`, `hasResults()`, `bracketIsStale()`), `app/Models/Registration.php` (`isInBracket()`), `app/Registrations/RegistrationService.php` (guarda en `changeStatus`), `tests/Feature/OrganizerIsolationTest.php` (fixture `bout`)

**Interfaces:**
- Consumes: `Category`, `Registration`, `RegistrationStatus`, `BronzeRule`.
- Produces:
  - Tabla `bouts`: `id, category_id, number (null en pases), round, position, is_bronze, required_participants (2 normal, 1 pase), slot_a_id, slot_b_id, winner_id (→ registrations, nullOnDelete), next_bout_id, next_slot ('a'|'b'), loser_next_bout_id, loser_next_slot`.
  - `Bout`: `category()`, `slotA()`, `slotB()`, `winner()`, `filledSlots(): int`, `isBye(): bool` (pase = `required_participants === 1`), `isCompleted(): bool`, `isReady(): bool` (dos participantes y sin resultado, no pase), `isFinal(): bool` (no bronce y sin `next_bout_id`), `loserId(): ?int`.
  - `Category::bouts(): HasMany`, `Category::hasResults(): bool` (algún combate con `required_participants = 2` y ganador), `Category::bracketIsStale(): bool`.
  - `Registration::isInBracket(): bool`.
  - `BracketGenerator::generate(Category $category, ?Closure $shuffler = null): void` — `$shuffler` recibe `Collection<int,int>` de ids de inscripción y devuelve la misma colección reordenada; por defecto `->shuffle()`.
  - `BracketAdvancer::settle(Category): void` (resuelve pases), `::complete(Bout $bout, int $winnerId): void` (fija ganador y coloca ganador/perdedor en los combates destino, resolviendo pases en cascada), `::reset(Category): void` (limpia rondas ≥2 y ganadores, luego `settle`).
  - `TournamentException extends \DomainException`.
  - `RoundLabel::for(int $round, int $totalRounds): string`.
  - Trait de tests `BuildsBrackets`: `categoryWithConfirmed(int $n, BronzeRule $rule = BronzeMatch): Category`, `generated(int $n, BronzeRule $rule = BronzeMatch): Category` (orden de siembra determinista = ids ascendentes), `bout(Category, int $round, int $position, bool $bronze = false): Bout`.

- [ ] **Step 1: Trait de tests y tests que fallan**

`tests/Concerns/BuildsBrackets.php`:

```php
<?php

namespace Tests\Concerns;

use App\Enums\BronzeRule;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Registration;
use App\Tournament\BracketGenerator;

trait BuildsBrackets
{
    protected function categoryWithConfirmed(int $n, BronzeRule $rule = BronzeRule::BronzeMatch): Category
    {
        $category = Category::factory()->create(['bronze_rule' => $rule]);
        Registration::factory()->count($n)->confirmed()->forCategory($category)->create();

        return $category;
    }

    /** Genera el cuadro con orden de siembra determinista (ids ascendentes = sorteo identidad). */
    protected function generated(int $n, BronzeRule $rule = BronzeRule::BronzeMatch): Category
    {
        $category = $this->categoryWithConfirmed($n, $rule);
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return $category->refresh();
    }

    protected function bout(Category $category, int $round, int $position, bool $bronze = false): Bout
    {
        return $category->bouts()->where('round', $round)->where('position', $position)->where('is_bronze', $bronze)->firstOrFail();
    }
}
```

`tests/Feature/Tournament/BracketGeneratorTest.php`:

```php
<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Registration;
use App\Tournament\BracketGenerator;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class BracketGeneratorTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    public function test_zero_or_one_participant_generates_nothing_and_never_names_a_champion(): void
    {
        foreach ([0, 1] as $n) {
            $category = $this->categoryWithConfirmed($n);
            try {
                app(BracketGenerator::class)->generate($category);
                $this->fail("n={$n} debió lanzar excepción");
            } catch (TournamentException) {
                $this->assertSame(0, $category->bouts()->count());
            }
        }
    }

    public function test_only_confirmed_registrations_enter_the_bracket(): void
    {
        $category = $this->categoryWithConfirmed(3);
        Registration::factory()->forCategory($category)->create(['status' => RegistrationStatus::Pending]);
        Registration::factory()->forCategory($category)->create(['status' => RegistrationStatus::Cancelled]);

        app(BracketGenerator::class)->generate($category);

        $inBracket = $category->bouts()->where('round', 1)->get()->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter()->all();
        $this->assertCount(3, $inBracket);
        $this->assertEqualsCanonicalizing(
            $category->registrations()->where('status', 'confirmed')->pluck('id')->all(),
            $inBracket,
        );
    }

    public function test_two_participants_generate_a_single_final(): void
    {
        $category = $this->generated(2);

        $this->assertSame(1, $category->bouts()->count());
        $final = $this->bout($category, 1, 1);
        $this->assertTrue($final->isFinal());
        $this->assertTrue($final->isReady());
        $this->assertNotNull($final->number);
    }

    public function test_four_participants_have_semis_final_and_bronze_without_byes(): void
    {
        $category = $this->generated(4);

        $this->assertSame(4, $category->bouts()->count());
        $this->assertSame(0, $category->bouts()->where('required_participants', 1)->count());
        $bronze = $this->bout($category, 2, 1, true);
        $this->assertSame(2, $bronze->required_participants);
        $this->assertSame($bronze->id, $this->bout($category, 1, 1)->loser_next_bout_id);
        $this->assertSame($bronze->id, $this->bout($category, 1, 2)->loser_next_bout_id);
        $this->assertSame([1, 2, 3, 4], $category->bouts()->orderBy('number')->pluck('number')->all());
    }

    public function test_five_participants_get_three_byes_that_advance_automatically(): void
    {
        $category = $this->generated(5);
        $ids = $category->registrations()->orderBy('id')->pluck('id');

        // 8 plazas: 4 de primera ronda + 2 semis + final + bronce.
        $this->assertSame(8, $category->bouts()->count());
        $byes = $category->bouts()->where('round', 1)->where('required_participants', 1)->get();
        $this->assertCount(3, $byes);
        $this->assertTrue($byes->every(fn ($b) => $b->isCompleted() && $b->number === null));

        // Siembra estándar con sorteo identidad: 1-pase, 4v5, 2-pase, 3v6(pase)
        $m2 = $this->bout($category, 1, 2);
        $this->assertSame([$ids[3], $ids[4]], [$m2->slot_a_id, $m2->slot_b_id]);
        $this->assertTrue($m2->isReady());

        $semi1 = $this->bout($category, 2, 1);
        $this->assertSame($ids[0], $semi1->slot_a_id);   // el cabeza de la plaza 1 pasa directo
        $this->assertNull($semi1->slot_b_id);            // espera al ganador de 4v5

        // Ningún participante aparece dos veces en primera ronda y no hay dos pases enfrentados.
        $round1 = $category->bouts()->where('round', 1)->get();
        $placed = $round1->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter();
        $this->assertSame($placed->count(), $placed->unique()->count());
        $this->assertTrue($round1->every(fn ($b) => $b->filledSlots() >= 1));

        // Numeración contigua solo para combates reales (2 de primera ronda... y 2 semis, bronce, final).
        $real = $category->bouts()->whereNotNull('number')->orderBy('number')->pluck('number')->all();
        $this->assertSame(range(1, count($real)), $real);
        $this->assertCount(5, $real);
    }

    public function test_three_participants_bronze_bout_is_a_single_participant_pase(): void
    {
        $category = $this->generated(3);
        $bronze = $this->bout($category, 2, 1, true);

        $this->assertSame(1, $bronze->required_participants);
        $this->assertNull($bronze->number);
        $this->assertSame(1, $category->bouts()->whereNull('number')->where('is_bronze', false)->count()); // el pase de semifinal
    }

    public function test_shared_bronze_creates_no_bronze_bout(): void
    {
        $category = $this->generated(4, BronzeRule::SharedBronze);

        $this->assertSame(3, $category->bouts()->count());
        $this->assertFalse($category->bouts()->where('is_bronze', true)->exists());
    }

    public function test_default_shuffle_places_every_participant_exactly_once(): void
    {
        $category = $this->categoryWithConfirmed(9);
        app(BracketGenerator::class)->generate($category);

        $placed = $category->bouts()->where('round', 1)->get()->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter();
        $this->assertCount(9, $placed->unique());
    }

    public function test_regenerate_replaces_the_bracket_while_there_are_no_results(): void
    {
        $category = $this->generated(4);
        Registration::factory()->confirmed()->forCategory($category)->create();

        app(BracketGenerator::class)->generate($category->refresh());

        $this->assertSame(8, $category->bouts()->count()); // 5 participantes ahora
        $this->assertNotNull($category->fresh()->bracket_generated_at);
    }

    public function test_regenerate_is_refused_once_a_result_exists(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        $semi->update(['winner_id' => $semi->slot_a_id]);

        $this->expectException(TournamentException::class);
        app(BracketGenerator::class)->generate($category->refresh());
    }
}
```

`tests/Feature/Tournament/RegistrationInBracketTest.php` (Review Focus 2):

```php
<?php

namespace Tests\Feature\Tournament;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class RegistrationInBracketTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    public function test_cancelling_before_results_marks_the_bracket_stale(): void
    {
        $category = $this->generated(4);
        $this->assertFalse($category->bracketIsStale());

        $registration = $category->registrations()->first();
        app(RegistrationService::class)->changeStatus($registration, RegistrationStatus::Cancelled);

        $this->assertTrue($category->refresh()->bracketIsStale());
    }

    public function test_confirming_a_new_registration_marks_the_bracket_stale(): void
    {
        $category = $this->generated(4);
        Registration::factory()->confirmed()->forCategory($category)->create();

        $this->assertTrue($category->refresh()->bracketIsStale());
    }

    public function test_cancelling_a_participant_is_blocked_once_results_exist(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        $semi->update(['winner_id' => $semi->slot_a_id]);

        $this->expectException(RegistrationException::class);
        app(RegistrationService::class)->changeStatus(Registration::find($semi->slot_b_id), RegistrationStatus::Cancelled);
    }
}
```

Run: `php artisan test --filter="BracketGeneratorTest|RegistrationInBracketTest"` → Expected: FAIL.

- [ ] **Step 2: Migración y modelo `Bout`**

Migración `bouts`:

```php
Schema::create('bouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('number')->nullable();          // orden de combate; null en pases
    $table->unsignedTinyInteger('round');                        // 1 = primera ronda
    $table->unsignedSmallInteger('position');                    // posición dentro de la ronda
    $table->boolean('is_bronze')->default(false);
    $table->unsignedTinyInteger('required_participants')->default(2); // 1 = pase
    $table->foreignId('slot_a_id')->nullable()->constrained('registrations')->nullOnDelete();
    $table->foreignId('slot_b_id')->nullable()->constrained('registrations')->nullOnDelete();
    $table->foreignId('winner_id')->nullable()->constrained('registrations')->nullOnDelete();
    $table->foreignId('next_bout_id')->nullable()->constrained('bouts')->nullOnDelete();
    $table->char('next_slot', 1)->nullable();
    $table->foreignId('loser_next_bout_id')->nullable()->constrained('bouts')->nullOnDelete();
    $table->char('loser_next_slot', 1)->nullable();
    $table->timestamps();

    $table->unique(['category_id', 'round', 'position', 'is_bronze']);
});
```

`app/Models/Bout.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id', 'number', 'round', 'position', 'is_bronze', 'required_participants',
    'slot_a_id', 'slot_b_id', 'winner_id', 'next_bout_id', 'next_slot', 'loser_next_bout_id', 'loser_next_slot',
])]
class Bout extends Model
{
    protected function casts(): array
    {
        return ['is_bronze' => 'boolean'];
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function slotA(): BelongsTo { return $this->belongsTo(Registration::class, 'slot_a_id'); }

    public function slotB(): BelongsTo { return $this->belongsTo(Registration::class, 'slot_b_id'); }

    public function winner(): BelongsTo { return $this->belongsTo(Registration::class, 'winner_id'); }

    public function filledSlots(): int
    {
        return (int) ($this->slot_a_id !== null) + (int) ($this->slot_b_id !== null);
    }

    /** Pase: combate que se resuelve solo porque solo hay un participante posible. */
    public function isBye(): bool
    {
        return $this->required_participants === 1;
    }

    public function isCompleted(): bool
    {
        return $this->winner_id !== null;
    }

    /** Listo para registrar resultado: ambos participantes definidos y aún sin ganador. */
    public function isReady(): bool
    {
        return ! $this->isBye() && ! $this->isCompleted() && $this->filledSlots() === 2;
    }

    public function isFinal(): bool
    {
        return ! $this->is_bronze && $this->next_bout_id === null;
    }

    public function loserId(): ?int
    {
        if (! $this->isCompleted() || $this->isBye()) {
            return null;
        }

        return $this->slot_a_id === $this->winner_id ? $this->slot_b_id : $this->slot_a_id;
    }
}
```

`Category`: añadir

```php
public function bouts(): HasMany { return $this->hasMany(Bout::class); }

/** Resultados reales (los pases automáticos no cuentan). */
public function hasResults(): bool
{
    return $this->bouts()->where('required_participants', 2)->whereNotNull('winner_id')->exists();
}

/** El cuadro ya no refleja las inscripciones confirmadas (o la regla del bronce): hay que regenerarlo. */
public function bracketIsStale(): bool
{
    $bouts = $this->bouts()->get();
    if ($bouts->isEmpty()) {
        return false;
    }

    $inBracket = $bouts->where('round', 1)->flatMap(fn (Bout $b) => [$b->slot_a_id, $b->slot_b_id])->filter()->sort()->values()->all();
    $confirmed = $this->registrations()->where('status', 'confirmed')->orderBy('id')->pluck('id')->all();
    $expectsBronze = $this->bronze_rule === BronzeRule::BronzeMatch && $bouts->max('round') >= 2;

    return $inBracket !== $confirmed || $bouts->contains('is_bronze', true) !== $expectsBronze;
}
```

`Registration`: `isInBracket(): bool { return Bout::where(fn ($q) => $q->where('slot_a_id', $this->id)->orWhere('slot_b_id', $this->id))->exists(); }`.

`RegistrationService::changeStatus`:

```php
public function changeStatus(Registration $registration, RegistrationStatus $status): void
{
    if ($status !== RegistrationStatus::Confirmed
        && $registration->category->hasResults()
        && $registration->isInBracket()) {
        throw new RegistrationException('El cuadro ya tiene resultados: la baja de este participante debe resolverse manualmente según el reglamento.');
    }

    $registration->update(['status' => $status]);
}
```

- [ ] **Step 3: Excepción, `RoundLabel`, `BracketAdvancer`**

`TournamentException`: `class TournamentException extends \DomainException {}`.

`RoundLabel`:

```php
final class RoundLabel
{
    public static function for(int $round, int $totalRounds): string
    {
        return match ($totalRounds - $round) {
            0 => 'Final',
            1 => 'Semifinales',
            2 => 'Cuartos de final',
            3 => 'Octavos de final',
            default => 'Ronda '.$round,
        };
    }
}
```

`BracketAdvancer`:

```php
<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Category;

final class BracketAdvancer
{
    /** Resuelve los pases cuyo único participante ya está colocado. */
    public function settle(Category $category): void
    {
        $category->bouts()->where('required_participants', 1)->whereNull('winner_id')
            ->orderBy('round')->orderBy('position')->get()
            ->each(function (Bout $bout) {
                $bout->refresh();
                if (! $bout->isCompleted() && $bout->filledSlots() === 1) {
                    $this->complete($bout, $bout->slot_a_id ?? $bout->slot_b_id);
                }
            });
    }

    /** Fija el ganador y coloca ganador y perdedor en sus combates destino (en cascada para pases). */
    public function complete(Bout $bout, int $winnerId): void
    {
        $loserId = $bout->isBye() ? null : ($bout->slot_a_id === $winnerId ? $bout->slot_b_id : $bout->slot_a_id);

        $bout->update(['winner_id' => $winnerId]);

        $this->place($bout->next_bout_id, $bout->next_slot, $winnerId);
        $this->place($bout->loser_next_bout_id, $bout->loser_next_slot, $loserId);
    }

    /** Vuelve al estado inicial de la primera ronda (solo válido sin resultados reales) y re-resuelve los pases. */
    public function reset(Category $category): void
    {
        $category->bouts()->where('round', '>', 1)->update(['slot_a_id' => null, 'slot_b_id' => null]);
        $category->bouts()->update(['winner_id' => null]);

        $this->settle($category);
    }

    private function place(?int $boutId, ?string $slot, ?int $registrationId): void
    {
        if ($boutId === null || $slot === null || $registrationId === null) {
            return;
        }

        $target = Bout::findOrFail($boutId);
        $target->update([$slot === 'a' ? 'slot_a_id' : 'slot_b_id' => $registrationId]);

        if ($target->isBye() && $target->filledSlots() === 1) {
            $this->complete($target, $registrationId);
        }
    }
}
```

- [ ] **Step 4: `BracketGenerator`**

```php
<?php

namespace App\Tournament;

use App\Enums\BronzeRule;
use App\Enums\RegistrationStatus;
use App\Models\Bout;
use App\Models\Category;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BracketGenerator
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /**
     * @param  Closure(Collection<int,int>): Collection<int,int>|null  $shuffler  reordena los ids de inscripción (sorteo)
     *
     * @throws TournamentException
     */
    public function generate(Category $category, ?Closure $shuffler = null): void
    {
        if ($category->hasResults()) {
            throw new TournamentException('La categoría ya tiene resultados: el cuadro no se puede regenerar.');
        }

        $ids = $category->registrations()->where('status', RegistrationStatus::Confirmed->value)->orderBy('id')->pluck('id');

        if ($ids->count() === 1) {
            throw new TournamentException('Solo hay un inscrito confirmado: no se genera cuadro. Resuelve la clasificación manualmente según el reglamento.');
        }
        if ($ids->isEmpty()) {
            throw new TournamentException('No hay inscripciones confirmadas para generar el cuadro.');
        }

        $ordered = ($shuffler ?? fn (Collection $c) => $c->shuffle())($ids)->values();

        DB::transaction(function () use ($category, $ordered) {
            $category->bouts()->update(['next_bout_id' => null, 'loser_next_bout_id' => null]);
            $category->bouts()->delete();

            $this->build($category, $ordered);

            $category->forceFill(['bracket_generated_at' => now()])->save();
            $this->advancer->settle($category);
        });
    }

    private function build(Category $category, Collection $ids): void
    {
        $n = $ids->count();
        $size = 2;
        $rounds = 1;
        while ($size < $n) {
            $size *= 2;
            $rounds++;
        }

        $seeds = $this->seedOrder($size);
        $number = 0;
        /** @var array<int,array<int,Bout>> $grid */
        $grid = [];
        $bronze = null;

        for ($round = 1; $round <= $rounds; $round++) {
            // El combate por el bronce se disputa antes que la final.
            if ($round === $rounds && $rounds >= 2 && $category->bronze_rule === BronzeRule::BronzeMatch) {
                $semiByes = $rounds === 2 ? count(array_filter($grid[1], fn (Bout $b) => $b->isBye())) : 0;
                $bronze = $this->create($category, ['round' => $round, 'position' => 1, 'is_bronze' => true, 'required_participants' => 2 - $semiByes], $number);
            }

            for ($p = 1; $p <= ($size >> $round); $p++) {
                $attributes = ['round' => $round, 'position' => $p, 'required_participants' => 2];

                if ($round === 1) {
                    $slotA = $ids[$seeds[2 * ($p - 1)] - 1] ?? null;
                    $slotB = $ids[$seeds[2 * ($p - 1) + 1] - 1] ?? null;
                    $attributes += ['slot_a_id' => $slotA, 'slot_b_id' => $slotB];
                    $attributes['required_participants'] = ($slotA && $slotB) ? 2 : 1;
                }

                $grid[$round][$p] = $this->create($category, $attributes, $number);
            }
        }

        foreach ($grid as $round => $bouts) {
            if ($round === $rounds) {
                continue;
            }
            foreach ($bouts as $p => $bout) {
                $bout->next_bout_id = $grid[$round + 1][intdiv($p + 1, 2)]->id;
                $bout->next_slot = $p % 2 === 1 ? 'a' : 'b';
                if ($bronze && $round === $rounds - 1) {
                    $bout->loser_next_bout_id = $bronze->id;
                    $bout->loser_next_slot = $p === 1 ? 'a' : 'b';
                }
                $bout->save();
            }
        }
    }

    private function create(Category $category, array $attributes, int &$number): Bout
    {
        $required = $attributes['required_participants'];

        return $category->bouts()->create($attributes + ['number' => $required === 2 ? ++$number : null]);
    }

    /**
     * Orden de siembra estándar: [1,2] → [1,4,2,3] → [1,8,4,5,2,7,3,6]…
     * Las plazas por encima de n son pases; con n > size/2 nunca se enfrentan dos pases.
     *
     * @return list<int>
     */
    private function seedOrder(int $size): array
    {
        $order = [1];
        for ($n = 1; $n < $size; $n *= 2) {
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = 2 * $n + 1 - $seed;
            }
            $order = $next;
        }

        return $order;
    }
}
```

> `Bout` creado con `'number' => null` y atributo `required_participants`: ambos están en `#[Fillable]` ✓. `$bout->isBye()` en el cálculo de `$semiByes` funciona porque `required_participants` se persistió al crear (se castea a int al refrescar; tras `create` el atributo mantiene el int pasado).

- [ ] **Step 5: Ampliar test de aislamiento**

En `fixtureParameters` añadir `$bout = Bout::factory()...`: no hay factory; usar `Bout::create(['category_id' => $category->id, 'round' => 1, 'position' => 1])` y devolver `'bout' => $bout->id`.

- [ ] **Step 6: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: núcleo del torneo persistente (generador, pases y avance)" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 10: Registro de resultados y ajuste del cuadro

**Files:**
- Create: `app/Tournament/{ResultRecorder,BracketAdjuster}.php`, `tests/Feature/Tournament/{ResultRecorderTest,BracketAdjusterTest}.php`
- Modify: `tests/Feature/CategoryTest.php` (test de regla del bronce), `app/Http/Requests/CategoryRequest.php`

**Interfaces:**
- Consumes: `BracketAdvancer::{complete,reset}`, `Bout` helpers, `Category::hasResults()`, `EventStatus::Finished`.
- Produces: `ResultRecorder::record(Bout $bout, int $winnerRegistrationId): void` (lanza `TournamentException`); `BracketAdjuster::swap(Category $category, int $registrationA, int $registrationB): void` (lanza `TournamentException`).

- [ ] **Step 1: Tests que fallan** (Review Focus 4)

`tests/Feature/Tournament/ResultRecorderTest.php`:

```php
<?php

namespace Tests\Feature\Tournament;

use App\Enums\EventStatus;
use App\Models\Registration;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class ResultRecorderTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function recorder(): ResultRecorder
    {
        return app(ResultRecorder::class);
    }

    public function test_winner_advances_and_loser_goes_to_bronze(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);

        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);          // final, hueco a
        $this->assertSame($semi->slot_b_id, $this->bout($category, 2, 1, true)->slot_a_id);    // bronce, hueco a
        $this->assertSame($semi->slot_a_id, $semi->fresh()->winner_id);
    }

    public function test_cannot_record_before_both_participants_are_defined(): void
    {
        $category = $this->generated(5);
        $final = $category->bouts()->where('is_bronze', false)->orderByDesc('round')->first();

        $this->expectException(TournamentException::class);
        $this->recorder()->record($final, $final->slot_a_id ?? Registration::first()->id);
    }

    public function test_cannot_record_a_pase(): void
    {
        $category = $this->generated(5);
        $bye = $category->bouts()->where('required_participants', 1)->first();

        $this->expectException(TournamentException::class);
        $this->recorder()->record($bye, $bye->slot_a_id ?? $bye->slot_b_id);
    }

    public function test_winner_must_be_one_of_the_two_participants_and_nothing_changes(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        $stranger = $this->generated(2)->registrations()->first(); // otra categoría

        foreach ([$stranger->id, 999999, $this->bout($category, 1, 2)->slot_a_id] as $bad) {
            try {
                $this->recorder()->record($semi, $bad);
                $this->fail('Debió rechazar el ganador '.$bad);
            } catch (TournamentException) {
            }
        }

        $this->assertNull($semi->fresh()->winner_id);
        $this->assertNull($this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_recording_the_same_winner_twice_is_idempotent(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->recorder()->record($semi->fresh(), $semi->slot_a_id);

        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_winner_can_be_corrected_while_dependent_bouts_have_no_result(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->recorder()->record($semi->fresh(), $semi->slot_b_id);

        $this->assertSame($semi->slot_b_id, $this->bout($category, 2, 1)->slot_a_id);
        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1, true)->slot_a_id);
    }

    public function test_correction_is_refused_when_the_final_already_has_a_result(): void
    {
        $category = $this->generated(4);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->recorder()->record($s1, $s1->slot_a_id);
        $this->recorder()->record($s2, $s2->slot_a_id);
        $final = $this->bout($category, 2, 1)->refresh();
        $this->recorder()->record($final, $final->slot_a_id);

        try {
            $this->recorder()->record($s1->fresh(), $s1->slot_b_id);
            $this->fail('Debió rechazar la corrección');
        } catch (TournamentException) {
            $this->assertSame($s1->slot_a_id, $s1->fresh()->winner_id);
        }
    }

    public function test_with_three_participants_the_semifinal_loser_takes_bronze_automatically(): void
    {
        $category = $this->generated(3);
        $semi = $this->bout($category, 1, 2); // el único combate real de primera ronda
        $bronze = $this->bout($category, 2, 1, true);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->assertSame($semi->slot_b_id, $bronze->fresh()->winner_id);

        // Corregir la semifinal reasigna el bronce.
        $this->recorder()->record($semi->fresh(), $semi->slot_b_id);
        $this->assertSame($semi->slot_a_id, $bronze->fresh()->winner_id);
    }

    public function test_finished_events_do_not_accept_results(): void
    {
        $category = $this->generated(4);
        $category->event->update(['status' => EventStatus::Finished]);
        $semi = $this->bout($category, 1, 1);

        $this->expectException(TournamentException::class);
        $this->recorder()->record($semi, $semi->slot_a_id);
    }
}
```

`tests/Feature/Tournament/BracketAdjusterTest.php`:

```php
<?php

namespace Tests\Feature\Tournament;

use App\Tournament\BracketAdjuster;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class BracketAdjusterTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    public function test_swap_exchanges_two_participants(): void
    {
        $category = $this->generated(4);
        $a = $this->bout($category, 1, 1)->slot_a_id;
        $b = $this->bout($category, 1, 2)->slot_a_id;

        app(BracketAdjuster::class)->swap($category, $a, $b);

        $this->assertSame($b, $this->bout($category, 1, 1)->slot_a_id);
        $this->assertSame($a, $this->bout($category, 1, 2)->slot_a_id);
    }

    public function test_swap_updates_the_participant_that_advances_by_a_pase(): void
    {
        $category = $this->generated(3);
        $withPase = $this->bout($category, 1, 1);
        $other = $this->bout($category, 1, 2);
        $this->assertSame($withPase->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);

        app(BracketAdjuster::class)->swap($category, $withPase->slot_a_id, $other->slot_a_id);

        $this->assertSame($other->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_swap_is_refused_after_a_result_and_for_unknown_participants(): void
    {
        $category = $this->generated(4);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);

        try {
            app(BracketAdjuster::class)->swap($category, $s1->slot_a_id, 999999);
            $this->fail('Debió rechazar un participante desconocido');
        } catch (TournamentException) {
        }

        app(ResultRecorder::class)->record($s1, $s1->slot_a_id);

        $this->expectException(TournamentException::class);
        app(BracketAdjuster::class)->swap($category->refresh(), $s2->slot_a_id, $s2->slot_b_id);
    }

    public function test_swapping_a_participant_with_itself_is_refused(): void
    {
        $category = $this->generated(4);
        $id = $this->bout($category, 1, 1)->slot_a_id;

        $this->expectException(TournamentException::class);
        app(BracketAdjuster::class)->swap($category, $id, $id);
    }
}
```

Añadir a `tests/Feature/CategoryTest.php`:

```php
public function test_bronze_rule_cannot_change_once_results_exist(): void
{
    $category = $this->generated(4);
    $semi = $this->bout($category, 1, 1);
    app(\App\Tournament\ResultRecorder::class)->record($semi, $semi->slot_a_id);
    $event = $category->event;

    $this->actingAs($event->user)->put(route('panel.categories.update', [$event, $category]), $this->payload(['name' => $category->name, 'bronze_rule' => 'shared_bronze']))
        ->assertSessionHasErrors('bronze_rule');
}
```

(y `use Tests\Concerns\BuildsBrackets;` + `use BuildsBrackets, RefreshDatabase;` en la clase).

Run: `php artisan test --filter="ResultRecorderTest|BracketAdjusterTest|CategoryTest"` → Expected: FAIL.

- [ ] **Step 2: `ResultRecorder`**

```php
<?php

namespace App\Tournament;

use App\Enums\EventStatus;
use App\Models\Bout;
use Illuminate\Support\Facades\DB;

final class ResultRecorder
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /** @throws TournamentException */
    public function record(Bout $bout, int $winnerRegistrationId): void
    {
        $bout = $bout->fresh();

        if ($bout->category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para corregir resultados.');
        }
        if ($bout->isBye()) {
            throw new TournamentException('Un pase avanza automáticamente: no admite resultado.');
        }
        if ($bout->filledSlots() !== 2) {
            throw new TournamentException('Todavía no están definidos ambos participantes de este combate.');
        }
        if (! in_array($winnerRegistrationId, [$bout->slot_a_id, $bout->slot_b_id], true)) {
            throw new TournamentException('El ganador debe ser uno de los dos participantes del combate.');
        }
        if ($bout->winner_id === $winnerRegistrationId) {
            return; // idempotente
        }

        DB::transaction(function () use ($bout, $winnerRegistrationId) {
            if ($bout->isCompleted()) {
                $this->assertCorrectable($bout);
                $this->retract($bout);
                $bout->refresh();
            }

            $this->advancer->complete($bout, $winnerRegistrationId);
        });
    }

    private function dependents(Bout $bout): array
    {
        return array_filter([
            [$bout->next_bout_id, $bout->next_slot],
            [$bout->loser_next_bout_id, $bout->loser_next_slot],
        ], fn (array $d) => $d[0] !== null);
    }

    private function assertCorrectable(Bout $bout): void
    {
        foreach ($this->dependents($bout) as [$id]) {
            $target = Bout::findOrFail($id);
            if ($target->isCompleted() && ! $target->isBye()) {
                throw new TournamentException('Hay combates posteriores con resultado: corrígelos primero.');
            }
        }
    }

    /** Deshace la colocación de ganador y perdedor derivada de este combate. */
    private function retract(Bout $bout): void
    {
        foreach ($this->dependents($bout) as [$id, $slot]) {
            $target = Bout::findOrFail($id);
            if ($target->isBye() && $target->isCompleted()) {
                $this->retract($target);
                $target->refresh();
            }
            $target->update([$slot === 'a' ? 'slot_a_id' : 'slot_b_id' => null, 'winner_id' => $target->isBye() ? null : $target->winner_id]);
        }

        $bout->update(['winner_id' => null]);
    }
}
```

- [ ] **Step 3: `BracketAdjuster`**

```php
<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BracketAdjuster
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /**
     * Intercambia dos participantes de primera ronda. La estructura de pases no cambia.
     *
     * @throws TournamentException
     */
    public function swap(Category $category, int $registrationA, int $registrationB): void
    {
        if ($registrationA === $registrationB) {
            throw new TournamentException('Elige dos participantes distintos.');
        }
        if ($category->hasResults()) {
            throw new TournamentException('El cuadro ya tiene resultados: no se puede ajustar.');
        }

        $bouts = $category->bouts()->where('round', 1)->get();
        [$boutA, $slotA] = $this->locate($bouts, $registrationA);
        [$boutB, $slotB] = $this->locate($bouts, $registrationB);

        DB::transaction(function () use ($category, $boutA, $slotA, $boutB, $slotB, $registrationA, $registrationB) {
            $boutA->{$slotA} = $registrationB;
            $boutB->{$slotB} = $registrationA;
            $boutA->save();
            $boutB->save();

            $this->advancer->reset($category);
        });
    }

    /** @return array{0: Bout, 1: string} modelo y nombre de columna */
    private function locate(Collection $bouts, int $registrationId): array
    {
        foreach ($bouts as $bout) {
            foreach (['slot_a_id', 'slot_b_id'] as $column) {
                if ($bout->{$column} === $registrationId) {
                    return [$bout, $column];
                }
            }
        }

        throw new TournamentException('El participante no está en la primera ronda del cuadro.');
    }
}
```

> En `swap`, `$boutA` y `$boutB` pueden ser la misma instancia (misma pareja): ambas asignaciones se aplican sobre el mismo objeto y se guarda dos veces; correcto.

- [ ] **Step 4: Regla del bronce bloqueada con resultados**

En `CategoryRequest::rules()` sustituir la regla de `bronze_rule` por:

```php
'bronze_rule' => ['required', Rule::enum(BronzeRule::class), function (string $attribute, mixed $value, \Closure $fail) {
    $category = $this->route('category');
    if ($category && $category->hasResults() && $category->bronze_rule->value !== $value) {
        $fail('No se puede cambiar la regla del bronce cuando ya hay resultados.');
    }
}],
```

- [ ] **Step 5: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: registro de resultados, correcciones y ajuste del cuadro" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Podio

**Files:**
- Create: `app/Tournament/{Placement,PodiumCalculator}.php`, `tests/Feature/Tournament/PodiumCalculatorTest.php`

**Interfaces:**
- Consumes: `Bout`, `Category`, `BronzeRule`, `ResultRecorder` (en tests).
- Produces: `Placement` (`readonly`, `public int $place`, `public Registration $registration`); `PodiumCalculator::for(Category $category): list<Placement>` — vacío hasta que la final tenga ganador; 1.º ganador de la final, 2.º su rival; con `BronzeMatch`: 3.º ganador del bronce si está resuelto y 4.º su rival solo si `show_fourth`; con `SharedBronze`: un 3.º por cada perdedor de semifinal que exista (sin 4.º).

- [ ] **Step 1: Tests que fallan**

`tests/Feature/Tournament/PodiumCalculatorTest.php`:

```php
<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Models\Category;
use App\Tournament\PodiumCalculator;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class PodiumCalculatorTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    /** Juega todos los combates listos: gana siempre el hueco A. */
    private function playAll(Category $category): void
    {
        $recorder = app(ResultRecorder::class);
        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $recorder->record($bout, $bout->slot_a_id);
        }
    }

    private function podium(Category $category): array
    {
        return collect(app(PodiumCalculator::class)->for($category->refresh()))
            ->map(fn ($p) => [$p->place, $p->registration->id])->all();
    }

    public function test_no_podium_until_the_final_is_decided(): void
    {
        $category = $this->generated(4);

        $this->assertSame([], $this->podium($category));
    }

    public function test_four_participants_with_bronze_match_and_fourth(): void
    {
        $category = $this->generated(4);
        $category->update(['show_fourth' => true]);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->playAll($category);

        $final = $this->bout($category, 2, 1);
        $bronze = $this->bout($category, 2, 1, true);

        $this->assertSame([
            [1, $final->winner_id], [2, $final->loserId()], [3, $bronze->fresh()->winner_id], [4, $bronze->fresh()->loserId()],
        ], $this->podium($category));
        $this->assertEqualsCanonicalizing([$s1->slot_b_id, $s2->slot_b_id], [$bronze->fresh()->slot_a_id, $bronze->fresh()->slot_b_id]);
    }

    public function test_fourth_is_hidden_unless_enabled(): void
    {
        $category = $this->generated(4);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0));
    }

    public function test_two_participants_only_have_first_and_second(): void
    {
        $category = $this->generated(2);
        $this->playAll($category);

        $this->assertSame([1, 2], array_column($this->podium($category), 0));
    }

    public function test_three_participants_third_comes_from_the_automatic_bronze(): void
    {
        $category = $this->generated(3);
        $category->update(['show_fourth' => true]);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0)); // no hay 4.º
    }

    public function test_shared_bronze_gives_two_thirds_and_no_fourth(): void
    {
        $category = $this->generated(4, BronzeRule::SharedBronze);
        $category->update(['show_fourth' => true]);
        $this->playAll($category);

        $this->assertSame([1, 2, 3, 3], array_column($this->podium($category), 0));
    }

    public function test_five_participants_full_flow(): void
    {
        $category = $this->generated(5);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0));
    }
}
```

Run: `php artisan test --filter=PodiumCalculatorTest` → Expected: FAIL.

- [ ] **Step 2: Implementación**

`Placement.php`:

```php
<?php

namespace App\Tournament;

use App\Models\Registration;

final readonly class Placement
{
    public function __construct(public int $place, public Registration $registration) {}
}
```

`PodiumCalculator.php`:

```php
<?php

namespace App\Tournament;

use App\Enums\BronzeRule;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Registration;
use Illuminate\Support\Collection;

final class PodiumCalculator
{
    /** @return list<Placement> */
    public function for(Category $category): array
    {
        $bouts = $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner.competitor'])->get();
        $final = $bouts->first(fn (Bout $b) => $b->isFinal());

        if (! $final || ! $final->isCompleted()) {
            return [];
        }

        $placements = [new Placement(1, $final->winner), new Placement(2, $this->loser($final))];

        if ($category->bronze_rule === BronzeRule::BronzeMatch) {
            $bronze = $bouts->first(fn (Bout $b) => $b->is_bronze);
            if ($bronze?->isCompleted()) {
                $placements[] = new Placement(3, $bronze->winner);
                if ($category->show_fourth && ! $bronze->isBye()) {
                    $placements[] = new Placement(4, $this->loser($bronze));
                }
            }
        } else {
            foreach ($this->semifinals($bouts, $final) as $semi) {
                if ($semi->isCompleted() && ! $semi->isBye()) {
                    $placements[] = new Placement(3, $this->loser($semi));
                }
            }
        }

        return $placements;
    }

    /** @return Collection<int,Bout> */
    private function semifinals(Collection $bouts, Bout $final): Collection
    {
        return $final->round >= 2
            ? $bouts->where('round', $final->round - 1)->where('is_bronze', false)->sortBy('position')->values()
            : collect();
    }

    private function loser(Bout $bout): Registration
    {
        return $bout->slot_a_id === $bout->winner_id ? $bout->slotB : $bout->slotA;
    }
}
```

> Con `SharedBronze` la categoría puede tener `show_fourth = true`; se ignora en el cálculo.

- [ ] **Step 3: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: cálculo del podio con regla del bronce configurable" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Interfaz de cuadros y combates (organizador)

**Files:**
- Create: `app/Http/Controllers/Panel/{BracketController,BoutResultController}.php`, vistas `panel/brackets/show.blade.php`, `partials/{bracket,bout-card,podium}.blade.php`, `tests/Feature/BracketPanelTest.php`
- Modify: `routes/web.php`, `app/Models/Competitor.php` (`displayName()`), `panel/events/show.blade.php` (enlace «Cuadro»)

**Interfaces:**
- Consumes: `BracketGenerator`, `BracketAdjuster`, `ResultRecorder`, `PodiumCalculator`, `RoundLabel`, `Category::{hasResults,bracketIsStale}`.
- Produces: rutas `panel.brackets.show` (GET `categorias/{category}/cuadro`), `panel.brackets.store` (POST, genera/regenera), `panel.brackets.swap` (POST `…/cuadro/intercambio`), `panel.bouts.result` (PUT `categorias/{category}/combates/{bout}/resultado`, campo `winner_id`); partial `partials.bracket` con variables `$bouts` (con `slotA.competitor`, `slotB.competitor`, `winner`), `$category`, `$editable` (bool, sólo organizador); `partials.podium` con `$placements` (`list<Placement>`); `Competitor::displayName(): string` (`"Nombre Apellidos"`).

- [ ] **Step 1: Tests que fallan**

`tests/Feature/BracketPanelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class BracketPanelTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function owned(int $n): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->for(Event::factory()->for($user))->create();
        Registration::factory()->count($n)->confirmed()->forCategory($category)->create();

        return [$user, $category->event, $category];
    }

    public function test_owner_generates_and_regenerates_the_bracket(): void
    {
        [$user, $event, $category] = $this->owned(5);

        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]))->assertRedirect();
        $this->assertSame(8, $category->bouts()->count());

        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]))->assertRedirect();
        $this->assertSame(8, $category->bouts()->count());
        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->assertOk()->assertSee('Semifinales');
    }

    public function test_single_confirmed_participant_shows_manual_resolution_message(): void
    {
        [$user, $event, $category] = $this->owned(1);

        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]))
            ->assertSessionHasErrors('error');
        $this->assertSame(0, $category->bouts()->count());
        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))
            ->assertOk()->assertSee('manualmente');
    }

    public function test_owner_records_a_result_and_the_winner_advances(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        $semi = $category->bouts()->where('round', 1)->orderBy('position')->first();

        $this->actingAs($user)->put(route('panel.bouts.result', [$event, $category, $semi]), ['winner_id' => $semi->slot_a_id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($semi->slot_a_id, $category->bouts()->where('round', 2)->where('is_bronze', false)->first()->slot_a_id);
    }

    public function test_tampered_winner_ids_are_rejected_without_changes(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        $semi = $category->bouts()->where('round', 1)->orderBy('position')->first();
        $foreign = $this->generated(2)->registrations()->first();

        foreach ([$foreign->id, 0, 'abc', ''] as $bad) {
            $this->actingAs($user)->put(route('panel.bouts.result', [$event, $category, $semi]), ['winner_id' => $bad])
                ->assertSessionHasErrors();
        }
        $this->assertNull($semi->fresh()->winner_id);
    }

    public function test_bout_of_another_category_is_not_reachable_through_my_category(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $other = $this->generated(4);
        $foreignBout = $other->bouts()->first();

        $this->actingAs($user)->put(route('panel.bouts.result', [$event, $category, $foreignBout]), ['winner_id' => $foreignBout->slot_a_id])
            ->assertNotFound();
    }

    public function test_intruder_cannot_generate_swap_or_record(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        $bout = $category->bouts()->first();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('panel.brackets.store', [$event, $category]))->assertForbidden();
        $this->actingAs($intruder)->post(route('panel.brackets.swap', [$event, $category]), ['registration_a' => 1, 'registration_b' => 2])->assertForbidden();
        $this->actingAs($intruder)->put(route('panel.bouts.result', [$event, $category, $bout]), ['winner_id' => $bout->slot_a_id])->assertForbidden();
    }

    public function test_swap_via_http_and_refusal_after_results(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        $s1 = $category->bouts()->where('round', 1)->orderBy('position')->get();
        [$a, $b] = [$s1[0]->slot_a_id, $s1[1]->slot_a_id];

        $this->actingAs($user)->post(route('panel.brackets.swap', [$event, $category]), ['registration_a' => $a, 'registration_b' => $b])
            ->assertSessionHasNoErrors();
        $this->assertSame($b, $s1[0]->fresh()->slot_a_id);

        $this->actingAs($user)->put(route('panel.bouts.result', [$event, $category, $s1[0]]), ['winner_id' => $s1[0]->fresh()->slot_a_id]);
        $this->actingAs($user)->post(route('panel.brackets.swap', [$event, $category]), ['registration_a' => $a, 'registration_b' => $b])
            ->assertSessionHasErrors('error');
    }

    public function test_stale_bracket_warning_is_shown(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        Registration::factory()->confirmed()->forCategory($category)->create();

        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->assertSee('desactualizado');
    }
}
```

Run: `php artisan test --filter=BracketPanelTest` → Expected: FAIL.

- [ ] **Step 2: Controladores y rutas**

`BracketController`:

```php
<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\BracketAdjuster;
use App\Tournament\BracketGenerator;
use App\Tournament\PodiumCalculator;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BracketController extends Controller
{
    public function show(Event $event, Category $category, PodiumCalculator $podium): View
    {
        $bouts = $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner'])->get();

        return view('panel.brackets.show', [
            'event' => $event,
            'category' => $category,
            'bouts' => $bouts,
            'placements' => $podium->for($category),
            'confirmedCount' => $category->registrations()->where('status', 'confirmed')->count(),
            'stale' => $category->bracketIsStale(),
            'hasResults' => $category->hasResults(),
        ]);
    }

    public function store(Event $event, Category $category, BracketGenerator $generator): RedirectResponse
    {
        try {
            $generator->generate($category);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('panel.brackets.show', [$event, $category])->with('status', 'Cuadro generado.');
    }

    public function swap(Request $request, Event $event, Category $category, BracketAdjuster $adjuster): RedirectResponse
    {
        $data = $request->validate([
            'registration_a' => ['required', 'integer'],
            'registration_b' => ['required', 'integer'],
        ]);

        try {
            $adjuster->swap($category, (int) $data['registration_a'], (int) $data['registration_b']);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Participantes intercambiados.');
    }
}
```

`BoutResultController`:

```php
public function update(Request $request, Event $event, Category $category, Bout $bout, ResultRecorder $recorder): RedirectResponse
{
    $data = $request->validate(['winner_id' => ['required', 'integer', 'min:1']]);

    try {
        $recorder->record($bout, (int) $data['winner_id']);
    } catch (TournamentException $e) {
        return back()->withErrors(['error' => $e->getMessage()]);
    }

    return back()->with('status', 'Resultado registrado.');
}
```

Rutas en el grupo del evento (con `scopeBindings`, `bout` se resuelve a través de `$category->bouts()`):

```php
Route::get('categorias/{category}/cuadro', [BracketController::class, 'show'])->name('brackets.show');
Route::post('categorias/{category}/cuadro', [BracketController::class, 'store'])->name('brackets.store');
Route::post('categorias/{category}/cuadro/intercambio', [BracketController::class, 'swap'])->name('brackets.swap');
Route::put('categorias/{category}/combates/{bout}/resultado', [BoutResultController::class, 'update'])->name('bouts.result');
```

`Competitor::displayName(): string { return $this->first_name.' '.$this->last_name; }`

- [ ] **Step 3: Vistas**

`partials/bout-card.blade.php` (`$bout`, `$category`, `$event`, `$editable`):

```blade
@php
    $names = [
        'a' => $bout->slotA?->competitor->displayName(),
        'b' => $bout->slotB?->competitor->displayName(),
    ];
    $empty = fn ($slot) => $bout->isBye() ? 'Pase' : 'Por determinar';
@endphp
<div class="bout-card mb-2">
    <div class="small text-muted px-2 pt-1">
        {{ $bout->number ? 'Combate '.$bout->number : 'Pase' }}@if ($bout->is_bronze) · Bronce @endif
    </div>
    @foreach (['a' => $bout->slot_a_id, 'b' => $bout->slot_b_id] as $slot => $registrationId)
        <div class="slot d-flex justify-content-between gap-2 {{ $registrationId && $registrationId === $bout->winner_id ? 'winner' : '' }}">
            <span class="{{ $registrationId ? '' : 'text-muted fst-italic' }}">
                {{ $names[$slot] ?? $empty($slot) }}
                @if ($registrationId && ($slot === 'a' ? $bout->slotA : $bout->slotB)->competitor->club)
                    <small class="text-muted">({{ ($slot === 'a' ? $bout->slotA : $bout->slotB)->competitor->club }})</small>
                @endif
            </span>
            @if ($editable && $bout->filledSlots() === 2 && ! $bout->isBye())
                <form method="POST" action="{{ route('panel.bouts.result', [$event, $category, $bout]) }}" class="no-print">
                    @csrf @method('PUT')
                    <input type="hidden" name="winner_id" value="{{ $registrationId }}">
                    <button class="btn btn-sm {{ $registrationId === $bout->winner_id ? 'btn-success' : 'btn-outline-primary' }}">Gana</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
```

`partials/bracket.blade.php` (`$bouts`, `$category`, `$event`, `$editable = false`):

```blade
@php
    $editable ??= false;
    $totalRounds = $bouts->where('is_bronze', false)->max('round');
    $rounds = $bouts->where('is_bronze', false)->groupBy('round')->sortKeys();
    $bronze = $bouts->firstWhere('is_bronze', true);
@endphp
<div class="bracket d-flex gap-3 overflow-auto pb-3">
    @foreach ($rounds as $round => $roundBouts)
        <div class="bracket-round flex-shrink-0">
            <h3 class="h6 text-center text-muted">{{ \App\Tournament\RoundLabel::for($round, $totalRounds) }}</h3>
            @foreach ($roundBouts->sortBy('position') as $bout)
                @include('partials.bout-card', ['bout' => $bout])
            @endforeach
        </div>
    @endforeach
</div>
@if ($bronze)
    <h3 class="h6 text-muted mt-3">Combate por el bronce</h3>
    <div style="max-width: 20rem">@include('partials.bout-card', ['bout' => $bronze])</div>
@endif
```

> `@include` hereda las variables del scope (`$category`, `$event`, `$editable`), por lo que el partial público las recibe del controlador público (Task 13) con `$editable = false`.

`partials/podium.blade.php`:

```blade
@if (count($placements))
    <ul class="list-group mb-3">
        @foreach ($placements as $p)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $p->place }}.º — <strong>{{ $p->registration->competitor->displayName() }}</strong></span>
                <span class="text-muted">{{ $p->registration->competitor->club }}</span>
            </li>
        @endforeach
    </ul>
@endif
```


`panel/brackets/show.blade.php`: cabecera con enlace a evento, nombre de categoría y `confirmedCount`; botón «Generar cuadro» / «Regenerar cuadro» (form POST `brackets.store`, `onsubmit` con `confirm` si ya existe) visible solo si `! $hasResults`; aviso `alert-warning` «El cuadro está **desactualizado** respecto a las inscripciones confirmadas: regénéralo» si `$stale`; aviso «Regla del bronce: DECISIÓN PENDIENTE (configurada: …)» con `$category->bronze_rule->label()`; si `$confirmedCount === 1` mensaje «Solo hay un inscrito confirmado: la clasificación se resuelve manualmente según el reglamento.»; si hay `$bouts`: `@include('partials.bracket', ['editable' => true])`, formulario de intercambio (dos `<select>` con los participantes de primera ronda — `$bouts->where('round',1)->flatMap(...)` — visible solo si `! $hasResults`), tabla «Orden de combates» (`$bouts->whereNotNull('number')->sortBy('number')`: nº, ronda, participantes, estado «Pendiente/Listo/Finalizado»), y `@include('partials.podium')`; botón «Imprimir» (`window.print()`).

`panel/events/show.blade.php`: en la tabla de categorías añadir enlace «Cuadro» → `panel.brackets.show`.

- [ ] **Step 4: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS (el test de aislamiento recorre ahora también las rutas de cuadro/resultado).

```bash
git add -A . ':!.env'
git commit -m "feat: interfaz de cuadros, resultados y ajuste para el organizador" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 13: Publicación, páginas públicas y CSV de clasificación

**Files:**
- Create: `app/Http/Controllers/{PublicCategoryController}.php`, `app/Http/Controllers/Panel/PublicationController.php`, `resources/views/public/categories/show.blade.php`, `tests/Feature/PublicResultsTest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Panel/ExportController.php` (`standings`), `resources/views/public/events/show.blade.php`, `panel/events/show.blade.php`, `panel/brackets/show.blade.php`

**Interfaces:**
- Consumes: `Category::is_published`, `partials.bracket`, `partials.podium`, `PodiumCalculator`, `Csv`.
- Produces: rutas `public.categories.show` (`/eventos/{event:slug}/categorias/{category}`), `panel.categories.publication` (PATCH `categorias/{category}/publicacion`, alterna `is_published`; exige cuadro generado para publicar), `panel.exports.standings` (`exportar/clasificacion.csv`, columnas `Categoría;Puesto;Apellidos;Nombre;Club`).

- [ ] **Step 1: Tests que fallan** (privacidad)

`tests/Feature/PublicResultsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class PublicResultsTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function playedCategory(): Category
    {
        $category = $this->generated(4);
        $category->update(['show_fourth' => true]);
        $category->event->update(['is_public' => true, 'status' => EventStatus::InProgress]);
        $recorder = app(ResultRecorder::class);
        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $recorder->record($bout, $bout->slot_a_id);
        }

        return $category->refresh();
    }

    public function test_owner_publishes_only_a_generated_category(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $empty = Category::factory()->for($event)->create();

        $this->actingAs($user)->patch(route('panel.categories.publication', [$event, $empty]))->assertSessionHasErrors('error');
        $this->assertFalse($empty->fresh()->is_published);

        $category = $this->generated(4);
        $category->event->update(['user_id' => $user->id]);
        $this->actingAs($user)->patch(route('panel.categories.publication', [$category->event, $category]));
        $this->assertTrue($category->fresh()->is_published);
        $this->actingAs($user)->patch(route('panel.categories.publication', [$category->event, $category]));
        $this->assertFalse($category->fresh()->is_published);
    }

    public function test_public_pages_show_bracket_and_podium_but_never_private_data(): void
    {
        $category = $this->playedCategory();
        $category->update(['is_published' => true]);
        $event = $category->event;

        // Datos privados con valores reconocibles
        Competitor::query()->where('event_id', $event->id)->get()->each(fn ($c, $i) => $c->update([
            'email' => "privado{$i}@secreto.test", 'phone' => "699000{$i}00", 'notes' => "nota-interna-{$i}", 'birth_date' => '1999-0'.($i + 1).'-17',
        ]));

        $pages = [route('home'), route('public.events.show', $event), route('public.categories.show', [$event, $category])];
        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach (['@secreto.test', '699000', 'nota-interna', '1999-0', '17/0', 'birth'] as $secret) {
                $this->assertStringNotContainsString($secret, $html, "{$url} expone {$secret}");
            }
        }

        $page = $this->get(route('public.categories.show', [$event, $category]));
        $winner = $category->bouts()->where('round', 2)->where('is_bronze', false)->first()->winner->competitor;
        $page->assertSee($winner->displayName())->assertSee('1.º');
    }

    public function test_unpublished_private_or_draft_content_is_not_public(): void
    {
        $category = $this->playedCategory();
        $event = $category->event;

        $this->get(route('public.categories.show', [$event, $category]))->assertNotFound(); // sin publicar

        $category->update(['is_published' => true]);
        $this->get(route('public.categories.show', [$event, $category]))->assertOk();

        $event->update(['is_public' => false]);
        $this->get(route('public.categories.show', [$event, $category]))->assertNotFound();

        $event->update(['is_public' => true, 'status' => EventStatus::Draft]);
        $this->get(route('public.categories.show', [$event, $category]))->assertNotFound();
    }

    public function test_category_from_another_event_is_not_reachable(): void
    {
        $category = $this->playedCategory();
        $category->update(['is_published' => true]);
        $other = Event::factory()->registrationOpen()->create();

        $this->get(route('public.categories.show', [$other, $category]))->assertNotFound();
    }

    public function test_event_page_lists_only_published_categories(): void
    {
        $category = $this->playedCategory();
        $hidden = Category::factory()->for($category->event)->create(['name' => 'Categoría oculta']);
        $category->update(['is_published' => true]);

        $this->get(route('public.events.show', $category->event))
            ->assertSee($category->name)->assertDontSee('Categoría oculta');
    }

    public function test_standings_csv_for_owner(): void
    {
        $category = $this->playedCategory();
        $owner = $category->event->user;

        $csv = $this->actingAs($owner)->get(route('panel.exports.standings', $category->event))->assertOk()->streamedContent();

        $this->assertStringContainsString('Categoría;Puesto;Apellidos;Nombre;Club', $csv);
        $this->assertStringContainsString($category->name.';1;', $csv);
        $this->assertStringContainsString($category->name.';4;', $csv); // show_fourth activado
        $this->actingAs(User::factory()->create())->get(route('panel.exports.standings', $category->event))->assertForbidden();
    }
}
```

Run: `php artisan test --filter=PublicResultsTest` → Expected: FAIL.

- [ ] **Step 2: Controladores y rutas**

`PublicationController::toggle`:

```php
public function toggle(Event $event, Category $category): RedirectResponse
{
    if (! $category->is_published && ! $category->bouts()->exists()) {
        return back()->withErrors(['error' => 'Genera el cuadro antes de publicar la categoría.']);
    }

    // is_published no está en #[Fillable] a propósito: solo cambia por esta acción.
    $category->forceFill(['is_published' => ! $category->is_published])->save();

    return back()->with('status', $category->is_published ? 'Categoría publicada.' : 'Categoría oculta.');
}
```


`PublicCategoryController::show`:

```php
public function show(Event $event, Category $category, PodiumCalculator $podium): View
{
    abort_unless($event->isListed() && $category->is_published, 404);

    return view('public.categories.show', [
        'event' => $event,
        'category' => $category,
        'bouts' => $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner'])->get(),
        'placements' => $podium->for($category),
        'editable' => false,
    ]);
}
```

`ExportController::standings`:

```php
public function standings(Event $event, PodiumCalculator $podium): StreamedResponse
{
    $rows = [];
    foreach ($event->categories()->orderBy('name')->get() as $category) {
        foreach ($podium->for($category) as $p) {
            $c = $p->registration->competitor;
            $rows[] = [$category->name, $p->place, $c->last_name, $c->first_name, $c->club];
        }
    }

    return Csv::download('clasificacion-'.$event->slug.'.csv', ['Categoría', 'Puesto', 'Apellidos', 'Nombre', 'Club'], $rows);
}
```

Rutas:

```php
// pública (fuera del panel)
Route::get('eventos/{event:slug}/categorias/{category}', [PublicCategoryController::class, 'show'])
    ->scopeBindings()->name('public.categories.show');

// dentro del grupo del evento
Route::patch('categorias/{category}/publicacion', [PublicationController::class, 'toggle'])->name('categories.publication');
Route::get('exportar/clasificacion.csv', [ExportController::class, 'standings'])->name('exports.standings');
```

- [ ] **Step 3: Vistas**

`public/categories/show.blade.php`: `@extends('layouts.app')`, migas (enlace al evento), `<h1>{{ $category->name }}</h1>`, botón `no-print` «Imprimir», `@include('partials.podium')` bajo `<h2>Podio</h2>` si hay `$placements`, `@include('partials.bracket')` bajo «Cuadro», y tabla «Resultados» (`$bouts->whereNotNull('number')->sortBy('number')`: nº, ronda, participantes con club, ganador). Solo nombres y club (`displayName()` y `club`): **nunca** `birth_date`, `email`, `phone`, `notes`.

`public/events/show.blade.php`: añadir lista de `$event->categories()->where('is_published', true)->orderBy('name')->get()` con enlaces a `public.categories.show` (pasar la variable desde el controlador: `'categories' => ...`).

`panel/events/show.blade.php` y `panel/brackets/show.blade.php`: botón «Publicar»/«Ocultar» (form `PATCH` a `categories.publication`), enlace «Ver página pública» cuando esté publicada, y enlace «Exportar clasificación (CSV)» → `panel.exports.standings`.

- [ ] **Step 4: Verificar y commit**

Run: `vendor/bin/pint --dirty && php artisan test`
Expected: todos PASS.

```bash
git add -A . ':!.env'
git commit -m "feat: publicación de cuadros y resultados, páginas públicas y CSV de clasificación" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 14: Primer objetivo funcional de extremo a extremo y documentación

**Files:**
- Create: `tests/Feature/FirstMilestoneTest.php`, `docs/decisiones-pendientes.md`, `docs/menores.md`
- Modify: `/README.md` (raíz del repo: estado y DocumentRoot), `web/README.md` (sustituir el texto genérico de Laravel por puntos de arranque del proyecto)

**Interfaces:**
- Consumes: todo lo anterior vía HTTP.

- [ ] **Step 1: Test del hito**

`tests/Feature/FirstMilestoneTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Organizador: evento + categoría, 5 competidores confirmados, cuadro con pases, final y bronce, publicación sin datos privados. */
class FirstMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_organizer_flow(): void
    {
        $organizer = User::factory()->create();
        $this->actingAs($organizer);

        $this->post(route('panel.events.store'), [
            'name' => 'Open de Otoño', 'discipline' => 'Judo', 'starts_on' => '2026-11-20', 'venue' => 'Pabellón',
            'status' => 'in_progress', 'is_public' => '1', 'registration_form_enabled' => '0',
        ])->assertRedirect();
        $event = Event::firstOrFail();

        $this->post(route('panel.categories.store', $event), [
            'name' => 'Adultos -70 kg', 'bronze_rule' => 'bronze_match', 'show_fourth' => '1',
        ])->assertRedirect();
        $category = Category::firstOrFail();

        $names = ['Ana' => 'Ruiz', 'Berta' => 'Sanz', 'Carla' => 'Vela', 'Dana' => 'Mora', 'Eva' => 'Nieto'];
        $i = 0;
        foreach ($names as $first => $last) {
            $this->post(route('panel.registrations.store', $event), [
                'first_name' => $first, 'last_name' => $last, 'club' => 'Club '.$first, 'birth_date' => '199'.$i.'-05-0'.($i + 1),
                'sex' => 'female', 'weight' => 60, 'email' => strtolower($first).'@privado.test', 'phone' => '60000000'.$i,
                'category_id' => $category->id, 'status' => 'confirmed',
            ])->assertSessionHasNoErrors();
            $i++;
        }

        $this->post(route('panel.brackets.store', [$event, $category]))->assertSessionHasNoErrors();
        $this->assertSame(3, $category->bouts()->where('required_participants', 1)->where('is_bronze', false)->count(), 'cinco competidores → tres pases');

        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $this->put(route('panel.bouts.result', [$event, $category, $bout]), ['winner_id' => $bout->slot_a_id])
                ->assertSessionHasNoErrors();
        }

        $this->patch(route('panel.categories.publication', [$event, $category]));
        auth()->logout();

        $html = $this->get(route('public.categories.show', [$event, $category]))->assertOk()->assertSee('1.º')->assertSee('4.º')->getContent();
        foreach (['@privado.test', '60000000', '199', 'Fecha de nacimiento'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }
}
```

Run: `php artisan test --filter=FirstMilestoneTest` → Expected: PASS (todo está implementado). Si falla, es un bug real de las tareas anteriores: corregirlo allí (con su test) antes de seguir.

- [ ] **Step 2: Documentación de decisiones**

`docs/decisiones-pendientes.md`: tabla con **Decisión / Valor provisional / Dónde está en el código / Quién decide**:
1. Regla del bronce (`BronzeRule::BronzeMatch` propuesta; `SharedBronze` alternativa) — `app/Enums/BronzeRule.php`, UI de categoría y de cuadro marcan «DECISIÓN PENDIENTE».
2. Retiradas y descalificaciones: **no implementadas**; las bajas con resultados se resuelven manualmente (`RegistrationService::changeStatus` las bloquea).
3. Una persona en varias categorías del mismo evento: permitido; identidad = nombre+apellidos normalizados+fecha de nacimiento por evento (`Competitor::normalizeName`).
4. Un único inscrito: sin cuadro ni campeón automático (`BracketGenerator`).
5. Edad mínima del formulario público (18) — `config/kumitecontrol.php`.
6. Correcciones tras finalizar el evento: requieren reabrirlo (`ResultRecorder`).

`docs/menores.md`: antes de habilitar inscripciones de menores hay que definir (a) consentimiento del representante legal y su forma de recogida, (b) datos del representante necesarios y su conservación, (c) base legal y aviso de privacidad aplicable (RGPD/LOPDGDD, edad de consentimiento digital en España: 14 años — **verificar con asesoría legal**), (d) que esos datos nunca se publican. Mientras tanto el formulario público solo admite `PUBLIC_FORM_MIN_AGE` (18) o más en la fecha del evento; el alta manual por el organizador sí admite menores bajo su responsabilidad.

- [ ] **Step 3: README**

`/README.md` (raíz): cambiar «Estado» a «Primera versión implementada en `web/`», y en «Preparación local» sustituir el bloque de instalación por: código en `web/`, `source scripts/dev-env.sh`, `composer install`, `cp .env.example .env`, `php artisan key:generate`, `npm install`, `php artisan migrate`, `npm run dev`; **DocumentRoot de MAMP PRO = `kumitecontrol/web/public`**. Enlazar `docs/decisiones-pendientes.md` y `docs/menores.md`. `web/README.md`: reducirlo a 10 líneas con el mismo arranque.

- [ ] **Step 4: Verificación final**

Run:
```bash
vendor/bin/pint --test && php artisan test && npm run build && php artisan route:list --path=panel | head -50
```
Expected: Pint sin cambios pendientes, **todos los tests PASS**, build de Vite correcto. Revisar visualmente (si el usuario ha configurado `kumitecontrol.test`): registro → evento → categoría → 5 inscripciones → cuadro → resultados → publicar → página pública en móvil y escritorio, y la vista de impresión.

- [ ] **Step 5: Commit**

```bash
git add -A . ':!.env' ../docs ../README.md
git commit -m "test: hito funcional completo; docs de decisiones pendientes y menores" -m "Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Self-review

**Cobertura del spec.** Cuentas/recuperación (T3) · eventos, estados, visibilidad, página pública (T4, T13) · categorías con criterios y edad en fecha del evento (T5) · competidores, inscripciones, estados, duplicados, formulario público (T6, T7) · listas, filtros, impresión, CSV inscritos y clasificación (T8, T12, T13) · eliminación directa, byes, ajuste previo, avance, regeneración, servicios persistentes (T9, T10) · podio 3/4 y bronce configurable marcado pendiente (T5, T11, T12, T14) · seguridad (policy + test global T4, CSRF por defecto, validación servidor, privacidad T13, menores T7/T14, `.env.example` T1) · reglas de torneo (1 competidor sin campeón T9; 2 → final T9; ambos participantes T10; retiradas pendientes T14) · fuera de alcance respetado · entorno PHP 8.4 (T1) · hito (T14).

**Sin marcadores sin resolver.** Revisado: sin TBD ni tests vacíos; los pasos con vistas descritas (T3 vistas secundarias, T5–T8, T12–T13) listan los campos y componentes exactos a usar.

**Consistencia de tipos.** `Bout::{filledSlots,isBye,isReady,isCompleted,isFinal,loserId}`, `BracketAdvancer::{settle,complete,reset}`, `BracketGenerator::generate(Category, ?Closure)`, `ResultRecorder::record(Bout,int)`, `BracketAdjuster::swap(Category,int,int)`, `PodiumCalculator::for(Category): list<Placement>`, `RegistrationService::{register,changeStatus}`, `Competitor::{normalizeName,ageOn,displayName}`, nombres de ruta y parámetros usados de forma coherente en tests y vistas.

**Decisiones a validar por el usuario** (también listadas al presentar el plan): DocumentRoot real `web/public`; Laravel Boost no instalado pese a `web/CLAUDE.md`; edad mínima 18 del formulario público; competidor ligado al evento; nombre `Bout` en lugar de `matches`; cuadro sin JS.
