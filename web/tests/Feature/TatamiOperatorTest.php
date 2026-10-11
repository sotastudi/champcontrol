<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TatamiOperatorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Event, Category, Category} */
    private function setUpEvent(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['tatami_count' => 2, 'is_public' => true, 'status' => EventStatus::InProgress]);
        $cat1 = Category::factory()->for($event)->create(['name' => 'Cat T1', 'tatami' => 1, 'is_published' => true]);
        $cat2 = Category::factory()->for($event)->create(['name' => 'Cat T2', 'tatami' => 2, 'is_published' => true]);
        foreach ([$cat1, $cat2] as $c) {
            Registration::factory()->count(4)->confirmed()->forCategory($c)->create();
            app(BracketGenerator::class)->generate($c, fn ($ids) => $ids->values());
        }

        return [$owner, $event, $cat1, $cat2];
    }

    public function test_owner_creates_a_limited_account_with_username_and_password_without_email_for_several_tatamis(): void
    {
        [$owner, $event] = $this->setUpEvent();

        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'Juez.Uno', 'password' => 'clave-123', 'name' => 'Juez Uno', 'tatamis' => [1, 2]])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $user = User::where('username', 'juez.uno')->firstOrFail();
        $this->assertTrue($user->isTatamiAccount());
        $this->assertNull($user->email);
        $this->assertSame($owner->id, $user->created_by_user_id);
        $this->assertTrue(\Hash::check('clave-123', $user->password));
        $this->assertSame([1, 2], $event->operators()->where('user_id', $user->id)->orderBy('tatami')->pluck('tatami')->all());

        // Entra con su usuario (sin correo) y ve «Mis tatamis» con los dos.
        auth()->logout();
        $this->post('/acceso', ['email' => 'JUEZ.uno', 'password' => 'clave-123'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->get(route('panel.events.index'))->assertOk()->assertSee('Mis tatamis')->assertSee('Tatami 1')->assertSee('Tatami 2');
        auth()->logout();
        $this->post('/acceso', ['email' => 'juez.uno', 'password' => 'mala'])->assertSessionHasErrors('email');

        // El panel del organizador lo muestra agrupado con sus dos tatamis.
        $html = $this->actingAs($owner)->get(route('panel.events.show', $event))->getContent();
        $this->assertStringContainsString('@juez.uno', $html);
        $this->assertMatchesRegularExpression('#Juez Uno.*?Tatami 1.*?Tatami 2#s', $html);
    }

    public function test_validation_of_new_controller_accounts_and_existing_accounts_are_only_given_access(): void
    {
        [$owner, $event] = $this->setUpEvent();
        $post = fn (array $data) => $this->actingAs($owner)->post(route('panel.operators.store', $event), $data);

        $post(['username' => 'ab', 'password' => 'clave-123', 'tatamis' => [1]])->assertSessionHasErrors('username');
        $post(['username' => 'con espacios', 'password' => 'clave-123', 'tatamis' => [1]])->assertSessionHasErrors('username');
        $post(['username' => 'nuevo', 'tatamis' => [1]])->assertSessionHasErrors('password');
        $post(['username' => 'nuevo', 'password' => '123', 'tatamis' => [1]])->assertSessionHasErrors('password');
        $post(['username' => 'nuevo', 'password' => 'clave-123'])->assertSessionHasErrors('tatamis');
        $post(['username' => 'nuevo', 'password' => 'clave-123', 'tatamis' => [3]])->assertSessionHasErrors('tatamis.0');
        $post(['username' => 'nuevo', 'password' => 'clave-123', 'email' => 'no-es-correo', 'tatamis' => [1]])->assertSessionHasErrors('email');
        $this->assertNull(User::where('username', 'nuevo')->first());

        // Con correo opcional.
        $post(['username' => 'conmail', 'password' => 'clave-123', 'email' => 'juez@example.com', 'tatamis' => [1]])->assertSessionHasNoErrors();
        $this->assertSame('juez@example.com', User::where('username', 'conmail')->firstOrFail()->email);

        // Una cuenta existente (por usuario) solo recibe acceso: su contraseña no cambia aunque se envíe otra.
        $post(['username' => 'conmail', 'password' => 'otra-clave', 'tatamis' => [2]])->assertSessionHasNoErrors();
        $existing = User::where('username', 'conmail')->firstOrFail();
        $this->assertTrue(\Hash::check('clave-123', $existing->password));
        $this->assertSame([1, 2], $event->operators()->where('user_id', $existing->id)->orderBy('tatami')->pluck('tatami')->all());

        // También se puede dar acceso a una cuenta de organizador por su correo.
        $organizer = User::factory()->create(['email' => 'org@example.com']);
        $post(['username' => 'ignorado', 'email' => 'org@example.com', 'tatamis' => [1]])->assertSessionHasNoErrors();
        $this->assertSame(1, $event->operators()->where('user_id', $organizer->id)->count());
        $this->assertNull(User::where('username', 'ignorado')->first());
    }

    public function test_the_creator_edits_a_controller_account_and_its_tatami_assignment(): void
    {
        [$owner, $event] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'juez', 'password' => 'clave-123', 'name' => 'Juez', 'tatamis' => [1]]);
        $user = User::where('username', 'juez')->firstOrFail();
        $operator = $event->operators()->where('user_id', $user->id)->firstOrFail();
        // El «operador» de referencia puede desaparecer al quitar su tatami: se resuelve cada vez.
        $put = fn (array $data) => $this->actingAs($owner)->put(route('panel.operators.update', [$event, $event->operators()->where('user_id', $user->id)->firstOrFail()]), $data);
        $tatamis = fn () => $event->operators()->where('user_id', $user->id)->orderBy('tatami')->pluck('tatami')->all();

        // Cambia datos, añade un tatami y fija una contraseña nueva.
        $put(['username' => 'juez.nuevo', 'name' => 'Juez Nuevo', 'email' => 'j@example.com', 'password' => 'nueva-clave', 'tatamis' => [1, 2]])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame(['juez.nuevo', 'Juez Nuevo', 'j@example.com'], [$user->username, $user->name, $user->email]);
        $this->assertTrue(\Hash::check('nueva-clave', $user->password));
        $this->assertSame([1, 2], $tatamis());

        // Sin contraseña no se cambia; quitar un tatami lo retira; correo vacío se borra.
        $put(['username' => 'juez.nuevo', 'name' => 'Juez Nuevo', 'email' => '', 'password' => '', 'tatamis' => [2]])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('nueva-clave', $user->fresh()->password));
        $this->assertNull($user->fresh()->email);
        $this->assertSame([2], $tatamis());

        // Validaciones: al menos un tatami, tatami existente, usuario libre, contraseña mínima.
        $put(['username' => 'juez.nuevo'])->assertSessionHasErrors('tatamis');
        $put(['username' => 'juez.nuevo', 'tatamis' => [9]])->assertSessionHasErrors('tatamis.0');
        User::factory()->create(['username' => 'ocupado']);
        $put(['username' => 'ocupado', 'tatamis' => [2]])->assertSessionHasErrors('username');
        $put(['username' => 'juez.nuevo', 'password' => '123', 'tatamis' => [2]])->assertSessionHasErrors('password');
        $this->assertSame([2], $tatamis());

        // Entra con los datos nuevos y ya no con los antiguos.
        auth()->logout();
        $this->post('/acceso', ['email' => 'juez', 'password' => 'nueva-clave'])->assertSessionHasErrors('email');
        $this->post('/acceso', ['email' => 'juez.nuevo', 'password' => 'nueva-clave'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_accounts_not_created_by_the_organizer_only_get_their_tatamis_edited(): void
    {
        [$owner, $event] = $this->setUpEvent();
        $creator = User::factory()->create();
        $account = new User(['name' => 'Ajena', 'username' => 'ajena', 'email' => null, 'password' => 'clave-123']);
        $account->forceFill(['role' => 'tatami', 'created_by_user_id' => $creator->id])->save();
        $organizer = User::factory()->create(['email' => 'org@example.com', 'name' => 'Org']);
        $event->operators()->create(['user_id' => $account->id, 'tatami' => 1]);
        $event->operators()->create(['user_id' => $organizer->id, 'tatami' => 1]);

        foreach ([$account, $organizer] as $user) {
            $operator = $event->operators()->where('user_id', $user->id)->firstOrFail();
            $this->actingAs($owner)->put(route('panel.operators.update', [$event, $operator]), [
                'username' => 'robada', 'name' => 'Robada', 'email' => 'x@example.com', 'password' => 'robada-123', 'tatamis' => [1, 2],
            ])->assertSessionHasNoErrors();

            $fresh = $user->fresh();
            $this->assertNotSame('Robada', $fresh->name);
            $this->assertNotSame('robada', $fresh->username);
            $this->assertFalse(\Hash::check('robada-123', $fresh->password));
            $this->assertSame([1, 2], $event->operators()->where('user_id', $user->id)->orderBy('tatami')->pluck('tatami')->all());
        }

        // Otro organizador no puede editar los controladores de este evento.
        $operator = $event->operators()->where('user_id', $account->id)->firstOrFail();
        $this->actingAs(User::factory()->create())->put(route('panel.operators.update', [$event, $operator]), ['tatamis' => [1]])->assertForbidden();
    }

    public function test_tatami_number_must_exist_and_other_organizers_cannot_manage_operators(): void
    {
        [$owner, $event] = $this->setUpEvent();

        $this->actingAs(User::factory()->create())->post(route('panel.operators.store', $event), ['username' => 'aaa', 'password' => 'clave-123', 'tatamis' => [1]])->assertForbidden();

        $operator = $event->operators()->create(['user_id' => User::factory()->create()->id, 'tatami' => 1]);
        $this->actingAs(User::factory()->create())->delete(route('panel.operators.destroy', [$event, $operator]))->assertForbidden();
        $this->actingAs($owner)->delete(route('panel.operators.destroy', [$event, $operator]))->assertSessionHasNoErrors();
        $this->assertModelMissing($operator);
    }

    public function test_the_operator_only_sees_and_scores_their_tatami_and_nothing_else_of_the_event(): void
    {
        [$owner, $event, $cat1, $cat2] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'juez', 'password' => 'clave-123', 'name' => 'Juez', 'tatamis' => [1]]);
        $operator = User::where('username', 'juez')->firstOrFail();
        auth()->logout();

        // Va directo a su único tatami desde «Mis tatamis».
        $this->actingAs($operator)->get(route('panel.events.index'))->assertRedirect(route('panel.tatamis.show', [$event, 1]));
        $this->actingAs($operator)->get(route('panel.tatamis.show', [$event, 1]))->assertOk()->assertSee('Cat T1')->assertDontSee('Cat T2');

        // Nada más del evento ni otros tatamis, ni crear eventos.
        $this->actingAs($operator)->get(route('panel.tatamis.show', [$event, 2]))->assertForbidden();
        $this->actingAs($operator)->get(route('panel.events.show', $event))->assertForbidden();
        $this->actingAs($operator)->get(route('panel.events.create'))->assertForbidden();
        $this->actingAs($operator)->post(route('panel.events.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($operator)->get(route('panel.brackets.show', [$event, $cat1]))->assertForbidden();

        // Puntúa el combate actual de su tatami (ippon: no hacen falta jueces).
        $current = $cat1->bouts()->where('number', 1)->firstOrFail();
        $this->actingAs($operator)->put(route('panel.tatamis.score', [$event, 1, $current]), ['a_ippon' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame($current->fresh()->slot_a_id, $current->fresh()->winner_id);

        // No puede puntuar un combate que no es el actual ni de otro tatami.
        $notCurrent = $cat1->bouts()->where('number', 3)->firstOrFail();
        $this->actingAs($operator)->put(route('panel.tatamis.score', [$event, 1, $notCurrent]), ['a_ippon' => 1])->assertSessionHasErrors('error');
        $this->assertNull($notCurrent->fresh()->winner_id);
        $other = $cat2->bouts()->where('number', 1)->firstOrFail();
        $this->actingAs($operator)->put(route('panel.tatamis.score', [$event, 1, $other]), ['a_ippon' => 1])->assertNotFound();
        $this->actingAs($operator)->put(route('panel.tatamis.score', [$event, 2, $other]), ['a_ippon' => 1])->assertForbidden();
    }

    public function test_the_owner_can_open_and_score_any_tatami_and_the_event_page_links_to_them(): void
    {
        [$owner, $event, , $cat2] = $this->setUpEvent();

        $this->actingAs($owner)->get(route('panel.events.show', $event))->assertSee(route('panel.tatamis.show', [$event, 2]), false)->assertSee('Controladores de tatami');
        $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 2]))->assertOk()->assertSee('Combate actual')->assertDontSee('>Gana<', false);

        $current = $cat2->bouts()->where('number', 1)->firstOrFail();
        $this->actingAs($owner)->put(route('panel.tatamis.score', [$event, 2, $current]), ['b_wazari' => 2])->assertSessionHasNoErrors();
        $this->assertSame($current->fresh()->slot_b_id, $current->fresh()->winner_id);
        $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 3]))->assertNotFound();
    }

    public function test_the_public_page_shows_only_the_current_state_of_each_tatami_with_published_categories(): void
    {
        [$owner, $event, $cat1, $cat2] = $this->setUpEvent();
        $cat2->forceFill(['is_published' => false])->save();
        $first = $cat1->bouts()->where('number', 1)->with(['slotA.competitor'])->firstOrFail();

        $html = $this->seeResults($event)->get(route('public.events.show', $event))->assertOk()->getContent();

        $this->assertStringContainsString('Tatamis ahora', $html);
        $this->assertStringContainsString('Cat T1 · Combate 1', $html);
        $this->assertStringContainsString($first->slotA->competitor->displayName(), $html);
        $this->assertStringNotContainsString('Cat T2 · Combate', $html, 'categorías sin publicar no aparecen');
        $this->assertStringNotContainsString('A continuación', $html);
        $this->assertStringNotContainsString('Controladores de tatami', $html);
        $this->assertStringNotContainsString('name="a_wazari"', $html);
    }

    public function test_the_operator_form_offers_a_password_generator_and_copy_button(): void
    {
        [$owner, $event] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'juez', 'password' => 'clave-123', 'tatamis' => [1]]);
        $operator = $event->operators()->firstOrFail();

        $html = $this->actingAs($owner)->get(route('panel.events.show', $event))->getContent();

        $this->assertStringContainsString('data-generate-password="#password"', $html);
        $this->assertStringContainsString('data-copy-password="#password"', $html);
        $this->assertStringContainsString('data-generate-password="#new-password-'.$operator->id.'"', $html, 'también al editar el controlador');
        $this->assertStringContainsString('action="'.route('panel.operators.update', [$event, $operator]).'"', $html);
        $this->assertStringContainsString('id="edit-op-'.$operator->id.'"', $html);
        $this->assertStringContainsString('Cópiala antes de guardar', $html);
    }

    public function test_tatami_view_lays_out_two_competitors_and_the_scoring_in_three_columns_with_bout_number_and_bib_tags(): void
    {
        [$owner, $event, $cat1] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.events.bibs', $event));
        $event->forceFill(['status' => EventStatus::InProgress])->save();
        $this->actingAs($owner)->post(route('panel.events.bibs', $event));
        $bout = $cat1->bouts()->where('number', 1)->firstOrFail();

        $html = $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 1]))->getContent();

        $this->assertSame(3, substr_count($html, 'col-12 col-md-4'), 'participante A, participante B y puntuación');
        $this->assertStringContainsString('<span class="bout-number-tag">Combate '.$bout->number.'</span>', $html);
        $this->assertStringContainsString('tatami-competitor-aka', $html);
        $this->assertStringContainsString('tatami-competitor-shiro', $html);
        $this->assertSame(2, substr_count($html, 'class="bib-tag bib-tag-lg"'), 'número de cada participante en un recuadro');
        foreach (['Wazari', 'Ippon', 'Falta'] as $label) {
            $this->assertSame(2, preg_match_all('#score-field-label"[^>]*>'.$label.'<#', $html), 'bloque '.$label.' por participante');
        }
        $this->assertSame(1, substr_count($html, 'data-score-save'), 'un solo formulario de puntuación');
    }

    public function test_one_can_go_back_to_a_scored_bout_see_it_locked_and_correct_it(): void
    {
        [$owner, $event, $cat1] = $this->setUpEvent();
        $b1 = $cat1->bouts()->where('number', 1)->firstOrFail();
        $b2 = $cat1->bouts()->where('number', 2)->firstOrFail();
        $show = fn (array $query = []) => $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 1] + $query));
        $score = fn ($bout, array $data) => $this->actingAs($owner)->put(route('panel.tatamis.score', [$event, 1, $bout]), $data);

        // Combate 1 puntuado (ippon de AKA): el actual pasa a ser el 2 y desde él se puede volver al 1.
        $score($b1, ['a_ippon' => 1])->assertSessionHasNoErrors();
        $html = $show()->assertOk()->assertSee('Combate actual')->assertSee('Combate anterior (1)')->getContent();
        $this->assertStringContainsString(route('panel.tatamis.show', [$event, 1, 'combate' => $b1->id]), html_entity_decode($html));
        $this->assertStringNotContainsString('data-score-lock="locked"', $html, 'el combate actual no está bloqueado');

        // Revisar el combate 1: aparece bloqueado, con «Desbloquear» y la vuelta al actual.
        $review = $show(['combate' => $b1->id])->assertOk()->assertSee('Revisando el combate 1')->assertSee('Resultado guardado')->assertSee('Desbloquear')->assertSee('Combate siguiente (2)')->getContent();
        $this->assertStringContainsString('data-score-lock="locked"', $review);
        $this->assertStringContainsString('data-score-unlock', $review);
        $this->assertStringContainsString('Ganador', $review);

        // Corregirlo (tras desbloquear): ahora gana SHIRO por ippon. El siguiente combate aún no se ha jugado, así que se puede.
        $score($b1->fresh(), ['b_ippon' => 1])->assertSessionHasNoErrors();
        $this->assertSame($b1->fresh()->slot_b_id, $b1->fresh()->winner_id);

        // No se puede puntuar un combate futuro ni entrar a uno de otro tatami o inexistente.
        $future = $cat1->bouts()->where('number', 3)->firstOrFail();
        $score($future, ['a_ippon' => 1])->assertSessionHasErrors('error');
        $show(['combate' => 999999])->assertNotFound();
        $show(['combate' => $this->setUpEvent()[3]->bouts()->first()->id])->assertNotFound();

        // Con los dos combates jugados, el final de la lista permite seguir hasta el actual.
        $score($b2, ['a_ippon' => 1])->assertSessionHasNoErrors();
        $this->assertSame($b2->fresh()->slot_a_id, $b2->fresh()->winner_id);
        $show(['combate' => $b1->id])->assertSee('Combate siguiente (2)');
    }

    public function test_a_correction_is_refused_when_the_next_bout_already_depends_on_it(): void
    {
        [$owner, $event, $cat1] = $this->setUpEvent();
        $b1 = $cat1->bouts()->where('number', 1)->firstOrFail();
        $b2 = $cat1->bouts()->where('number', 2)->firstOrFail();
        $score = fn ($bout, array $data) => $this->actingAs($owner)->put(route('panel.tatamis.score', [$event, 1, $bout]), $data);

        $score($b1, ['a_ippon' => 1]);
        $score($b2, ['a_ippon' => 1]);
        $final = $cat1->bouts()->where('number', 3)->firstOrFail();
        $score($final, ['a_ippon' => 1])->assertSessionHasNoErrors();

        // La final ya está jugada: cambiar el ganador de la semifinal 1 la invalidaría.
        $score($b1->fresh(), ['b_ippon' => 1])->assertSessionHasErrors('error');
        $this->assertSame($b1->fresh()->slot_a_id, $b1->fresh()->winner_id);
    }

    public function test_the_create_form_is_hidden_behind_a_button_and_reopens_only_after_its_own_errors(): void
    {
        [$owner, $event] = $this->setUpEvent();

        $html = $this->actingAs($owner)->get(route('panel.events.show', $event))->getContent();
        $this->assertMatchesRegularExpression('#data-bs-target="\#create-operator" aria-expanded="false"[^>]*>.*?Crear controlador#s', $html);
        $this->assertMatchesRegularExpression('#<form[^>]*class="collapse  row[^"]*"[^>]*id="create-operator"#', $html, 'el formulario empieza plegado');
        $this->assertMatchesRegularExpression('#data-bs-target="\#create-operator">Cancelar</button>#', $html);

        // Un error al crear lo deja desplegado con lo escrito.
        $this->actingAs($owner)->from(route('panel.events.show', $event))
            ->post(route('panel.operators.store', $event), ['_form' => 'create', 'username' => 'ab', 'tatamis' => [1]])->assertSessionHasErrors('username');
        $open = $this->actingAs($owner)->get(route('panel.events.show', $event))->getContent();
        $this->assertMatchesRegularExpression('#<form[^>]*class="collapse show row[^"]*"[^>]*id="create-operator"#', $open);
        $this->assertStringContainsString('value="ab"', $open);

        // Un error en la edición de un controlador no despliega el formulario de crear.
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['_form' => 'create', 'username' => 'juez', 'password' => 'clave-123', 'tatamis' => [1]]);
        $operator = $event->operators()->firstOrFail();
        $this->actingAs($owner)->from(route('panel.events.show', $event))
            ->put(route('panel.operators.update', [$event, $operator]), ['_form' => 'edit', 'username' => 'juez', 'tatamis' => [9]])->assertSessionHasErrors('tatamis.0');
        $afterEdit = $this->actingAs($owner)->get(route('panel.events.show', $event))->getContent();
        $this->assertMatchesRegularExpression('#<form[^>]*class="collapse  row[^"]*"[^>]*id="create-operator"#', $afterEdit);
    }
}
