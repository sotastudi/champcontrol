<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Competitor;
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
                'first_name' => $first, 'last_name' => $last, 'club' => 'Club '.$first, 'sex' => 'male', 'level' => '3º kyu', 'birth_date' => '199'.$i.'-05-0'.($i + 1),
                'sex' => 'female', 'weight' => 60, 'email' => strtolower($first).'@privado.test', 'phone' => '60000000'.$i,
                'category_id' => $category->id, 'status' => 'confirmed',
            ])->assertSessionHasNoErrors();
            $private[$first] = [
                'iso' => '199'.$i.'-05-0'.($i + 1),
                'dmy' => '0'.($i + 1).'/05/199'.$i,
                'email' => strtolower($first).'@privado.test',
                'phone' => '60000000'.$i,
            ];
            $i++;
        }

        // El organizador sí ve los datos privados: así las comprobaciones públicas pueden fallar.
        $csv = $this->get(route('panel.exports.registrations', $event))->assertOk()->streamedContent();
        foreach ($private as $first => $values) {
            foreach (['email', 'phone', 'dmy'] as $key) {
                $this->assertStringContainsString($values[$key], $csv, "CSV del organizador sin $key de $first");
            }
            $competitor = Competitor::where('first_name', $first)->firstOrFail();
            $this->get(route('panel.competitors.edit', [$event, $competitor]))->assertOk()->assertSee($values['iso']);
        }

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
        $name = fn (?int $registrationId) => Registration::findOrFail($registrationId)->competitor->displayName();
        $podium = [
            1 => $name($final->winner_id),
            2 => $name($final->loserId()),
            3 => $name($bronze->winner_id),
            4 => $name($bronze->loserId()),
        ];
        $this->assertCount(4, array_unique($podium));

        $this->patch(route('panel.categories.publication', [$event, $category]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($category->fresh()->is_published);
        auth()->logout();

        $html = $this->get(route('public.categories.show', [$event, $category]))->assertOk()->getContent();

        // Solo el bloque del podio: el cuadro y los resultados de debajo listan a todos los competidores.
        $this->assertSame(1, preg_match('#<ul class="list-group[^"]*">(.*?)</ul>#s', $html, $block));
        preg_match_all('#<li\b.*?</li>#s', $block[1], $rows);
        $this->assertCount(4, $rows[0], 'el podio tiene cuatro filas');
        foreach ($podium as $place => $expected) {
            $row = $rows[0][$place - 1];
            $this->assertStringContainsString($place.'.º', $row);
            $this->assertStringContainsString($expected, $row);
            foreach (array_diff($podium, [$place => $expected]) as $other) {
                $this->assertStringNotContainsString($other, $row, "$other no debe estar en la fila del $place.º");
            }
        }
        foreach ($private as $values) {
            foreach ($values as $secret) {
                $this->assertStringNotContainsString($secret, $html);
            }
        }
    }
}
