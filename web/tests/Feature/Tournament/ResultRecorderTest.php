<?php

namespace Tests\Feature\Tournament;

use App\Enums\EventStatus;
use App\Models\Registration;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class ResultRecorderTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    private function recorder(): ResultRecorder
    {
        return app(ResultRecorder::class);
    }

    public function test_winner_advances_and_loser_goes_to_bronze(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);

        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);          // final, hueco a
        $this->assertSame($semi->slot_b_id, $this->bout($category, 2, 1, true)->slot_a_id);    // bronce, hueco a
        $this->assertSame($semi->slot_a_id, $semi->fresh()->winner_id);
    }

    public function test_cannot_record_before_both_participants_are_defined(): void
    {
        $category = $this->generated(5);
        $final = $category->bouts()->where('is_bronze', false)->orderByDesc('round')->first();

        $this->expectException(TournamentException::class);
        $this->recorder()->record($final, $final->slot_a_id ?? Registration::first()->id);
    }

    public function test_cannot_record_a_pase(): void
    {
        $category = $this->generated(5);
        $bye = $category->bouts()->where('required_participants', 1)->first();

        $this->expectException(TournamentException::class);
        $this->recorder()->record($bye, $bye->slot_a_id ?? $bye->slot_b_id);
    }

    public function test_winner_must_be_one_of_the_two_participants_and_nothing_changes(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        $stranger = $this->generated(2)->registrations()->first(); // otra categoría

        foreach ([$stranger->id, 999999, $this->bout($category, 1, 2)->slot_a_id] as $bad) {
            try {
                $this->recorder()->record($semi, $bad);
                $this->fail('Debió rechazar el ganador '.$bad);
            } catch (TournamentException) {
            }
        }

        $this->assertNull($semi->fresh()->winner_id);
        $this->assertNull($this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_recording_the_same_winner_twice_is_idempotent(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->recorder()->record($semi->fresh(), $semi->slot_a_id);

        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1)->slot_a_id);
    }

    public function test_winner_can_be_corrected_while_dependent_bouts_have_no_result(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->recorder()->record($semi->fresh(), $semi->slot_b_id);

        $this->assertSame($semi->slot_b_id, $this->bout($category, 2, 1)->slot_a_id);
        $this->assertSame($semi->slot_a_id, $this->bout($category, 2, 1, true)->slot_a_id);
    }

    public function test_correction_is_refused_when_the_final_already_has_a_result(): void
    {
        $category = $this->generated(4);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->recorder()->record($s1, $s1->slot_a_id);
        $this->recorder()->record($s2, $s2->slot_a_id);
        $final = $this->bout($category, 2, 1)->refresh();
        $this->recorder()->record($final, $final->slot_a_id);

        try {
            $this->recorder()->record($s1->fresh(), $s1->slot_b_id);
            $this->fail('Debió rechazar la corrección');
        } catch (TournamentException) {
            $this->assertSame($s1->slot_a_id, $s1->fresh()->winner_id);
        }
    }

    public function test_with_three_participants_the_semifinal_loser_takes_bronze_automatically(): void
    {
        $category = $this->generated(3);
        $semi = $this->bout($category, 1, 2); // el único combate real de primera ronda
        $bronze = $this->bout($category, 2, 1, true);

        $this->recorder()->record($semi, $semi->slot_a_id);
        $this->assertSame($semi->slot_b_id, $bronze->fresh()->winner_id);

        // Corregir la semifinal reasigna el bronce.
        $this->recorder()->record($semi->fresh(), $semi->slot_b_id);
        $this->assertSame($semi->slot_a_id, $bronze->fresh()->winner_id);
    }

    public function test_finished_events_do_not_accept_results(): void
    {
        $category = $this->generated(4);
        $category->event->update(['status' => EventStatus::Finished]);
        $semi = $this->bout($category, 1, 1);

        $this->expectException(TournamentException::class);
        $this->recorder()->record($semi, $semi->slot_a_id);
    }
}
