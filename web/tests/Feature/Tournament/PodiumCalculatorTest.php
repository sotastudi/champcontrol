<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Enums\WithdrawalType;
use App\Models\Category;
use App\Models\Registration;
use App\Tournament\PodiumCalculator;
use App\Tournament\ResultRecorder;
use App\Tournament\WithdrawalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class PodiumCalculatorTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    /** Juega todos los combates listos: gana siempre el hueco A. */
    private function playAll(Category $category): void
    {
        $recorder = app(ResultRecorder::class);
        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $recorder->record($bout, $bout->slot_a_id);
        }
    }

    private function podium(Category $category): array
    {
        return collect(app(PodiumCalculator::class)->for($category->refresh()))
            ->map(fn ($p) => [$p->place, $p->registration->id])->all();
    }

    public function test_no_podium_until_the_final_is_decided(): void
    {
        $category = $this->generated(4);

        $this->assertSame([], $this->podium($category));
    }

    public function test_four_participants_with_bronze_match_and_fourth(): void
    {
        $category = $this->generated(4);
        $category->update(['show_fourth' => true]);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $this->playAll($category);

        $final = $this->bout($category, 2, 1);
        $bronze = $this->bout($category, 2, 1, true);

        $this->assertSame([
            [1, $final->winner_id], [2, $final->loserId()], [3, $bronze->fresh()->winner_id], [4, $bronze->fresh()->loserId()],
        ], $this->podium($category));
        $this->assertEqualsCanonicalizing([$s1->slot_b_id, $s2->slot_b_id], [$bronze->fresh()->slot_a_id, $bronze->fresh()->slot_b_id]);
    }

    public function test_fourth_is_hidden_unless_enabled(): void
    {
        $category = $this->generated(4);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0));
    }

    public function test_two_participants_only_have_first_and_second(): void
    {
        $category = $this->generated(2);
        $this->playAll($category);

        $this->assertSame([1, 2], array_column($this->podium($category), 0));
    }

    public function test_three_participants_third_comes_from_the_automatic_bronze(): void
    {
        $category = $this->generated(3);
        $category->update(['show_fourth' => true]);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0)); // no hay 4.º
    }

    public function test_shared_bronze_gives_two_thirds_and_no_fourth(): void
    {
        $category = $this->generated(4, BronzeRule::SharedBronze);
        $category->update(['show_fourth' => true]);
        $this->playAll($category);

        $this->assertSame([1, 2, 3, 3], array_column($this->podium($category), 0));
    }

    public function test_five_participants_full_flow(): void
    {
        $category = $this->generated(5);
        $this->playAll($category);

        $this->assertSame([1, 2, 3], array_column($this->podium($category), 0));
    }

    /** Semifinal 1 jugada, el ganador de baja antes de la final y semifinal 2 jugada: el rival gana la final por walkover. */
    private function finalWithoutFirstFinalist(WithdrawalType $type): array
    {
        $category = $this->generated(4);
        $recorder = app(ResultRecorder::class);
        $s1 = $this->bout($category, 1, 1);
        $s2 = $this->bout($category, 1, 2);
        $recorder->record($s1, $s1->slot_a_id);
        app(WithdrawalRecorder::class)->record(Registration::find($s1->slot_a_id), $type, 'motivo');
        $recorder->record($s2, $s2->slot_a_id);

        return [$category, $s1->slot_a_id, $s2->slot_a_id];
    }

    public function test_withdrawn_before_the_final_keeps_second_place(): void
    {
        [$category, $withdrawnId, $championId] = $this->finalWithoutFirstFinalist(WithdrawalType::Withdrawn);

        $placements = app(PodiumCalculator::class)->for($category);

        $this->assertSame([[1, $championId], [2, $withdrawnId]], collect($placements)->take(2)->map(fn ($p) => [$p->place, $p->registration->id])->all());
        $this->assertTrue($placements[1]->withdrawn);
        $this->assertFalse($placements[0]->withdrawn);
        $this->assertSame([], app(PodiumCalculator::class)->vacantPlaces($category));
    }

    public function test_disqualified_is_omitted_without_renumbering_and_place_is_reported_vacant(): void
    {
        [$category, $dqId, $championId] = $this->finalWithoutFirstFinalist(WithdrawalType::Disqualified);

        $bronze = $this->bout($category, 2, 1, true);
        app(ResultRecorder::class)->record($bronze, $bronze->slot_a_id);

        $calculator = app(PodiumCalculator::class);
        $placements = collect($calculator->for($category));

        $this->assertNotContains($dqId, $placements->map(fn ($p) => $p->registration->id)->all());
        $this->assertSame($championId, $placements->firstWhere('place', 1)->registration->id);
        $this->assertNull($placements->firstWhere('place', 2));
        $this->assertNotNull($placements->firstWhere('place', 3));   // sin renumerar
        $this->assertSame([2], $calculator->vacantPlaces($category));
    }
}
