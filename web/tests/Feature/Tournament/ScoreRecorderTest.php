<?php

namespace Tests\Feature\Tournament;

use App\Models\Registration;
use App\Tournament\ScoreRecorder;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class ScoreRecorderTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function score(array $score, int $n = 4): array
    {
        $category = $this->generated($n);
        $bout = $this->bout($category, 1, 1);
        $winner = app(ScoreRecorder::class)->record($bout, $score);

        return [$bout->fresh(), $winner, $category];
    }

    public function test_ippon_wins_immediately_and_advances(): void
    {
        [$bout, $winner, $category] = $this->score(['a_ippon' => true, 'judge_votes' => ['shiro', 'shiro', 'shiro']]);

        $this->assertSame($bout->slot_a_id, $winner);
        $this->assertSame($bout->slot_a_id, $bout->winner_id);
        $this->assertTrue($bout->a_ippon);
        $this->assertSame($bout->slot_a_id, $this->bout($category, 2, 1)->slot_a_id, 'el ganador avanza');
        $this->assertSame(['aka', 'aka', 'aka'], $bout->judge_votes, 'el ippon se lleva la bandera de todos los jueces (aunque se enviaran otras)');
    }

    public function test_ippon_of_shiro_and_two_wazari_set_all_judges_to_that_colour_with_the_category_judges(): void
    {
        $category = $this->generated(4);
        $category->forceFill(['referees' => 5])->save();
        $scores = app(ScoreRecorder::class);

        $scores->record($this->bout($category, 1, 1), ['b_ippon' => true]);
        $this->assertSame(array_fill(0, 5, 'shiro'), $this->bout($category, 1, 1)->judge_votes);

        $scores->record($this->bout($category, 1, 2), ['a_wazari' => 2]);
        $this->assertSame(array_fill(0, 5, 'aka'), $this->bout($category, 1, 2)->judge_votes);
    }

    public function test_two_wazari_make_an_ippon(): void
    {
        [$bout, $winner] = $this->score(['b_wazari' => 2]);

        $this->assertSame($bout->slot_b_id, $winner);
        $this->assertTrue($bout->b_ippon);
        $this->assertSame(2, $bout->b_wazari);
    }

    public function test_without_ippon_or_abandonment_every_judge_must_vote(): void
    {
        $category = $this->generated(4);
        $bout = $this->bout($category, 1, 1);
        $scores = app(ScoreRecorder::class);

        foreach ([[], ['a_wazari' => 1], ['judge_votes' => ['aka', 'aka']], ['judge_votes' => ['aka', null, 'aka']]] as $incomplete) {
            try {
                $scores->record($bout, $incomplete);
                $this->fail('debió exigir los votos de los jueces');
            } catch (TournamentException $e) {
                $this->assertStringContainsString('Faltan los votos de los jueces', $e->getMessage());
                $this->assertNull($bout->fresh()->winner_id);
            }
        }

        // Con los 3 votos (la categoría usa 3 jueces por defecto) se guarda; un wazari solo no decide.
        $winner = $scores->record($bout->fresh(), ['a_wazari' => 1, 'judge_votes' => ['shiro', 'shiro', 'aka']]);
        $this->assertSame($bout->fresh()->slot_b_id, $winner, 'los jueces mandan sobre un wazari');
    }

    public function test_the_number_of_required_votes_follows_the_category_judges(): void
    {
        $category = $this->generated(4);
        $category->forceFill(['referees' => 5])->save();
        $bout = $this->bout($category, 1, 1);
        $scores = app(ScoreRecorder::class);

        $this->expectException(TournamentException::class);
        try {
            $scores->record($bout, ['judge_votes' => ['aka', 'aka', 'aka']]);
        } finally {
            $this->assertNull($bout->fresh()->winner_id);
        }
    }

    public function test_ippon_and_abandonment_need_no_judges(): void
    {
        $category = $this->generated(4);
        $scores = app(ScoreRecorder::class);

        $this->assertSame($this->bout($category, 1, 1)->slot_a_id, $scores->record($this->bout($category, 1, 1), ['a_ippon' => true]));
        $this->assertSame($this->bout($category, 1, 2)->slot_b_id, $scores->record($this->bout($category, 1, 2), ['abandoned_slot' => 'a']));
    }

    public function test_judge_majority_with_hikiwake_not_counting(): void
    {
        [$bout, $winner] = $this->score(['judge_votes' => ['aka', 'hikiwake', 'hikiwake']]);
        $this->assertSame($bout->slot_a_id, $winner);

        [$bout, $winner] = $this->score(['judge_votes' => ['shiro', 'shiro', 'aka']]);
        $this->assertSame($bout->slot_b_id, $winner);
    }

    public function test_tied_judges_leave_the_bout_without_winner_but_keep_the_score(): void
    {
        [$bout, $winner, $category] = $this->score(['judge_votes' => ['aka', 'shiro', 'hikiwake'], 'a_wazari' => 1, 'a_warnings' => 2]);

        $this->assertNull($winner);
        $this->assertNull($bout->winner_id);
        $this->assertSame(['aka', 'shiro', 'hikiwake'], $bout->judge_votes);
        $this->assertSame(2, $bout->a_warnings);
        $this->assertNull($this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_abandonment_makes_the_other_win_and_warnings_do_not_change_the_result(): void
    {
        [$bout, $winner] = $this->score(['abandoned_slot' => 'a', 'a_warnings' => 3, 'b_warnings' => 0]);

        $this->assertSame($bout->slot_b_id, $winner);
        $this->assertSame(3, $bout->a_warnings);

        [$bout, $winner] = $this->score(['a_warnings' => 4, 'judge_votes' => ['aka', 'aka', 'shiro']]);
        $this->assertSame($bout->slot_a_id, $winner, 'las faltas no cambian el ganador');
    }

    public function test_invalid_combinations_and_bouts_are_rejected(): void
    {
        $category = $this->generated(4);
        $bout = $this->bout($category, 1, 1);
        $scores = app(ScoreRecorder::class);

        foreach ([
            ['a_ippon' => true, 'b_ippon' => true],
            ['abandoned_slot' => 'a', 'a_ippon' => true],
            ['judge_votes' => ['rojo']],
            ['abandoned_slot' => 'c'],
        ] as $bad) {
            try {
                $scores->record($bout, $bad);
                $this->fail('debió rechazarse');
            } catch (TournamentException) {
                $this->assertNull($bout->fresh()->winner_id);
            }
        }

        $semi = $this->bout($category, 2, 1); // participantes aún sin definir
        $this->expectException(TournamentException::class);
        $scores->record($semi, ['a_ippon' => true]);
    }

    public function test_a_score_can_correct_a_previous_result_when_nothing_depends_on_it(): void
    {
        $category = $this->generated(4);
        $bout = $this->bout($category, 1, 1);
        $scores = app(ScoreRecorder::class);
        $scores->record($bout, ['a_ippon' => true]);

        $winner = $scores->record($bout->fresh(), ['abandoned_slot' => 'a']);

        $this->assertSame($bout->fresh()->slot_b_id, $winner);
        $this->assertSame($bout->fresh()->slot_b_id, $bout->fresh()->winner_id);
        $this->assertSame(1, Registration::whereKey($winner)->count());
    }
}
