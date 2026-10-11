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

    public function test_only_the_creator_can_change_the_password_of_a_controller_account(): void
    {
        [$owner, $event] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'juez', 'password' => 'clave-123', 'tatamis' => [1]]);
        $user = User::where('username', 'juez')->firstOrFail();
        $operator = $event->operators()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('panel.operators.password', [$event, $operator]), ['password' => 'nueva-clave'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('nueva-clave', $user->fresh()->password));
        $this->actingAs($owner)->patch(route('panel.operators.password', [$event, $operator]), ['password' => '123'])->assertSessionHasErrors('password');

        // Otro organizador que también le da acceso a su propio evento no puede cambiarle la contraseña.
        $other = User::factory()->create();
        $otherEvent = Event::factory()->for($other)->create(['tatami_count' => 1]);
        $this->actingAs($other)->post(route('panel.operators.store', $otherEvent), ['username' => 'juez', 'tatamis' => [1]])->assertSessionHasNoErrors();
        $otherOperator = $otherEvent->operators()->where('user_id', $user->id)->firstOrFail();
        $this->actingAs($other)->patch(route('panel.operators.password', [$otherEvent, $otherOperator]), ['password' => 'robada-123'])->assertForbidden();
        $this->assertTrue(\Hash::check('nueva-clave', $user->fresh()->password));

        // Ni una cuenta de organizador (no es una cuenta de tatami) se puede cambiar.
        $organizer = User::factory()->create(['email' => 'org@example.com']);
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['username' => 'xyz', 'email' => 'org@example.com', 'tatamis' => [1]]);
        $orgOperator = $event->operators()->where('user_id', $organizer->id)->firstOrFail();
        $this->actingAs($owner)->patch(route('panel.operators.password', [$event, $orgOperator]), ['password' => 'clave-nueva'])->assertForbidden();
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
        $this->assertStringContainsString('data-generate-password="#new-password-'.$operator->id.'"', $html, 'también al cambiar la contraseña');
        $this->assertStringContainsString('Cópiala antes de guardar', $html);
    }
}
