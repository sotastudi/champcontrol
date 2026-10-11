<?php

namespace Tests\Feature\Tournament;

use App\Enums\BronzeRule;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Tournament\BracketGenerator;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBrackets;
use Tests\TestCase;

class BracketGeneratorTest extends TestCase
{
    use BuildsBrackets, RefreshDatabase;

    public function test_zero_or_one_participant_generates_nothing_and_never_names_a_champion(): void
    {
        foreach ([0, 1] as $n) {
            $category = $this->categoryWithConfirmed($n);
            try {
                app(BracketGenerator::class)->generate($category);
                $this->fail("n={$n} debió lanzar excepción");
            } catch (TournamentException) {
                $this->assertSame(0, $category->bouts()->count());
            }
        }
    }

    public function test_only_confirmed_registrations_enter_the_bracket(): void
    {
        $category = $this->categoryWithConfirmed(3);
        Registration::factory()->forCategory($category)->create(['status' => RegistrationStatus::Pending]);
        Registration::factory()->forCategory($category)->create(['status' => RegistrationStatus::Cancelled]);

        app(BracketGenerator::class)->generate($category);

        $inBracket = $category->bouts()->where('round', 1)->get()->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter()->values()->all();
        $this->assertCount(3, $inBracket);
        $this->assertEqualsCanonicalizing(
            $category->registrations()->where('status', 'confirmed')->pluck('id')->all(),
            $inBracket,
        );
    }

    public function test_two_participants_generate_a_single_final(): void
    {
        $category = $this->generated(2);

        $this->assertSame(1, $category->bouts()->count());
        $final = $this->bout($category, 1, 1);
        $this->assertTrue($final->isFinal());
        $this->assertTrue($final->isReady());
        $this->assertNotNull($final->number);
    }

    public function test_four_participants_have_semis_final_and_bronze_without_byes(): void
    {
        $category = $this->generated(4);

        $this->assertSame(4, $category->bouts()->count());
        $this->assertSame(0, $category->bouts()->where('required_participants', 1)->count());
        $bronze = $this->bout($category, 2, 1, true);
        $this->assertSame(2, $bronze->required_participants);
        $this->assertSame($bronze->id, $this->bout($category, 1, 1)->loser_next_bout_id);
        $this->assertSame($bronze->id, $this->bout($category, 1, 2)->loser_next_bout_id);
        $this->assertSame([1, 2, 3, 4], $category->bouts()->orderBy('number')->pluck('number')->all());
    }

    public function test_five_participants_get_three_byes_that_advance_automatically(): void
    {
        $category = $this->generated(5);
        $ids = $category->registrations()->orderBy('id')->pluck('id');

        // 8 plazas: 4 de primera ronda + 2 semis + final + bronce.
        $this->assertSame(8, $category->bouts()->count());
        $byes = $category->bouts()->where('round', 1)->where('required_participants', 1)->get();
        $this->assertCount(3, $byes);
        $this->assertTrue($byes->every(fn ($b) => $b->isCompleted() && $b->number === null));

        // Siembra estándar con sorteo identidad: 1-pase, 4v5, 2-pase, 3v6(pase)
        $m2 = $this->bout($category, 1, 2);
        $this->assertSame([$ids[3], $ids[4]], [$m2->slot_a_id, $m2->slot_b_id]);
        $this->assertTrue($m2->isReady());

        $semi1 = $this->bout($category, 2, 1);
        $this->assertSame($ids[0], $semi1->slot_a_id);   // el cabeza de la plaza 1 pasa directo
        $this->assertNull($semi1->slot_b_id);            // espera al ganador de 4v5

        // Ningún participante aparece dos veces en primera ronda y no hay dos pases enfrentados.
        $round1 = $category->bouts()->where('round', 1)->get();
        $placed = $round1->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter();
        $this->assertSame($placed->count(), $placed->unique()->count());
        $this->assertTrue($round1->every(fn ($b) => $b->filledSlots() >= 1));

        // Numeración contigua solo para combates reales (2 de primera ronda... y 2 semis, bronce, final).
        $real = $category->bouts()->whereNotNull('number')->orderBy('number')->pluck('number')->all();
        $this->assertSame(range(1, count($real)), $real);
        $this->assertCount(5, $real);
    }

    public function test_three_participants_bronze_bout_is_a_single_participant_pase(): void
    {
        $category = $this->generated(3);
        $bronze = $this->bout($category, 2, 1, true);

        $this->assertSame(1, $bronze->required_participants);
        $this->assertNull($bronze->number);
        $this->assertSame(1, $category->bouts()->whereNull('number')->where('is_bronze', false)->count()); // el pase de semifinal
    }

    public function test_shared_bronze_creates_no_bronze_bout(): void
    {
        $category = $this->generated(4, BronzeRule::SharedBronze);

        $this->assertSame(3, $category->bouts()->count());
        $this->assertFalse($category->bouts()->where('is_bronze', true)->exists());
    }

    public function test_default_shuffle_places_every_participant_exactly_once(): void
    {
        $category = $this->categoryWithConfirmed(9);
        app(BracketGenerator::class)->generate($category);

        $placed = $category->bouts()->where('round', 1)->get()->flatMap(fn ($b) => [$b->slot_a_id, $b->slot_b_id])->filter();
        $this->assertCount(9, $placed->unique());
    }

    public function test_regenerate_replaces_the_bracket_while_there_are_no_results(): void
    {
        $category = $this->generated(4);
        Registration::factory()->confirmed()->forCategory($category)->create();

        app(BracketGenerator::class)->generate($category->refresh());

        $this->assertSame(8, $category->bouts()->count()); // 5 participantes ahora
        $this->assertNotNull($category->fresh()->bracket_generated_at);
    }

    public function test_regenerate_is_refused_once_a_result_exists(): void
    {
        $category = $this->generated(4);
        $semi = $this->bout($category, 1, 1);
        $semi->update(['winner_id' => $semi->slot_a_id]);

        $this->expectException(TournamentException::class);
        app(BracketGenerator::class)->generate($category->refresh());
    }
}
