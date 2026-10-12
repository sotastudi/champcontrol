<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Tournament\BracketGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function competitor(Event $event, Category $category, string $last, string $first, ?string $club, string $status = 'confirmed'): Competitor
    {
        $competitor = Competitor::factory()->create(['event_id' => $event->id, 'last_name' => $last, 'first_name' => $first, 'club' => $club]);
        Registration::factory()->create(['category_id' => $category->id, 'competitor_id' => $competitor->id, 'status' => $status]);

        return $competitor;
    }

    public function test_numbers_are_correlative_grouped_by_club_and_only_for_confirmed_competitors(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::RegistrationClosed]);
        $a = Category::factory()->for($event)->create();
        $b = Category::factory()->for($event)->create();

        $zeta1 = $this->competitor($event, $a, 'Soler', 'Zoe', 'Dojo Zeta');
        $alfa2 = $this->competitor($event, $a, 'Vidal', 'Ana', 'dojo alfa ');
        $alfa1 = $this->competitor($event, $a, 'Álvarez', 'Pau', 'Dojo Alfa');
        $none = $this->competitor($event, $a, 'Aaa', 'Sin', null);
        $cancelled = $this->competitor($event, $a, 'Canc', 'Elada', 'Dojo Alfa', 'cancelled');
        // Mismo competidor en otra categoría: un único número.
        Registration::factory()->create(['category_id' => $b->id, 'competitor_id' => $alfa1->id, 'status' => 'confirmed']);

        $this->actingAs($user)->post(route('panel.events.bibs', $event))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(1, $alfa1->fresh()->bib_number, 'Álvarez antes que Vidal dentro del mismo club');
        $this->assertSame(2, $alfa2->fresh()->bib_number);
        $this->assertSame(3, $zeta1->fresh()->bib_number);
        $this->assertSame(4, $none->fresh()->bib_number, 'sin club al final');
        $this->assertNull($cancelled->fresh()->bib_number);
    }

    public function test_numbers_cannot_be_assigned_while_registrations_are_pending(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::RegistrationClosed]);
        $category = Category::factory()->for($event)->create();
        $confirmed = $this->competitor($event, $category, 'Roca', 'Lia', 'Club X');
        $pending = $this->competitor($event, $category, 'Mora', 'Teo', 'Club X', 'pending');

        $this->actingAs($user)->get(route('panel.events.show', $event))->assertSee('Quedan 1 inscripción pendiente')->assertSee('Ver pendientes');
        $this->post(route('panel.events.bibs', $event))->assertSessionHasErrors('error');
        $this->assertNull($confirmed->fresh()->bib_number);

        // Aprobada (o cancelada) la última pendiente, ya se pueden asignar.
        $pending->registrations()->firstOrFail()->update(['status' => 'confirmed']);
        $this->post(route('panel.events.bibs', $event))->assertSessionHasNoErrors();
        $this->assertNotNull($confirmed->fresh()->bib_number);
        $this->get(route('panel.events.show', $event))->assertDontSee('Quedan');
    }

    public function test_reassigning_renumbers_from_scratch_and_it_requires_closed_registration(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::RegistrationOpen]);
        $category = Category::factory()->for($event)->create();
        $first = $this->competitor($event, $category, 'Bea', 'A', 'Club B');

        $this->actingAs($user)->post(route('panel.events.bibs', $event))->assertSessionHasErrors('error');
        $this->assertNull($first->fresh()->bib_number);

        $event->forceFill(['status' => EventStatus::InProgress])->save();
        $this->actingAs($user)->post(route('panel.events.bibs', $event))->assertSessionHasNoErrors();
        $this->assertSame(1, $first->fresh()->bib_number);

        $earlier = $this->competitor($event, $category, 'Zz', 'Z', 'Club A');
        $this->actingAs($user)->post(route('panel.events.bibs', $event));
        $this->assertSame(1, $earlier->fresh()->bib_number);
        $this->assertSame(2, $first->fresh()->bib_number);
    }

    public function test_numbers_are_shown_in_list_ficha_csv_and_bracket_and_intruders_are_forbidden(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => EventStatus::RegistrationClosed]);
        $category = Category::factory()->for($event)->create();
        $competitor = $this->competitor($event, $category, 'Roca', 'Lia', 'Club X');
        $this->competitor($event, $category, 'Mora', 'Teo', 'Club X');
        $this->actingAs($user)->post(route('panel.events.bibs', $event));
        $registration = $competitor->registrations()->firstOrFail();

        $this->actingAs($user)->get(route('panel.registrations.show', [$event, $registration]))->assertSee('Número');
        $csv = $this->actingAs($user)->get(route('panel.exports.registrations', $event))->streamedContent();
        $this->assertStringContainsString('N.º;Categoría', $csv);
        $this->assertMatchesRegularExpression('/\n2;.*;Roca;Lia;"Club X"/u', $csv);

        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());
        $this->actingAs($user)->get(route('panel.brackets.show', [$event, $category]))->assertSee('Nº 2')->assertSee('Nº 1');

        $this->actingAs(User::factory()->create())->post(route('panel.events.bibs', $event))->assertForbidden();
    }
}
