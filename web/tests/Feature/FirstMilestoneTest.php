<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
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
        $private = [];
        foreach ($names as $first => $last) {
            $this->post(route('panel.registrations.store', $event), [
                'first_name' => $first, 'last_name' => $last, 'club' => 'Club '.$first, 'birth_date' => '199'.$i.'-05-0'.($i + 1),
                'sex' => 'female', 'weight' => 60, 'email' => strtolower($first).'@privado.test', 'phone' => '60000000'.$i,
                'category_id' => $category->id, 'status' => 'confirmed',
            ])->assertSessionHasNoErrors();
            $private[] = '199'.$i.'-05-0'.($i + 1);
            $private[] = '0'.($i + 1).'/05/199'.$i;
            $private[] = strtolower($first).'@privado.test';
            $private[] = '60000000'.$i;
            $i++;
        }

        // El organizador sí ve los datos privados (CSV de inscritos): así las comprobaciones públicas pueden fallar.
        $csv = $this->get(route('panel.exports.registrations', $event))->assertOk()->streamedContent();
        foreach ([$private[2], $private[3]] as $value) {
            $this->assertStringContainsString($value, $csv);
        }
        $this->assertTrue(
            str_contains($csv, $private[0]) || str_contains($csv, $private[1]),
            'el CSV del organizador debe contener la fecha de nacimiento'
        );

        $this->post(route('panel.brackets.store', [$event, $category]))->assertSessionHasNoErrors();
        $this->assertSame(3, $category->bouts()->where('required_participants', 1)->where('is_bronze', false)->count(), 'cinco competidores → tres pases');

        while ($bout = $category->bouts()->orderBy('number')->get()->first(fn ($b) => $b->isReady())) {
            $this->put(route('panel.bouts.result', [$event, $category, $bout]), ['winner_id' => $bout->slot_a_id])
                ->assertSessionHasNoErrors();
        }

        // Todos los combates reales (no pases) se han resuelto: con 5 participantes hay 2 combates de primera ronda, 1 semifinal, 1 final y 1 bronce.
        $real = $category->bouts()->where('required_participants', 2)->get();
        $this->assertCount(5, $real, 'cinco competidores → cinco combates reales (incluido el bronce)');
        $this->assertCount(0, $real->filter(fn ($b) => $b->winner_id === null), 'ningún combate real sin ganador');

        $final = $category->bouts()->get()->first(fn ($b) => $b->isFinal());
        $bronze = $category->bouts()->where('is_bronze', true)->firstOrFail();
        $first = $final->winner->competitor->first_name;
        $fourth = $bronze->fresh()->loserId() ? Registration::find($bronze->fresh()->loserId())->competitor->first_name : null;
        $this->assertNotNull($fourth);

        $this->patch(route('panel.categories.publication', [$event, $category]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($category->fresh()->is_published);
        auth()->logout();

        $response = $this->get(route('public.categories.show', [$event, $category]))->assertOk()
            ->assertSeeInOrder(['1.º', $first])
            ->assertSee('4.º');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/4\.º.{0,400}?'.preg_quote($fourth, '/').'/su', $html);
        foreach ($private as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }
}
