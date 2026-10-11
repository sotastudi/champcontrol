<?php

namespace Tests\Feature\Tournament;

use App\Enums\DrawCriteria;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use App\Tournament\PodiumCalculator;
use App\Tournament\ResultRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawOrdererTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<array{level:?string,weight:float,club:string}> $rows */
    private function category(array $rows): Category
    {
        $category = Category::factory()->create();
        foreach ($rows as $row) {
            $competitor = Competitor::factory()->create($row);
            Registration::factory()->confirmed()->forCategory($category)->create(['competitor_id' => $competitor->id]);
        }

        return $category;
    }

    /** @return list<array{string,string}> parejas de "nivel/peso" del primer combate */
    private function firstRoundPairs(Category $category): array
    {
        return $category->bouts()->where('round', 1)->where('required_participants', 2)->get()
            ->map(fn ($b) => [$b->slotA->competitor->level.'/'.$b->slotA->competitor->weight, $b->slotB->competitor->level.'/'.$b->slotB->competitor->weight])
            ->all();
    }

    public function test_adjacent_level_and_weight_fight_each_other(): void
    {
        $category = $this->category([
            ['level' => '1º dan', 'weight' => 70, 'club' => 'A'],
            ['level' => '1º dan', 'weight' => 80, 'club' => 'B'],
            ['level' => '5º kyu', 'weight' => 60, 'club' => 'C'],
            ['level' => '5º kyu', 'weight' => 65, 'club' => 'D'],
        ]);

        app(BracketGenerator::class)->generate($category);

        foreach ($this->firstRoundPairs($category) as $pair) {
            $this->assertSame(substr($pair[0], 0, 6), substr($pair[1], 0, 6), 'se enfrentan niveles distintos');
        }
    }

    public function test_highest_levels_get_the_byes(): void
    {
        $category = $this->category([
            ['level' => '3º dan', 'weight' => 70, 'club' => 'A'],
            ['level' => '1º kyu', 'weight' => 70, 'club' => 'B'],
            ['level' => '5º kyu', 'weight' => 70, 'club' => 'C'],
        ]);

        app(BracketGenerator::class)->generate($category);

        $bye = $category->bouts()->where('round', 1)->where('required_participants', 1)->firstOrFail();
        $this->assertSame('3º dan', ($bye->slotA ?? $bye->slotB)->competitor->level);
    }

    public function test_first_bout_avoids_same_club_when_possible(): void
    {
        foreach (range(1, 15) as $i) {
            $category = $this->category([
                ['level' => '1º kyu', 'weight' => 60, 'club' => 'Dojo X'],
                ['level' => '1º kyu', 'weight' => 61, 'club' => 'dojo x '],
                ['level' => '1º kyu', 'weight' => 62, 'club' => 'Dojo Y'],
                ['level' => '1º kyu', 'weight' => 63, 'club' => 'Dojo Y'],
            ]);

            app(BracketGenerator::class)->generate($category);

            foreach ($category->bouts()->where('round', 1)->get() as $bout) {
                $this->assertNotSame(
                    mb_strtolower(trim($bout->slotA->competitor->club)),
                    mb_strtolower(trim($bout->slotB->competitor->club)),
                );
            }
        }
    }

    public function test_competitors_without_level_or_weight_do_not_break_the_draw(): void
    {
        $category = $this->category([
            ['level' => null, 'weight' => null, 'club' => 'A'],
            ['level' => null, 'weight' => null, 'club' => 'A'],
            ['level' => '2º dan', 'weight' => 70, 'club' => 'B'],
            ['level' => null, 'weight' => 55, 'club' => 'C'],
            ['level' => null, 'weight' => null, 'club' => 'D'],
        ]);

        app(BracketGenerator::class)->generate($category);

        $this->assertSame(5, $category->bouts()->where('round', 1)->get()->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter()->count());
    }

    public function test_category_criteria_overrides_the_event_default(): void
    {
        $category = Category::factory()->create();
        $this->assertSame(DrawCriteria::LevelWeightClub, $category->effectiveDrawCriteria());

        $category->event->forceFill(['draw_criteria' => DrawCriteria::Random])->save();
        $this->assertSame(DrawCriteria::Random, $category->fresh()->effectiveDrawCriteria());

        $category->forceFill(['draw_criteria' => DrawCriteria::WeightClub])->save();
        $this->assertSame(DrawCriteria::WeightClub, $category->fresh()->effectiveDrawCriteria());
    }

    public function test_weight_criteria_pairs_by_weight_ignoring_level(): void
    {
        $category = $this->category([
            ['level' => '1º dan', 'weight' => 50, 'club' => 'A'],
            ['level' => '5º kyu', 'weight' => 51, 'club' => 'B'],
            ['level' => '1º dan', 'weight' => 90, 'club' => 'C'],
            ['level' => '5º kyu', 'weight' => 91, 'club' => 'D'],
        ]);
        $category->forceFill(['draw_criteria' => DrawCriteria::WeightClub])->save();

        app(BracketGenerator::class)->generate($category);

        foreach ($this->firstRoundPairs($category) as $pair) {
            $w = array_map(fn ($x) => (float) explode('/', $x)[1], $pair);
            $this->assertLessThan(5, abs($w[0] - $w[1]));
        }
    }

    public function test_forms_and_validation_for_draw_criteria(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->get(route('panel.events.edit', $event))->assertSee('name="draw_criteria"', false)->assertSee('Nivel &gt; peso &gt; club', false);
        $this->actingAs($user)->get(route('panel.categories.create', $event))->assertSee('Según el evento (Nivel &gt; peso &gt; club)', false);

        $payload = ['name' => 'X', 'bronze_rule' => 'bronze_match'];
        $this->actingAs($user)->post(route('panel.categories.store', $event), $payload + ['draw_criteria' => 'nope'])->assertSessionHasErrors('draw_criteria');
        $this->actingAs($user)->post(route('panel.categories.store', $event), $payload + ['draw_criteria' => 'random'])->assertSessionDoesntHaveErrors();
        $this->assertSame(DrawCriteria::Random, $event->categories()->firstOrFail()->draw_criteria);
    }

    public function test_a_forced_same_club_pairing_is_kept_and_reported_as_a_warning(): void
    {
        $rows = [];
        foreach (range(1, 6) as $i) {
            $rows[] = ['level' => '1º kyu', 'weight' => 60 + $i, 'club' => 'Dojo Único'];
        }
        $category = $this->category($rows);

        app(BracketGenerator::class)->generate($category);

        $category->refresh();
        $this->assertSame(3, $category->bouts()->where('round', 1)->where('required_participants', 2)->count(), 'nadie queda suelto aunque se fuerce la regla');
        $this->assertCount(3, $category->draw_warnings);
        $this->assertStringContainsString('mismo club (Dojo Único)', $category->draw_warnings[0]);

        $owner = $category->event->user;
        $this->actingAs($owner)->get(route('panel.brackets.show', [$category->event, $category]))
            ->assertSee('Revisa el sorteo')->assertSee('mismo club (Dojo Único)');
    }

    public function test_no_warning_when_clubs_can_be_separated_and_regenerating_clears_old_warnings(): void
    {
        $category = $this->category([
            ['level' => '1º kyu', 'weight' => 60, 'club' => 'A'], ['level' => '1º kyu', 'weight' => 61, 'club' => 'A'],
            ['level' => '1º kyu', 'weight' => 62, 'club' => 'B'], ['level' => '1º kyu', 'weight' => 63, 'club' => 'B'],
        ]);
        $category->forceFill(['draw_warnings' => ['viejo aviso']])->save();

        app(BracketGenerator::class)->generate($category);

        $this->assertNull($category->fresh()->draw_warnings);
    }

    public function test_every_bracket_size_can_be_played_to_the_final_with_the_loose_rule(): void
    {
        foreach ([5, 6, 7, 9, 12, 21, 37] as $n) {
            $category = Category::factory()->create();
            Registration::factory()->count($n)->confirmed()->forCategory($category)->create();
            app(BracketGenerator::class)->generate($category);

            $recorder = app(ResultRecorder::class);
            while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
                $recorder->record($bout, $bout->slot_a_id);
            }

            $final = $category->bouts()->get()->first(fn ($b) => $b->isFinal());
            $this->assertTrue($final->isCompleted(), "n={$n}: la final no se resolvió");
            $this->assertSame(0, $category->bouts()->where('required_participants', 2)->whereNull('winner_id')->count(), "n={$n}: combates sin ganador");
            $podium = app(PodiumCalculator::class)->for($category->fresh());
            $this->assertGreaterThanOrEqual(3, count($podium), "n={$n}");
        }
    }
}
