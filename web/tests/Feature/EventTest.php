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

    public function test_slug_falls_back_when_name_has_no_sluggable_characters(): void
    {
        $event = Event::factory()->create(['name' => '!!!']);

        $this->assertMatchesRegularExpression('/^evento-[a-z0-9]{6}$/', $event->slug);
    }
}
