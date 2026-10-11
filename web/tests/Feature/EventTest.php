<?php

namespace Tests\Feature;

use App\Enums\BronzeRule;
use App\Enums\DisqualifiedPodiumRule;
use App\Enums\EventStatus;
use App\Enums\PreResultsWithdrawalRule;
use App\Enums\UnfoughtWithdrawalRule;
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

    private function rulesPayload(array $o = []): array
    {
        return array_merge([
            'dq_podium_rule' => 'shift_up', 'unfought_withdrawal_rule' => 'no_place',
            'default_bronze_rule' => 'shared_bronze', 'withdrawal_before_results_rule' => 'allow_walkover',
        ], $o);
    }

    public function test_event_is_created_and_updated_with_the_four_rules(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload($this->rulesPayload()))->assertRedirect();

        $event = Event::firstOrFail();
        $this->assertSame(DisqualifiedPodiumRule::ShiftUp, $event->dqPodiumRule());
        $this->assertSame(UnfoughtWithdrawalRule::NoPlace, $event->unfoughtWithdrawalRule());
        $this->assertSame(BronzeRule::SharedBronze, $event->defaultBronzeRule());
        $this->assertSame(PreResultsWithdrawalRule::AllowWalkover, $event->preResultsWithdrawalRule());

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload($this->rulesPayload([
            'dq_podium_rule' => 'vacant', 'default_bronze_rule' => 'bronze_match',
        ])))->assertRedirect();

        $event->refresh();
        $this->assertSame(DisqualifiedPodiumRule::Vacant, $event->dqPodiumRule());
        $this->assertSame(BronzeRule::BronzeMatch, $event->defaultBronzeRule());
        $this->assertSame(UnfoughtWithdrawalRule::NoPlace, $event->unfoughtWithdrawalRule());
    }

    public function test_invalid_rule_values_are_rejected(): void
    {
        foreach (['dq_podium_rule', 'unfought_withdrawal_rule', 'default_bronze_rule', 'withdrawal_before_results_rule'] as $field) {
            $this->actingAs(User::factory()->create())
                ->post(route('panel.events.store'), $this->payload($this->rulesPayload([$field => 'nope'])))
                ->assertSessionHasErrors($field);
        }
        $this->assertSame(0, Event::count());
    }

    public function test_event_without_rules_gets_the_defaults(): void
    {
        $this->actingAs(User::factory()->create())->post(route('panel.events.store'), $this->payload())->assertRedirect();

        $event = Event::firstOrFail()->fresh();
        $this->assertSame('vacant', $event->getRawOriginal('dq_podium_rule'));
        $this->assertSame('keep_place', $event->getRawOriginal('unfought_withdrawal_rule'));
        $this->assertSame('bronze_match', $event->getRawOriginal('default_bronze_rule'));
        $this->assertSame('cancel_and_regenerate', $event->getRawOriginal('withdrawal_before_results_rule'));
        $this->assertSame(DisqualifiedPodiumRule::Vacant, $event->dqPodiumRule());
    }

    public function test_edit_form_shows_the_four_selects_with_the_saved_values(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create($this->rulesPayload());

        $html = $this->actingAs($user)->get(route('panel.events.edit', $event))->assertOk()
            ->assertSee('Reglas del campeonato')->getContent();

        foreach (['dq_podium_rule' => 'shift_up', 'unfought_withdrawal_rule' => 'no_place', 'default_bronze_rule' => 'shared_bronze', 'withdrawal_before_results_rule' => 'allow_walkover'] as $name => $value) {
            $this->assertMatchesRegularExpression('/<select id="'.$name.'" name="'.$name.'".*?<option value="'.$value.'" selected/s', $html);
        }
        $this->actingAs($user)->get(route('panel.events.create'))->assertOk()->assertSee('name="dq_podium_rule"', false);
    }

    public function test_registration_window_is_saved_shown_and_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload([
            'registration_opens_at' => '2026-11-01T10:00', 'registration_closes_at' => '2026-11-10T20:30',
        ]))->assertSessionHasNoErrors();
        $event = $user->events()->firstOrFail();
        $this->assertSame('2026-11-01 10:00', $event->registration_opens_at->format('Y-m-d H:i'));

        $this->actingAs($user)->get(route('panel.events.edit', $event))
            ->assertSee('value="2026-11-01T10:00"', false)->assertSee('value="2026-11-10T20:30"', false)
            ->assertSee('Inicio de inscripciones')->assertSee('Cierre de inscripciones');

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload([
            'registration_opens_at' => '2026-11-10T20:30', 'registration_closes_at' => '2026-11-01T10:00',
        ]))->assertSessionHasErrors('registration_closes_at');

        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['registration_opens_at' => '', 'registration_closes_at' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($event->fresh()->registration_opens_at);
    }

    public function test_start_time_is_optional_validated_saved_and_shown_next_to_the_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('panel.events.store'), $this->payload(['start_time' => '09:30', 'is_public' => '1', 'status' => 'in_progress']))->assertSessionHasNoErrors();
        $event = $user->events()->firstOrFail();
        $this->assertSame('09:30', $event->startTime());
        $this->assertSame('20 de noviembre de 2026 · 09:30 h', $event->startsAtLabel());

        $this->actingAs($user)->get(route('panel.events.edit', $event))->assertSee('name="start_time"', false)->assertSee('value="09:30"', false)->assertSee('Hora de inicio');
        $this->actingAs($user)->get(route('panel.events.show', $event))->assertSee('20 de noviembre de 2026 · 09:30 h');
        $this->get(route('home'))->assertSee('20 de noviembre de 2026 · 09:30 h');
        $this->get(route('public.events.show', $event))->assertSee('09:30 h');

        foreach (['25:00', 'mediodía', '9:5'] as $bad) {
            $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['start_time' => $bad]))->assertSessionHasErrors('start_time');
        }
        $this->assertSame('09:30', $event->fresh()->startTime());

        // Se puede quitar.
        $this->actingAs($user)->put(route('panel.events.update', $event), $this->payload(['start_time' => '']))->assertSessionHasNoErrors();
        $this->assertNull($event->fresh()->startTime());
        $this->assertSame('20 de noviembre de 2026', $event->fresh()->startsAtLabel());
    }
}
