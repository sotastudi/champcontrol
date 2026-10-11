# Documentación del sorteo/nivel y pulido de pendientes menores — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Documentar el select de nivel y el sorteo configurable, y cerrar cinco pendientes menores de revisiones anteriores.

**Architecture:** Cambios pequeños y aislados sobre código ya existente en `web/` (Laravel 13 / PHP 8.4). Cada tarea es independiente, con TDD y commit propio.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit (SQLite en memoria), Pint.

**Spec:** Peticiones del usuario en la sesión (select de nivel kyu/dan; sorteo nivel > peso > club configurable por evento y categoría; pendientes menores listados en el resumen de sesión). Sin documento de diseño aparte.

## Global Constraints

- Entorno: `cd web && source scripts/dev-env.sh` y usar `\php` (PHP 8.4 de MAMP, Node 26). Nunca el PHP por defecto del terminal.
- Antes de cada commit: `vendor/bin/pint --dirty` y `\php artisan test` (hoy 188 tests pasan).
- Staging siempre con rutas explícitas (nunca `git add -A`); no stagear `web/.env` ni `assets/`.
- No tocar la base de datos MySQL del usuario; los tests usan SQLite en memoria. Ninguna tarea añade migraciones.
- Commits terminan con `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. Rama `develop`; sin merge ni push.
- Interfaz y mensajes en español.

## Review Focus

- Reset de contraseña: un email inexistente y un token inválido deben dar el mismo mensaje en `password.update` (Task 3).
- Teléfonos `+34 600 000 000` en CSV no deben quedar prefijados, pero `=cmd|...`, `+cmd|...` y `@SUM(1)` siguen neutralizados (Task 4).
- Categoría bloqueada por bajas sin resultados y regla 4 en «cancelar y regenerar»: la baja debe seguir permitida (Task 2).
- Un error de validación en «Gana» o en el ajuste del cuadro debe verse, no ignorarse (Task 5).
- Documentación: el sorteo no debe describirse como puramente aleatorio (Task 1).

---

### Task 1: Documentar nivel y sorteo configurable

**Files:**
- Modify: `docs/decisiones-pendientes.md` (añadir 2 filas a la tabla, tras la fila «Reglas del campeonato configurables por evento»)
- Modify: `README.md:35` y la sección de categorías (`README.md:17`)

**Interfaces:**
- Consumes: `App\Enums\DrawCriteria` (casos `level_weight_club` por defecto, `weight_level_club`, `level_club`, `weight_club`, `club_only`, `random`), `App\Tournament\DrawOrderer`, `App\Support\MartialLevels`, `Category::effectiveDrawCriteria()`.
- Produces: solo documentación.

- [ ] **Step 1: Añadir las filas a `docs/decisiones-pendientes.md`**

Insertar tras la fila de «Reglas del campeonato configurables por evento»:

```markdown
| Nivel del competidor | **Select cerrado**: 10º kyu → 1º kyu y 1º dan → 10º dan (se guarda el texto). Los valores libres anteriores no se reconocen y hay que reasignarlos. Un inscrito sin nivel queda al final del criterio de nivel. Si otras artes marciales usan otra escala, hay que ampliar la lista. | `app/Support/MartialLevels.php`, `app/Http/Requests/*Request.php` | Organización / reglamento |
| Criterios del sorteo | **CONFIGURABLE por evento** (por defecto `Nivel > peso > club`) y **por categoría** (vacío = hereda del evento). Opciones: nivel > peso > club, peso > nivel > club, nivel > club, peso > club, solo club (azar evitando el mismo club), azar puro. Nivel: mayor primero; peso: menor primero; empates al azar. Los de mayor nivel reciben los pases; el resto se empareja con su vecino de la lista; si coinciden en club se intercambia un rival cuando es posible (no siempre lo es). Son combinaciones fijas, no un orden libre. | `app/Enums/DrawCriteria.php`, `app/Tournament/DrawOrderer.php`, `app/Tournament/BracketGenerator.php` | Organización / reglamento |
```

- [ ] **Step 2: Actualizar el README**

En `README.md:35`, sustituir «El sorteo solo se modifica…» por: «El sorteo se genera con los criterios configurados (por defecto nivel > peso > club, con azar entre iguales) y solo se modifica antes de anotar el primer resultado. …(resto de la frase igual)». En `README.md:17` añadir al final de la viñeta de categorías: «El nivel es un desplegable (10º kyu a 10º dan) y el criterio de sorteo se elige por evento y, si hace falta, por categoría.»

- [ ] **Step 3: Verificar**

Run: `grep -n "Criterios del sorteo\|Nivel del competidor" docs/decisiones-pendientes.md && grep -n "nivel > peso > club" README.md`
Expected: tres líneas encontradas.

- [ ] **Step 4: Commit**

```bash
git add docs/decisiones-pendientes.md README.md
git commit -m "docs: nivel como select y criterios de sorteo configurables

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

