<?php

namespace Tests\Feature;

use App\Enums\Modality;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Tournament\BracketGenerator;
use App\Tournament\TournamentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_form_offers_the_five_modalities_with_kumite_by_default(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->get(route('panel.categories.create', $event))
            ->assertSeeInOrder(['Kumite', 'Tameshikari', 'Exhibición', 'Combate pactado', 'Katas']);

        $this->post(route('panel.categories.store', $event), ['name' => 'Infantil mixta', 'bronze_rule' => 'bronze_match'])->assertSessionHasNoErrors();
        $this->assertSame('kumite', Category::firstOrFail()->modality);
    }

    public function test_modality_must_be_one_of_the_list(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();

        $this->actingAs($user)->post(route('panel.categories.store', $event), ['name' => 'X', 'modality' => 'sumo', 'bronze_rule' => 'bronze_match'])
            ->assertSessionHasErrors('modality');
    }

    public function test_only_combat_modalities_have_a_bracket(): void
    {
        $this->assertTrue(Modality::Kumite->hasBracket());
        $this->assertTrue(Modality::AgreedBout->hasBracket());
        foreach ([Modality::Tameshikari, Modality::Exhibition, Modality::Kata] as $modality) {
            $this->assertFalse($modality->hasBracket());
        }

        $category = Category::factory()->for(Event::factory())->create(['modality' => 'kata']);
        $this->expectException(TournamentException::class);
        app(BracketGenerator::class)->generate($category);
    }
}
