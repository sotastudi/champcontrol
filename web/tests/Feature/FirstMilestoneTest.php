<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Organizador: evento + categoría, 5 competidores confirmados, cuadro con pases, final y bronce, publicación sin datos privados. */
class FirstMilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_organizer_flow(): void
    {
        $organizer = User::factory()->create();
        $this->actingAs($organizer);

        $this->post(route('panel.events.store'), [
            'name' => 'Open de Otoño', 'discipline' => 'Judo', 'starts_on' => '2026-11-20', 'venue' => 'Pabellón',
            'status' => 'in_progress', 'is_public' => '1', 'registration_form_enabled' => '0',
        ])->assertRedirect();
        $event = Event::firstOrFail();

        $this->post(route('panel.categories.store', $event), [
            'name' => 'Adultos -70 kg', 'bronze_rule' => 'bronze_match', 'show_fourth' => '1',
        ])->assertRedirect();
        $category = Category::firstOrFail();

        $names = ['Ana' => 'Ruiz', 'Berta' => 'Sanz', 'Carla' => 'Vela', 'Dana' => 'Mora', 'Eva' => 'Nieto'];
        $i = 0;
        foreach ($names as $first => $last) {
            $this->post(route('panel.registrations.store', $event), [
                'first_name' => $first, 'last_name' => $last, 'club' => 'Club '.$first, 'birth_date' => '199'.$i.'-05-0'.($i + 1),
                'sex' => 'female', 'weight' => 60, 'email' => strtolower($first).'@privado.test', 'phone' => '60000000'.$i,
                'category_id' => $category->id, 'status' => 'confirmed',
            ])->assertSessionHasNoErrors();
            $i++;
        }

        $this->post(route('panel.brackets.store', [$event, $category]))->assertSessionHasNoErrors();
        $this->assertSame(3, $category->bouts()->where('required_participants', 1)->where('is_bronze', false)->count(), 'cinco competidores → tres pases');

        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $this->put(route('panel.bouts.result', [$event, $category, $bout]), ['winner_id' => $bout->slot_a_id])
                ->assertSessionHasNoErrors();
        }

        $this->patch(route('panel.categories.publication', [$event, $category]));
        auth()->logout();

        $html = $this->get(route('public.categories.show', [$event, $category]))->assertOk()->assertSee('1.º')->assertSee('4.º')->getContent();
        foreach (['@privado.test', '60000000', '199', 'Fecha de nacimiento'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }
}
