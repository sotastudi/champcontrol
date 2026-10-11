<?php

namespace Tests\Feature;

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

    public function test_stale_after_results_shows_manual_notice_without_regenerate(): void
    {
        [$user, $event, $category] = $this->owned(4);
        $this->actingAs($user)->post(route('panel.brackets.store', [$event, $category]));
        $semi = $category->bouts()->where('round', 1)->orderBy('position')->first();
        $this->actingAs($user)->put(route('panel.bouts.result', [$event, $category, $semi]), ['winner_id' => $semi->slot_a_id]);
        Registration::factory()->confirmed()->forCategory($category)->create();

        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))
            ->assertSee('resuélvelos manualmente')->assertDontSee('desactualizado')->assertDontSee('Regenerar cuadro');
    }
}