### Task 2: `WithdrawalRecorder` usa `isRegenerationLocked()`

**Files:**
- Modify: `web/app/Tournament/WithdrawalRecorder.php:31-39`
- Modify: `web/app/Http/Controllers/Panel/BracketController.php:24,35` (eliminar `$hasResults` si ninguna vista lo usa)
- Test: `web/tests/Feature/Tournament/WithdrawalRecorderTest.php`

**Interfaces:**
- Consumes: `Category::isRegenerationLocked(): bool` (= `hasResults()` o existe baja), `Event::preResultsWithdrawalRule()`, `PreResultsWithdrawalRule::{CancelAndRegenerate,AllowWalkover}`, `Category::bracketIsStale()`, `TournamentException::STALE_BRACKET`.
- Produces: sin cambios de firma.

- [ ] **Step 1: Escribir el test que falla**

Añadir a `WithdrawalRecorderTest` (usar los `use` e idioma ya presentes en el fichero; añadir `PreResultsWithdrawalRule` y `WithdrawalType` si faltan):

```php
public function test_withdrawal_is_allowed_when_category_is_locked_by_withdrawals_even_without_results(): void
{
    // n=3: quien tiene el pase no genera walkover al retirarse (su rival de la final aún no existe).
    $category = $this->generated(3);
    $category->event->forceFill(['withdrawal_before_results_rule' => PreResultsWithdrawalRule::AllowWalkover])->save();

    $bye = $category->bouts()->where('round', 1)->where('required_participants', 1)->firstOrFail();
    $byeRegistration = Registration::findOrFail($bye->slot_a_id ?? $bye->slot_b_id);
    app(WithdrawalRecorder::class)->record($byeRegistration, WithdrawalType::Withdrawn);

    $category->refresh();
    $this->assertFalse($category->hasResults());
    $this->assertTrue($category->isRegenerationLocked());

    // Se vuelve a la regla por defecto y se retira a otro: el cuadro ya no se puede regenerar, así que no debe pedirlo.
    $category->event->forceFill(['withdrawal_before_results_rule' => PreResultsWithdrawalRule::CancelAndRegenerate])->save();
    $other = Registration::where('category_id', $category->id)->whereKeyNot($byeRegistration->id)->firstOrFail();

    app(WithdrawalRecorder::class)->record($other->fresh(), WithdrawalType::Withdrawn);

    $this->assertTrue($other->fresh()->isWithdrawn());
}
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `\php artisan test --filter=test_withdrawal_is_allowed_when_category_is_locked`
Expected: FAIL con `TournamentException` «Aún no hay resultados: cancela la inscripción y regenera el cuadro.»

- [ ] **Step 3: Implementar**

En `WithdrawalRecorder::record`, sustituir el bloque `if (! $category->hasResults()) { … }` por:

```php
        if (! $category->isRegenerationLocked()) {
            if ($category->event->preResultsWithdrawalRule() === PreResultsWithdrawalRule::CancelAndRegenerate) {
                throw new TournamentException('Aún no hay resultados: cancela la inscripción y regenera el cuadro.');
            }
            if ($category->bracketIsStale()) {
                throw new TournamentException(TournamentException::STALE_BRACKET);
            }
        }
```

En `BracketController`, comprobar con `grep -rn "hasResults" resources/views app/Http` si `$hasResults` se usa; si no, quitar la variable y la clave `'hasResults'` del array de la vista.

- [ ] **Step 4: Ejecutar toda la suite**

Run: `vendor/bin/pint --dirty && \php artisan test`
Expected: todo pasa (≥189 tests).

- [ ] **Step 5: Commit**

```bash
git add web/app/Tournament/WithdrawalRecorder.php web/app/Http/Controllers/Panel/BracketController.php web/tests/Feature/Tournament/WithdrawalRecorderTest.php
git commit -m "fix: el registro de bajas usa isRegenerationLocked en la guarda externa

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

