<?php

namespace Tests\Feature;

use App\Enums\ClubStatus;
use App\Models\Category;
use App\Models\ClubAthlete;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubAthleteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'first_name' => 'Lucía', 'last_name' => 'Martín Gil', 'birth_date' => '2000-03-10', 'sex' => 'female', 'level' => '2º kyu', 'weight' => 55,
        ], $o);
    }

    private function openEvent(): array
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);

        return [$event, $this->clubFor($event)];
    }

    public function test_club_manages_its_roster_and_the_nav_link_is_shown(): void
    {
        [, $club] = $this->openEvent();

        $this->actingAs($club)->get('/')->assertSee('Mis competidores');
        $this->get(route('panel.athletes.index'))->assertOk();
        $this->post(route('panel.athletes.store'), $this->payload())->assertSessionHasNoErrors();
        $athlete = $club->athletes()->firstOrFail();
        $this->post(route('panel.athletes.store'), $this->payload(['last_name' => 'martin gil']))->assertSessionHasErrors('first_name');

        $this->put(route('panel.athletes.update', $athlete), $this->payload(['weight' => 58]))->assertSessionHasNoErrors();
        $this->assertSame(58.0, $athlete->fresh()->weight);
        $this->delete(route('panel.athletes.destroy', $athlete));
        $this->assertSame(0, $club->athletes()->count());
    }

    public function test_only_clubs_and_only_their_own_athletes(): void
    {
        [$event, $club] = $this->openEvent();
        $other = $this->clubFor($event, name: 'Otro Club');
        $athlete = $club->athletes()->create($this->payload());

        $this->actingAs(User::factory()->create())->get(route('panel.athletes.index'))->assertForbidden();
        $this->actingAs($other)->get(route('panel.athletes.edit', $athlete))->assertNotFound();
        $this->put(route('panel.athletes.update', $athlete), $this->payload())->assertNotFound();
        $this->delete(route('panel.athletes.destroy', $athlete))->assertNotFound();
    }

    public function test_roster_athlete_is_added_to_a_matching_category_with_one_click(): void
    {
        [$event, $club] = $this->openEvent();
        $senior = Category::factory()->for($event)->create(['name' => 'Senior femenina', 'sex' => 'female', 'min_age' => 18]);
        $men = Category::factory()->for($event)->create(['name' => 'Senior masculina', 'sex' => 'male', 'min_age' => 18]);
        $athlete = $club->athletes()->create($this->payload());

        $this->actingAs($club)->get(route('public.registration.create', $event))
            ->assertSee('Agregar a Senior femenina')->assertDontSee('Agregar a Senior masculina')
            ->assertSee('data-bs-target="#why-'.$athlete->id.'-'.$men->id.'"', false)->assertSee('no disponible, sexo distinto al de la categoría');

        $this->post(route('public.registration.roster', $event), ['athlete_id' => $athlete->id, 'category_id' => $senior->id])->assertSessionHasNoErrors();
        $registration = Registration::firstOrFail();
        $this->assertSame('pending', $registration->status->value);
        $this->assertSame('Club Sur', $registration->competitor->club);
        $this->get(route('public.registration.create', $event))->assertSee('Inscrito en Senior femenina');

        // Requisitos: otra categoría que no cumple y repetir.
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $athlete->id, 'category_id' => $men->id])->assertSessionHasErrors('error');
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $athlete->id, 'category_id' => $senior->id])->assertSessionHasErrors('error');
        $this->assertSame(1, Registration::count());
        $this->assertSame(1, Competitor::count());
    }

    public function test_the_same_athlete_in_two_categories_reuses_the_event_competitor(): void
    {
        [$event, $club] = $this->openEvent();
        $a = Category::factory()->for($event)->create(['name' => 'Senior A', 'min_age' => 18]);
        $b = Category::factory()->for($event)->create(['name' => 'Senior B', 'min_age' => 18]);
        $athlete = $club->athletes()->create($this->payload());

        $this->actingAs($club);
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $athlete->id, 'category_id' => $a->id]);
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $athlete->id, 'category_id' => $b->id]);

        $this->assertSame(2, Registration::count());
        $this->assertSame(1, Competitor::count());
    }

    public function test_minor_needs_guardian_data_and_consent_in_the_roster(): void
    {
        [$event, $club] = $this->openEvent();
        $category = Category::factory()->for($event)->create(['name' => 'Infantil']);
        $minor = $club->athletes()->create($this->payload(['first_name' => 'Pau', 'birth_date' => '2012-05-01']));
        $this->actingAs($club);

        $this->post(route('public.registration.roster', $event), ['athlete_id' => $minor->id, 'category_id' => $category->id])->assertSessionHasErrors('error');
        $this->assertSame(0, Registration::count());

        $this->put(route('panel.athletes.update', $minor), $this->payload(['first_name' => 'Pau', 'birth_date' => '2012-05-01', 'guardian_name' => 'Marta', 'guardian_contact' => 'marta@familia.test', 'guardian_consent' => '1']))->assertSessionHasNoErrors();
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $minor->id, 'category_id' => $category->id])->assertSessionHasNoErrors();

        $competitor = Competitor::firstOrFail();
        $this->assertSame('Marta', $competitor->guardian_name);
        $this->assertNotNull($competitor->guardian_consent_at);

        // El consentimiento exige los datos del representante.
        $this->put(route('panel.athletes.update', $minor), $this->payload(['birth_date' => '2012-05-01', 'guardian_consent' => '1']))->assertSessionHasErrors('guardian_name');
    }

    public function test_only_approved_clubs_can_use_the_roster_and_not_another_clubs_athletes(): void
    {
        [$event, $club] = $this->openEvent();
        $category = Category::factory()->for($event)->create(['min_age' => 18]);
        $pending = $this->clubFor($event, ClubStatus::Pending, 'Pendiente');
        $mine = $pending->athletes()->create($this->payload());
        $theirs = $club->athletes()->create($this->payload(['first_name' => 'Otra']));

        $this->actingAs($pending)->post(route('public.registration.roster', $event), ['athlete_id' => $mine->id, 'category_id' => $category->id])->assertForbidden();
        $this->actingAs($club)->post(route('public.registration.roster', $event), ['athlete_id' => $mine->id, 'category_id' => $category->id])->assertNotFound();
        $this->post(route('public.registration.roster', $event), ['athlete_id' => $theirs->id, 'category_id' => $category->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Registration::count());
    }

    public function test_roster_form_hides_the_guardian_section_for_adults_and_discards_their_guardian_data(): void
    {
        [, $club] = $this->openEvent();
        $this->actingAs($club);

        $this->get(route('panel.athletes.index'))->assertSee('data-athlete-form', false)->assertSee('data-guardian-section', false);

        $this->post(route('panel.athletes.store'), $this->payload(['guardian_name' => 'Marta', 'guardian_contact' => 'm@x.test', 'guardian_consent' => '1']))->assertSessionHasNoErrors();
        $adult = $club->athletes()->firstOrFail();
        $this->assertNull($adult->guardian_name);
        $this->assertNull($adult->guardian_contact);
        $this->assertNull($adult->guardian_consent_at);
    }

    public function test_reasons_are_visible_when_the_athlete_fits_no_category(): void
    {
        [$event, $club] = $this->openEvent();
        Category::factory()->for($event)->create(['name' => 'Infantil', 'min_age' => 8, 'max_age' => 12]);
        Category::factory()->for($event)->create(['name' => 'Senior masculina', 'sex' => 'male', 'min_age' => 18]);
        $club->athletes()->create($this->payload());

        $this->actingAs($club)->get(route('public.registration.create', $event))
            ->assertSee('No cumple los requisitos de ninguna categoría disponible (pulsa en cada una para ver el motivo)')
            ->assertSee('no disponible, edad')->assertSee('superior al máximo (12)')
            ->assertSee('no disponible, sexo distinto al de la categoría')
            ->assertDontSee('pasa el ratón')->assertDontSee('disabled');
    }
}
