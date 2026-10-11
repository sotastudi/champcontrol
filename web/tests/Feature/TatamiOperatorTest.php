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

    public function test_owner_creates_a_limited_account_and_sees_the_temporary_password_once(): void
    {
        [$owner, $event] = $this->setUpEvent();

        $response = $this->actingAs($owner)->post(route('panel.operators.store', $event), ['email' => 'juez@example.com', 'name' => 'Juez Uno', 'tatami' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('new_operator');
        $password = session('new_operator')['password'];

        $user = User::where('email', 'juez@example.com')->firstOrFail();
        $this->assertTrue($user->isTatamiAccount());
        $this->assertTrue(\Hash::check($password, $user->password));
        $this->assertSame(1, $event->operators()->where('user_id', $user->id)->where('tatami', 1)->count());

        $this->actingAs($owner)->get(route('panel.events.show', $event))->assertSee('juez@example.com')->assertSee($password);
        $this->actingAs($owner)->get(route('panel.events.show', $event))->assertDontSee($password); // solo una vez

        // Sin nombre no se crea una cuenta nueva; con cuenta existente no se pide ni se muestra nada más.
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['email' => 'otra@example.com', 'tatami' => 1])->assertSessionHasErrors('name');
        $this->assertNull(User::where('email', 'otra@example.com')->first());
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['email' => 'juez@example.com', 'tatami' => 2])
            ->assertSessionHasNoErrors()->assertSessionMissing('new_operator');
        $this->assertSame(2, $event->operators()->count());
    }

    public function test_tatami_number_must_exist_and_other_organizers_cannot_manage_operators(): void
    {
        [$owner, $event] = $this->setUpEvent();

        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['email' => 'a@example.com', 'name' => 'A', 'tatami' => 3])->assertSessionHasErrors('tatami');
        $this->actingAs(User::factory()->create())->post(route('panel.operators.store', $event), ['email' => 'a@example.com', 'name' => 'A', 'tatami' => 1])->assertForbidden();

        $operator = $event->operators()->create(['user_id' => User::factory()->create()->id, 'tatami' => 1]);
        $this->actingAs(User::factory()->create())->delete(route('panel.operators.destroy', [$event, $operator]))->assertForbidden();
        $this->actingAs($owner)->delete(route('panel.operators.destroy', [$event, $operator]))->assertSessionHasNoErrors();
        $this->assertModelMissing($operator);
    }

    public function test_the_operator_only_sees_and_scores_their_tatami_and_nothing_else_of_the_event(): void
    {
        [$owner, $event, $cat1, $cat2] = $this->setUpEvent();
        $this->actingAs($owner)->post(route('panel.operators.store', $event), ['email' => 'juez@example.com', 'name' => 'Juez', 'tatami' => 1]);
        $operator = User::where('email', 'juez@example.com')->firstOrFail();
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
}
