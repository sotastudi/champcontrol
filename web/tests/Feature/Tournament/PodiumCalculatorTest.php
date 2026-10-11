<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Models\Category;
use App\Tournament\PodiumCalculator;
use App\Tournament\ResultRecorder;
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
}