### Task 3: Reset de contraseña sin enumeración y con throttle

**Files:**
- Modify: `web/app/Http/Controllers/Auth/NewPasswordController.php` (`store`)
- Modify: `web/routes/web.php:38` (middleware de `password.update`)
- Test: el test de reset existente (`grep -rln "password.update" web/tests`)

**Interfaces:**
- Consumes: `Password::reset(...)` devuelve `Password::PASSWORD_RESET`, `Password::INVALID_USER` o `Password::INVALID_TOKEN`; `lang/es/passwords.php` tiene `token` e `user`.
- Produces: `password.update` responde igual ante email inexistente y token inválido, y limita a 6 intentos por minuto.

- [ ] **Step 1: Escribir los tests que fallan**

```php
public function test_reset_does_not_reveal_whether_the_email_exists(): void
{
    User::factory()->create(['email' => 'real@example.com']);
    $payload = ['token' => 'token-falso', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'];

    $unknown = $this->post(route('password.update'), $payload + ['email' => 'nadie@example.com']);
    $known = $this->post(route('password.update'), $payload + ['email' => 'real@example.com']);

    $this->assertSame(
        session('errors')?->getBag('default')->first('email'),
        $known->getSession()->get('errors')->getBag('default')->first('email'),
    );
    $this->assertSame(
        $unknown->getSession()->get('errors')->first('email'),
        $known->getSession()->get('errors')->first('email'),
    );
}

public function test_password_update_is_throttled(): void
{
    $payload = ['token' => 't', 'email' => 'x@example.com', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123'];
    for ($i = 0; $i < 6; $i++) {
        $this->post(route('password.update'), $payload);
    }
    $this->post(route('password.update'), $payload)->assertStatus(429);
}
```

(Eliminar la primera aserción `session('errors')` del primer test; se deja solo la comparación unknown vs known.)

- [ ] **Step 2: Ejecutar y ver que fallan**

Run: `\php artisan test --filter="reveal_whether_the_email|password_update_is_throttled"`
Expected: FAIL (mensajes distintos «No encontramos…» vs «token inválido»; sin 429).

- [ ] **Step 3: Implementar**

En `NewPasswordController::store`, tratar ambos fallos igual:

```php
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        // Email inexistente y token inválido dan el mismo mensaje: no se revela qué correos están registrados.
        return back()->withInput($request->only('email'))->withErrors(['email' => __(Password::INVALID_TOKEN)]);
```

(Eliminar el ternario anterior.) En `web/routes/web.php:38` añadir `->middleware('throttle:6,1')` a la ruta `password.update`.

- [ ] **Step 4: Ejecutar toda la suite**

Run: `vendor/bin/pint --dirty && \php artisan test`
Expected: todo pasa. Si el test de reset existente esperaba el mensaje de usuario inexistente, actualizarlo al nuevo comportamiento.

- [ ] **Step 5: Commit**

```bash
git add web/app/Http/Controllers/Auth/NewPasswordController.php web/routes/web.php web/tests
git commit -m "fix: password.update no revela si el email existe y tiene throttle

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

### Task 4: `Csv::cell` no prefija teléfonos

**Files:**
- Modify: `web/app/Support/Csv.php` (`cell`)
- Test: `web/tests/Unit/CsvTest.php`

**Interfaces:**
- Consumes: `Csv::cell(mixed): string`.
- Produces: una celda que solo contiene dígitos, espacios, puntos, guiones y paréntesis, con un `+` o `-` inicial opcional, se emite sin prefijo; cualquier otra celda que empiece por `= + - @ \t \r` sigue prefijada con `'`.

- [ ] **Step 1: Escribir el test que falla**

Añadir casos al data provider de `CsvTest` (o un test nuevo con el mismo estilo):

```php
public function test_phone_numbers_are_not_prefixed_but_formulas_still_are(): void
{
    $this->assertSame('+34 600 000 000', Csv::cell('+34 600 000 000'));
    $this->assertSame('+34600000000', Csv::cell('+34600000000'));
    $this->assertSame('-5', Csv::cell('-5'));
    $this->assertSame("'=1+1", Csv::cell('=1+1'));
    $this->assertSame("'+cmd|' /C calc'!A0", Csv::cell("+cmd|' /C calc'!A0"));
    $this->assertSame("'@SUM(1)", Csv::cell('@SUM(1)'));
    $this->assertSame("'+34+1", Csv::cell('+34+1'));
    $this->assertSame("'-1+2", Csv::cell('-1+2'));
}
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `\php artisan test --filter=CsvTest`
Expected: FAIL en `'+34 600 000 000'` (devuelve `'+34 600…`).

