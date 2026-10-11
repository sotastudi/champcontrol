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

class BoutScorePanelTest extends TestCase
{
    use RefreshDatabase;

    private function setUpBracket(): array
    {
        $user = User::factory()->create();
        $category = Category::factory()->for(Event::factory()->for($user)->create(['is_public' => true, 'status' => EventStatus::InProgress]))->create(['referees' => 3, 'is_published' => true]);
        Registration::factory()->count(4)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return [$user, $category->event, $category, $category->bouts()->where('round', 1)->where('position', 1)->firstOrFail()];
    }

    public function test_owner_saves_a_score_and_the_winner_is_recorded(): void
    {
        [$user, $event, $category, $bout] = $this->setUpBracket();

        $this->actingAs($user)->put(route('panel.bouts.score', [$event, $category, $bout]), [
            'a_wazari' => 1, 'b_wazari' => 0, 'a_ippon' => 0, 'b_ippon' => 1, 'a_warnings' => 1, 'b_warnings' => 0,
            'abandoned_slot' => '', 'judge_votes' => [0 => 'aka', 2 => 'shiro'],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $bout->refresh();
        $this->assertSame($bout->slot_b_id, $bout->winner_id);
        $this->assertSame(['aka', null, 'shiro'], $bout->judge_votes, 'se conserva la posición de cada juez');
        $this->assertSame(1, $bout->a_warnings);
    }

    public function test_invalid_input_and_contradictions_show_an_error(): void
    {
        [$user, $event, $category, $bout] = $this->setUpBracket();
        $url = route('panel.bouts.score', [$event, $category, $bout]);

        $this->actingAs($user)->put($url, ['a_wazari' => 5])->assertSessionHasErrors('a_wazari');
        $this->actingAs($user)->put($url, ['judge_votes' => ['rojo']])->assertSessionHasErrors('judge_votes.0');
        $this->actingAs($user)->put($url, ['a_ippon' => 1, 'b_ippon' => 1])->assertSessionHasErrors('error');
        $this->assertNull($bout->fresh()->winner_id);
    }

    public function test_intruder_is_forbidden_and_the_form_shows_one_selector_per_judge(): void
    {
        [$user, $event, $category, $bout] = $this->setUpBracket();

        $this->actingAs(User::factory()->create())->put(route('panel.bouts.score', [$event, $category, $bout]), [])->assertForbidden();

        $html = $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->getContent();
        $this->assertStringContainsString('AKA (rojo)', $html);
        $this->assertStringContainsString('Juez 3', $html);
        $this->assertStringNotContainsString('Juez 4', $html);
        $this->assertStringContainsString('Hikiwake, empate: blanca y roja', $html);
        $this->assertStringContainsString('name="judge_votes[2]"', $html);
        $this->assertStringContainsString('flag-hikiwake', $html);
        $this->assertStringNotContainsString('<select name="judge_votes', $html);
    }

    public function test_public_page_shows_points_and_judges_but_never_the_warnings(): void
    {
        [$user, $event, $category, $bout] = $this->setUpBracket();
        $this->actingAs($user)->put(route('panel.bouts.score', [$event, $category, $bout]), [
            'a_wazari' => 1, 'a_warnings' => 4, 'judge_votes' => ['aka', 'aka', 'shiro'],
        ]);
        auth()->logout();

        $this->get(route('public.categories.show', [$event, $category]))
            ->assertOk()->assertSee('Wazari AKA ×1')->assertSee('Jueces: AKA 2 · SHIRO 1 · hikiwake 0')
            ->assertDontSee('amonest')->assertDontSee('name="a_warnings"', false);
    }
}
