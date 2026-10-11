<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\WithdrawalType;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use App\Tournament\ScoreRecorder;
use App\Tournament\WithdrawalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultResetTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Event, Category} categoría de 4 con el cuadro sembrado en orden (combates 1 y 2 semifinales, 3 final, 4 bronce) */
    private function setUpCategory(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['status' => EventStatus::InProgress, 'tatami_count' => 1]);
        $category = Category::factory()->for($event)->create(['tatami' => 1, 'tatami_position' => 1]);
        Registration::factory()->count(4)->confirmed()->forCategory($category)->create();
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return [$owner, $event, $category];
    }

    private function bout(Category $category, int $number)
    {
        return $category->bouts()->where('number', $number)->firstOrFail();
    }

    private function ippon(Category $category, int $number, string $side = 'a'): void
    {
        app(ScoreRecorder::class)->record($this->bout($category, $number), [$side.'_ippon' => true]);
    }

    public function test_resetting_a_bout_clears_winner_score_and_removes_the_participant_from_the_next_round(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();
        $this->ippon($category, 1);
        app(ScoreRecorder::class)->record($this->bout($category, 1), ['a_ippon' => true, 'a_warnings' => 2]);
        $final = $this->bout($category, 3);
        $this->assertNotNull($final->slot_a_id);

        $this->actingAs($owner)->post(route('panel.bouts.reset', [$event, $category, $this->bout($category, 1)]))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $bout = $this->bout($category, 1);
        $this->assertNull($bout->winner_id);
        $this->assertSame([0, 0, false, 0, null, null], [$bout->a_wazari, $bout->a_warnings, $bout->a_ippon, $bout->b_wazari, $bout->judge_votes, $bout->abandoned_slot]);
        $this->assertNull($this->bout($category, 3)->slot_a_id, 'ya no avanza a la final');
        $this->assertNull($this->bout($category, 4)->slot_a_id, 'ni el perdedor al bronce');

        // Se puede volver a puntuar.
        $this->ippon($category, 1, 'b');
        $this->assertSame($this->bout($category, 1)->slot_b_id, $this->bout($category, 1)->winner_id);
    }

    public function test_a_bout_cannot_be_reset_while_a_later_bout_depends_on_it_but_can_after_resetting_that_one(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();
        foreach ([1, 2, 3] as $n) {
            $this->ippon($category, $n);
        }
        $reset = fn (int $n) => $this->actingAs($owner)->post(route('panel.bouts.reset', [$event, $category, $this->bout($category, $n)]));

        $reset(1)->assertSessionHasErrors('error');
        $this->assertNotNull($this->bout($category, 1)->winner_id);

        $reset(3)->assertSessionHasNoErrors();
        $reset(1)->assertSessionHasNoErrors();
        $this->assertNull($this->bout($category, 1)->winner_id);
        $this->assertNotNull($this->bout($category, 2)->winner_id, 'la otra semifinal no se toca');
    }

    public function test_only_the_owner_can_reset_and_finished_events_and_empty_bouts_are_refused(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();
        $this->ippon($category, 1);
        $url = route('panel.bouts.reset', [$event, $category, $this->bout($category, 1)]);
        $catUrl = route('panel.categories.reset', [$event, $category]);

        $operator = User::factory()->create();
        $event->operators()->create(['user_id' => $operator->id, 'tatami' => 1]);
        $this->actingAs($operator)->post($url)->assertForbidden();
        $this->actingAs($operator)->post($catUrl)->assertForbidden();
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        $this->assertNotNull($this->bout($category, 1)->winner_id);

        $this->actingAs($owner)->post(route('panel.bouts.reset', [$event, $category, $this->bout($category, 2)]))->assertSessionHasErrors('error');

        $event->forceFill(['status' => EventStatus::Finished])->save();
        $this->actingAs($owner)->post($url)->assertSessionHasErrors('error');
        $this->actingAs($owner)->post($catUrl)->assertSessionHasErrors('error');
        $this->assertNotNull($this->bout($category, 1)->winner_id);
    }

    public function test_resetting_the_whole_category_clears_every_result_and_keeps_the_pairings(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();
        $pairs = $category->bouts()->where('round', 1)->orderBy('position')->get()->map(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->all();
        foreach ([1, 2, 3, 4] as $n) {
            $this->ippon($category, $n);
        }
        $this->assertSame(0, $category->bouts()->where('required_participants', 2)->whereNull('winner_id')->count());

        $this->actingAs($owner)->post(route('panel.categories.reset', [$event, $category]))
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, '4 combates'));

        $this->assertSame(0, $category->bouts()->whereNotNull('winner_id')->count());
        $this->assertSame($pairs, $category->bouts()->where('round', 1)->orderBy('position')->get()->map(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->all());
        $this->assertSame(0, $category->bouts()->where('round', '>', 1)->whereNotNull('slot_a_id')->count(), 'las rondas siguientes vuelven a estar vacías');
        $this->assertFalse($category->fresh()->hasResults());
        $this->assertSame(0, $category->bouts()->where(fn ($q) => $q->where('a_ippon', true)->orWhere('b_ippon', true))->count());

        // Sin resultados, el cuadro vuelve a poder regenerarse y a puntuarse.
        $this->ippon($category, 1);
        $this->assertNotNull($this->bout($category, 3)->slot_a_id);
    }

    public function test_resetting_the_category_keeps_withdrawals_and_reapplies_their_walkovers(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();
        $event->forceFill(['withdrawal_before_results_rule' => 'allow_walkover'])->save();
        $this->ippon($category, 1);
        $withdrawn = $this->bout($category, 2)->slotB;
        app(WithdrawalRecorder::class)->record($withdrawn, WithdrawalType::Withdrawn);
        $this->assertTrue($this->bout($category, 2)->isWalkover());

        $this->actingAs($owner)->post(route('panel.categories.reset', [$event, $category]))->assertSessionHasNoErrors();

        $semi = $this->bout($category, 2);
        $this->assertTrue($semi->isWalkover(), 'la baja sigue vigente');
        $this->assertSame($semi->slot_a_id, $semi->winner_id);
        $this->assertNull($this->bout($category, 1)->winner_id, 'el resultado normal sí se borra');
        $this->assertTrue($withdrawn->fresh()->isWithdrawn());
    }

    public function test_the_bracket_shows_the_prominent_confirmation_modal_and_reset_buttons_only_with_results(): void
    {
        [$owner, $event, $category] = $this->setUpCategory();

        $before = $this->actingAs($owner)->get(route('panel.brackets.show', [$event, $category]))->getContent();
        $this->assertStringNotContainsString('data-reset-url', $before, 'sin resultados no hay botones de reiniciar');
        $this->assertStringContainsString('id="reset-modal"', $before);

        $this->ippon($category, 1);
        $html = $this->actingAs($owner)->get(route('panel.brackets.show', [$event, $category]))->getContent();

        $this->assertStringContainsString('data-reset-url="'.route('panel.bouts.reset', [$event, $category, $this->bout($category, 1)]).'"', $html);
        $this->assertStringContainsString('data-reset-url="'.route('panel.categories.reset', [$event, $category]).'"', $html);
        $this->assertStringContainsString('modal-header bg-danger text-white', $html);
        $this->assertStringContainsString('Esta acción no se puede deshacer.', $html);
        $this->assertStringContainsString('Sí, reiniciar', $html);
        $this->assertStringContainsString('de 1 combate', str_replace('de «'.$category->name.'»', '', 'de 1 combate'), 'sanity');
        $this->assertMatchesRegularExpression('#data-reset-message="Se borrarán los resultados y la puntuación de 1 combate#', $html);

        // Un controlador de tatami no ve el reinicio en su vista.
        $operator = User::factory()->create();
        $event->operators()->create(['user_id' => $operator->id, 'tatami' => 1]);
        $tatami = $this->actingAs($operator)->get(route('panel.tatamis.show', [$event, 1, 'combate' => $this->bout($category, 1)->id]))->getContent();
        $this->assertStringNotContainsString('data-reset-url', $tatami);
        $ownerTatami = $this->actingAs($owner)->get(route('panel.tatamis.show', [$event, 1, 'combate' => $this->bout($category, 1)->id]))->getContent();
        $this->assertStringContainsString('data-reset-url="'.route('panel.bouts.reset', [$event, $category, $this->bout($category, 1)]).'"', $ownerTatami);
    }
}