- [ ] **Step 3: Implementar**

```php
    /** Evita la inyección de fórmulas: una celda que empieza por = + - @ (o tab/CR) se prefija con ', salvo un número de teléfono (solo dígitos, espacios, puntos, guiones y paréntesis, con +/- inicial). */
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if (preg_match('/^[+\-]?\d[\d\s().\-]*$/', $text) === 1) {
            return $text;
        }

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
```

Nota: `+34+1` y `-1+2` contienen `+` interior, no encajan en el patrón y siguen prefijados.

- [ ] **Step 4: Ejecutar toda la suite**

Run: `vendor/bin/pint --dirty && \php artisan test`
Expected: todo pasa.

- [ ] **Step 5: Commit**

```bash
git add web/app/Support/Csv.php web/tests/Unit/CsvTest.php
git commit -m "fix: los teléfonos con prefijo + no se prefijan en el CSV

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

### Task 5: Mostrar errores de validación de «Gana» y del ajuste del cuadro

**Files:**
- Modify: `web/resources/views/layouts/app.blade.php:89-91`
- Test: `web/tests/Feature/Tournament/BracketPanelTest.php` (o el fichero que cubra `panel.bouts.result`; localizarlo con `grep -rn "winner_id" web/tests`)

**Interfaces:**
- Consumes: los controladores de resultado (`winner_id`), baja (`registration_id`, `type`, `reason`) y ajuste del cuadro (`registration_a_id` / `registration_b_id` u otros nombres; localizar con `grep -n "validate\|rules" web/app/Http/Controllers/Panel/BoutController.php web/app/Http/Controllers/Panel/BracketAdjustController.php web/app/Http/Requests`).
- Produces: el layout muestra un `alert-danger` por cada error de validación cuyo campo no tenga un `@error` propio en la página.

- [ ] **Step 1: Escribir el test que falla**

```php
public function test_validation_error_on_winner_is_visible_after_redirect(): void
{
    $category = $this->generated(4);
    $bout = $this->bout($category, 1, 1);
    $user = $category->event->user;

    $this->actingAs($user)->from(route('panel.brackets.show', [$category->event, $category]))
        ->post(route('panel.bouts.result', [$category->event, $category, $bout]), ['winner_id' => 'abc'])
        ->assertSessionHasErrors('winner_id');

    $this->actingAs($user)->get(route('panel.brackets.show', [$category->event, $category]))
        ->assertSee('alert-danger', false);
}
```

(Ajustar los nombres de ruta a los reales: `php artisan route:list --name=bout` y `--name=bracket`.)

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `\php artisan test --filter=validation_error_on_winner`
Expected: FAIL (no hay `alert-danger` en el HTML).

- [ ] **Step 3: Implementar**

En `layouts/app.blade.php`, tras el bloque `@error('error')`, añadir:

```blade
    @foreach (['winner_id', 'registration_id', 'registration_a_id', 'registration_b_id', 'type', 'reason'] as $field)
        @error($field)
            @unless (View::hasSection('inline-errors'))
                <div class="alert alert-danger">{{ $message }}</div>
            @endunless
        @enderror
    @endforeach
```

Antes de fijar la lista de campos, comprobar con grep los nombres reales de los campos validados en los controladores de resultado, baja y ajuste, y usar exactamente esos. Para evitar duplicar errores que una vista ya muestra inline (por ejemplo `type`/`reason` en el formulario de bajas si tuviese `@error`), `grep -rn "@error('type')\|@error('reason')\|@error('registration_id')" web/resources/views` y quitar de la lista los campos que ya se pinten inline; eliminar el `@unless` y la sección `inline-errors` (no existen) si no hacen falta.

- [ ] **Step 4: Ejecutar toda la suite**

Run: `vendor/bin/pint --dirty && \php artisan test && npm run build`
Expected: todo pasa y el build termina sin errores.

- [ ] **Step 5: Commit**

```bash
git add web/resources/views/layouts/app.blade.php web/tests
git commit -m "fix: se muestran los errores de validación de resultado y ajuste del cuadro

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```
