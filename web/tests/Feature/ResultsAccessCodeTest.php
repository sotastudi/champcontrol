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

class ResultsAccessCodeTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithResults(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['is_public' => true, 'status' => EventStatus::InProgress, 'tatami_count' => 1]);
        $category = Category::factory()->for($event)->create(['name' => 'Cat Visible', 'is_published' => true, 'tatami' => 1]);
        Registration::factory()->count(2)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return [$owner, $event, $category];
    }

    public function test_every_event_gets_a_unique_unambiguous_code_on_creation(): void
    {
        $codes = Event::factory()->count(5)->create()->pluck('access_code');

        $this->assertCount(5, $codes->unique());
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}$/', $code);
        }

        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('panel.events.store'), [
            'name' => 'Nuevo', 'discipline' => 'Judo', 'starts_on' => '2026-11-20', 'venue' => 'Pabellón', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($owner->events()->firstOrFail()->access_code);
    }

    public function test_without_the_code_the_public_sees_the_event_but_no_results(): void
    {
        [, $event, $category] = $this->eventWithResults();

        $html = $this->get(route('public.events.show', $event))->assertOk()->getContent();
        $this->assertStringContainsString($event->name, $html);
        $this->assertStringContainsString('código de acceso', $html);
        $this->assertStringNotContainsString('Cat Visible', $html);
        $this->assertStringNotContainsString('Tatamis ahora', $html);
        $this->assertStringNotContainsString($event->access_code, $html);

        $this->get(route('public.categories.show', [$event, $category]))->assertRedirect(route('public.events.show', $event));
    }

    public function test_entering_the_right_code_unlocks_results_in_that_session_only(): void
    {
        [, $event, $category] = $this->eventWithResults();
        $other = Event::factory()->create(['is_public' => true, 'status' => EventStatus::InProgress]);

        $event->forceFill(['access_code' => 'AB12'])->save();
        $this->post(route('public.events.access', $event), ['code' => 'ZZZZ'])->assertSessionHasErrors('code');
        $this->get(route('public.categories.show', [$event, $category]))->assertRedirect();

        // Sin distinguir mayúsculas, espacios ni guiones.
        $typed = strtolower(substr($event->access_code, 0, 2).' - '.substr($event->access_code, 2));
        $this->post(route('public.events.access', $event), ['code' => $typed])->assertRedirect(route('public.events.show', $event))->assertSessionHasNoErrors();

        $this->get(route('public.events.show', $event))->assertSee('Cat Visible')->assertSee('Tatamis ahora');
        $this->get(route('public.categories.show', [$event, $category]))->assertOk();

        // El código de un evento no abre otro.
        $this->get(route('public.events.show', $other))->assertSee('código de acceso');
    }

    public function test_regenerating_the_code_invalidates_the_old_one_and_open_sessions(): void
    {
        [$owner, $event, $category] = $this->eventWithResults();
        $old = $event->access_code;
        $this->post(route('public.events.access', $event), ['code' => $old])->assertSessionHasNoErrors();
        $this->get(route('public.categories.show', [$event, $category]))->assertOk();

        $this->actingAs($owner)->post(route('panel.events.access-code', $event))->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertNotSame($old, $event->fresh()->access_code);
        $this->get(route('public.categories.show', [$event, $category]))->assertRedirect(route('public.events.show', $event));
        $this->post(route('public.events.access', $event), ['code' => $old])->assertSessionHasErrors('code');
        $this->post(route('public.events.access', $event), ['code' => $event->fresh()->access_code])->assertSessionHasNoErrors();
        $this->get(route('public.categories.show', [$event, $category]))->assertOk();
    }

    public function test_owner_and_tatami_operators_need_no_code_and_only_the_owner_sees_and_regenerates_it(): void
    {
        [$owner, $event, $category] = $this->eventWithResults();
        $operator = User::factory()->create();
        $event->operators()->create(['user_id' => $operator->id, 'tatami' => 1]);

        $this->actingAs($owner)->get(route('public.categories.show', [$event, $category]))->assertOk();
        $this->actingAs($operator)->get(route('public.categories.show', [$event, $category]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('public.categories.show', [$event, $category]))->assertRedirect();

        $this->actingAs($owner)->get(route('panel.events.show', $event))->assertSee($event->access_code);
        $this->actingAs($operator)->get(route('panel.events.show', $event))->assertForbidden();
        $this->actingAs($operator)->post(route('panel.events.access-code', $event))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('panel.events.access-code', $event))->assertForbidden();
    }

    public function test_code_attempts_are_throttled_and_private_events_have_no_form(): void
    {
        [, $event] = $this->eventWithResults();
        $private = Event::factory()->create(['is_public' => false]);
        $this->post(route('public.events.access', $private), ['code' => $private->access_code])->assertNotFound();
        $event->forceFill(['access_code' => 'AB12'])->save();
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('public.events.access', $event), ['code' => 'MALA']);
        }
        $this->post(route('public.events.access', $event), ['code' => 'MALA'])->assertStatus(429);

    }
}
