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
    }

    public function test_two_wazari_make_an_ippon(): void
    {
        [$bout, $winner] = $this->score(['b_wazari' => 2]);

        $this->assertSame($bout->slot_b_id, $winner);
        $this->assertTrue($bout->b_ippon);
        $this->assertSame(2, $bout->b_wazari);
    }

    public function test_one_wazari_alone_decides_only_without_judges(): void
    {
        [$bout, $winner] = $this->score(['a_wazari' => 1]);
        $this->assertSame($bout->slot_a_id, $winner);
        $this->assertFalse($bout->a_ippon);

        [$bout, $winner] = $this->score(['a_wazari' => 1, 'judge_votes' => ['shiro', 'shiro', 'aka']]);
        $this->assertSame($bout->slot_b_id, $winner, 'los jueces mandan sobre un wazari');
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
