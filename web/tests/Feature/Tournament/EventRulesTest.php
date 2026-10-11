<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Enums\DisqualifiedPodiumRule;
use App\Enums\EventStatus;
use App\Enums\PreResultsWithdrawalRule;
use App\Enums\UnfoughtWithdrawalRule;
use App\Enums\WithdrawalType;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\PodiumCalculator;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use App\Tournament\WithdrawalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class EventRulesTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function rules(Category $category, array $rules): void
    {
        $category->event->update($rules);
    }

    private function podium(Category $category): array
    {
        return collect(app(PodiumCalculator::class)->for($category->refresh()))
            ->map(fn ($p) => [$p->place, $p->registration->id])->all();
    }

    private function places(Category $category): array
    {
        return array_column($this->podium($category), 0);
    }

    private function vacant(Category $category): array
    {
        return app(PodiumCalculator::class)->vacantPlaces($category->refresh());
    }

    private function withdraw(int $registrationId, WithdrawalType $type): void
    {
        app(WithdrawalRecorder::class)->record(Registration::findOrFail($registrationId), $type);
    }

    private function win(Category $category, int $round, int $position, int $winnerId, bool $bronze = false): void
    {
        app(ResultRecorder::class)->record($this->bout($category, $round, $position, $bronze), $winnerId);
    }

    /** 4 con bronce: semi 1 jugada, su ganador descalificado, semi 2 jugada, bronce jugado. DQ en el 2.º puesto. */
    private function fourWithDisqualifiedFinalist(BronzeRule $rule = BronzeRule::BronzeMatch): array
    {
        $category = $this->generated(4, $rule);
        $category->update(['show_fourth' => true]);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->win($category, 1, 1, $s1->slot_a_id);
        $this->withdraw($s1->slot_a_id, WithdrawalType::Disqualified);
        $this->win($category, 1, 2, $s2->slot_a_id);
        if ($rule === BronzeRule::BronzeMatch) {
            $this->win($category, 2, 1, $this->bout($category, 2, 1, true)->slot_a_id, true);
        }

        return [$category->refresh(), $s1, $s2];
    }

    /** 4: semi 1 jugada, semifinalista de la semi 2 retirado sin combatir, final jugada. */
    private function fourWithUnfoughtWithdrawal(BronzeRule $rule): array
    {
        $category = $this->generated(4, $rule);
        $category->update(['show_fourth' => true]);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->win($category, 1, 1, $s1->slot_a_id);
        $this->withdraw($s2->slot_a_id, WithdrawalType::Withdrawn);
        $this->win($category, 2, 1, $s1->slot_a_id);

        return [$category->refresh(), $s1, $s2];
    }

    public function test_dq_with_vacant_rule_leaves_the_place_vacant(): void
    {
        [$category, $s1, $s2] = $this->fourWithDisqualifiedFinalist();

        $this->assertSame([1, 3, 4], $this->places($category));
        $this->assertSame([2], $this->vacant($category));
    }

    public function test_dq_with_shift_up_renumbers_and_reports_no_vacancy(): void
    {
        [$category, $s1, $s2] = $this->fourWithDisqualifiedFinalist();
        $this->rules($category, ['dq_podium_rule' => 'shift_up']);

        $bronze = $this->bout($category, 2, 1, true);
        $this->assertSame(
            [[1, $s2->slot_a_id], [2, $bronze->winner_id], [3, $bronze->loserId()]],
            $this->podium($category),
        );
        $this->assertSame([], $this->vacant($category));
    }

    public function test_shared_bronze_tie_renumbers_densely_with_shift_up(): void
    {
        [$category] = $this->fourWithDisqualifiedFinalist(BronzeRule::SharedBronze);
        $this->assertSame([1, 3, 3], $this->places($category));

        $this->rules($category, ['dq_podium_rule' => 'shift_up']);

        $this->assertSame([1, 2, 2], $this->places($category));
    }

    public function test_unfought_withdrawal_keeps_its_place_by_default(): void
    {
        [$category, $s1, $s2] = $this->fourWithUnfoughtWithdrawal(BronzeRule::BronzeMatch);

        $this->assertSame([1, 2, 3, 4], $this->places($category));
        $withdrawn = collect(app(PodiumCalculator::class)->for($category))->firstWhere('withdrawn', true);
        $this->assertSame($s2->slot_a_id, $withdrawn->registration->id);
    }

    public function test_unfought_withdrawal_without_place_leaves_it_vacant_or_shifts(): void
    {
        [$category, $s1, $s2] = $this->fourWithUnfoughtWithdrawal(BronzeRule::BronzeMatch);
        $this->rules($category, ['unfought_withdrawal_rule' => 'no_place']);

        $this->assertSame([1, 2, 3], $this->places($category));
        $this->assertNotContains($s2->slot_a_id, array_column($this->podium($category), 1));
        $this->assertSame([4], $this->vacant($category));

        $this->rules($category, ['dq_podium_rule' => 'shift_up']);

        $this->assertSame([1, 2, 3], $this->places($category));
        $this->assertSame([], $this->vacant($category));
    }

    public function test_unfought_withdrawal_without_place_in_the_middle_of_the_podium(): void
    {
        // Retirado en la final sin haber combatido: semi 2 por walkover y final por walkover del rival.
        $category = $this->generated(4);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->win($category, 1, 1, $s1->slot_a_id);
        $this->withdraw($s2->slot_a_id, WithdrawalType::Withdrawn);   // semi 2: walkover para s2.b
        $this->withdraw($s2->slot_b_id, WithdrawalType::Withdrawn);   // finalista que solo ganó por walkover
        $category->refresh();
        $this->assertSame($s1->slot_a_id, $this->bout($category, 2, 1)->winner_id);
        $this->rules($category, ['unfought_withdrawal_rule' => 'no_place']);

        $this->assertNotContains($s2->slot_b_id, array_column($this->podium($category), 1));
        $this->assertNotContains(2, $this->places($category));
        $this->assertContains(2, $this->vacant($category));

        $this->rules($category, ['dq_podium_rule' => 'shift_up']);

        $this->assertSame([], $this->vacant($category));
        $this->assertSame(range(1, count($this->places($category))), $this->places($category));
    }

    public function test_withdrawn_who_did_fight_keeps_the_place_even_without_place_rule(): void
    {
        $category = $this->generated(4);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->win($category, 1, 1, $s1->slot_a_id);
        $this->win($category, 1, 2, $s2->slot_a_id);
        $this->withdraw($s1->slot_a_id, WithdrawalType::Withdrawn);   // finalista que ya combatió
        $this->rules($category, ['unfought_withdrawal_rule' => 'no_place']);

        $this->assertSame([[1, $s2->slot_a_id], [2, $s1->slot_a_id]], array_slice($this->podium($category), 0, 2));
    }

    public function test_changing_the_event_rule_changes_the_podium_without_touching_bouts(): void
    {
        [$category] = $this->fourWithDisqualifiedFinalist();
        $before = $category->bouts()->orderBy('id')->get(['id', 'winner_id', 'result_type'])->toArray();

        $this->assertSame([1, 3, 4], $this->places($category));
        $this->rules($category, ['dq_podium_rule' => 'shift_up']);
        $this->assertSame([1, 2, 3], $this->places($category));
        $this->rules($category, ['dq_podium_rule' => 'vacant']);
        $this->assertSame([1, 3, 4], $this->places($category));

        $this->assertSame($before, $category->bouts()->orderBy('id')->get(['id', 'winner_id', 'result_type'])->toArray());
    }

    public function test_event_helpers_never_return_null(): void
    {
        $event = new Event;

        $this->assertSame(DisqualifiedPodiumRule::Vacant, $event->dqPodiumRule());
        $this->assertSame(UnfoughtWithdrawalRule::KeepPlace, $event->unfoughtWithdrawalRule());
        $this->assertSame(BronzeRule::BronzeMatch, $event->defaultBronzeRule());
        $this->assertSame(PreResultsWithdrawalRule::CancelAndRegenerate, $event->preResultsWithdrawalRule());
    }

    public function test_pre_results_withdrawal_is_refused_by_default(): void
    {
        $category = $this->generated(4);

        $this->expectException(TournamentException::class);
        $this->withdraw($this->bout($category, 1, 1)->slot_a_id, WithdrawalType::Withdrawn);
    }

    public function test_pre_results_withdrawal_is_allowed_as_walkover_with_the_event_rule(): void
    {
        $category = $this->generated(4);
        $this->rules($category, ['withdrawal_before_results_rule' => 'allow_walkover']);
        $s1 = $this->bout($category, 1, 1);
        $this->assertFalse($category->hasResults());

        $this->withdraw($s1->slot_a_id, WithdrawalType::Withdrawn);

        $s1->refresh();
        $this->assertSame($s1->slot_b_id, $s1->winner_id);
        $this->assertTrue($s1->isWalkover());
        $this->assertTrue($category->hasResults());
    }

    public function test_pre_results_walkover_still_requires_a_bracket(): void
    {
        $category = $this->categoryWithConfirmed(4);
        $this->rules($category, ['withdrawal_before_results_rule' => 'allow_walkover']);

        $this->expectException(TournamentException::class);
        $this->withdraw($category->registrations()->first()->id, WithdrawalType::Withdrawn);
    }

    public function test_panel_shows_the_withdrawals_section_according_to_rule_and_state(): void
    {
        $category = $this->generated(4);
        $owner = $category->event->user;
        $url = route('panel.brackets.show', [$category->event, $category]);

        $this->actingAs($owner)->get($url)->assertOk()->assertDontSee('Retiradas y descalificaciones');

        $this->rules($category, ['withdrawal_before_results_rule' => 'allow_walkover']);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Retiradas y descalificaciones');

        $this->rules($category, ['withdrawal_before_results_rule' => 'cancel_and_regenerate']);
        $this->win($category, 1, 1, $this->bout($category, 1, 1)->slot_a_id);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Retiradas y descalificaciones');
    }

    public function test_panel_does_not_show_the_section_without_a_bracket_even_with_allow_walkover(): void
    {
        $category = $this->categoryWithConfirmed(4);
        $this->rules($category, ['withdrawal_before_results_rule' => 'allow_walkover']);

        $this->actingAs($category->event->user)->get(route('panel.brackets.show', [$category->event, $category]))
            ->assertOk()->assertDontSee('Retiradas y descalificaciones');
    }

    public function test_panel_withdrawal_post_before_results_follows_the_rule_and_intruders_get_403(): void
    {
        $category = $this->generated(4);
        $owner = $category->event->user;
        $target = $this->bout($category, 1, 1)->slot_a_id;
        $url = route('panel.withdrawals.store', [$category->event, $category]);

        $this->actingAs($owner)->post($url, ['registration_id' => $target, 'type' => 'withdrawn'])->assertSessionHasErrors('error');
        $this->assertFalse(Registration::find($target)->isWithdrawn());

        $this->rules($category, ['withdrawal_before_results_rule' => 'allow_walkover']);
        $this->actingAs(User::factory()->create())->post($url, ['registration_id' => $target, 'type' => 'withdrawn'])->assertForbidden();
        $this->actingAs($owner)->post($url, ['registration_id' => $target, 'type' => 'withdrawn'])->assertSessionHasNoErrors();
        $this->assertTrue(Registration::find($target)->isWithdrawn());
    }

    public function test_standings_csv_reflects_the_rules(): void
    {
        [$category] = $this->fourWithDisqualifiedFinalist();
        $category->event->update(['status' => EventStatus::InProgress]);
        $owner = $category->event->user;

        $places = function () use ($owner, $category) {
            $csv = $this->actingAs($owner)->get(route('panel.exports.standings', $category->event))->streamedContent();
            $rows = array_values(array_map(fn ($l) => str_getcsv($l, ';', '"', ''), array_filter(preg_split('/\r?\n/', ltrim($csv, "\xEF\xBB\xBF")))));

            return collect(array_slice($rows, 1))->where(0, $category->name)->pluck(1)->sort()->values()->all();
        };

        $this->assertSame(['1', '3', '4'], $places());

        $this->rules($category, ['dq_podium_rule' => 'shift_up']);

        $this->assertSame(['1', '2', '3'], $places());
    }
}
