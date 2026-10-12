<?php

namespace Tests\Feature;

use App\Enums\ClubStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventClub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Category $category): array
    {
        return [
            'first_name' => 'Lucía', 'last_name' => 'Martín Gil', 'birth_date' => '2000-03-10', 'sex' => 'female',
            'level' => '2º kyu', 'category_id' => $category->id,
        ];
    }

    public function test_a_club_account_can_be_created(): void
    {
        $this->post('/registro-club', [
            'name' => 'Dojo Norte', 'email' => 'norte@example.com', 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertTrue(User::firstWhere('email', 'norte@example.com')->isClubAccount());
    }

    public function test_guests_and_non_club_accounts_cannot_register_competitors(): void
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();

        $this->get(route('public.registration.create', $event))->assertOk()->assertSee('No se admiten inscripciones a título personal')->assertDontSee('name="first_name"', false);
        $this->post(route('public.registration.store', $event), $this->payload($category))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->post(route('public.registration.store', $event), $this->payload($category))->assertForbidden();
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_club_must_register_in_the_event_and_be_approved_before_registering_competitors(): void
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();
        $club = User::factory()->create(['name' => 'Dojo Norte']);
        $club->forceFill(['role' => 'club'])->save();

        $this->actingAs($club)->get(route('public.registration.create', $event))->assertSee('Registrar mi club en este campeonato');
        $this->post(route('public.registration.store', $event), $this->payload($category))->assertForbidden();

        $this->post(route('public.clubs.store', $event))->assertRedirect(route('public.registration.create', $event));
        $this->assertSame(ClubStatus::Pending, EventClub::firstOrFail()->status);
        $this->get(route('public.registration.create', $event))->assertSee('pendiente de aprobación');
        $this->post(route('public.registration.store', $event), $this->payload($category))->assertForbidden();

        EventClub::firstOrFail()->update(['status' => ClubStatus::Approved]);
        $this->get(route('public.registration.create', $event))->assertSee('name="first_name"', false);
        $this->post(route('public.registration.store', $event), $this->payload($category))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('competitors', ['first_name' => 'Lucía', 'club' => 'Dojo Norte']);
    }

    public function test_rejected_club_cannot_register_competitors(): void
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($event)->create();

        $this->actingAs($this->clubFor($event, ClubStatus::Rejected));
        $this->get(route('public.registration.create', $event))->assertSee('ha rechazado');
        $this->post(route('public.registration.store', $event), $this->payload($category))->assertForbidden();
    }

    public function test_approval_in_one_event_does_not_apply_to_another(): void
    {
        $one = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);
        $two = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);
        $category = Category::factory()->for($two)->create();

        $this->actingAs($this->clubFor($one))->post(route('public.registration.store', $two), $this->payload($category))->assertForbidden();
    }

    public function test_organizer_approves_and_rejects_clubs_of_their_own_event_only(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->registrationOpen()->create();
        $club = EventClub::firstWhere('id', $this->clubFor($event, ClubStatus::Pending)->clubRegistrationFor($event)->id);

        $this->actingAs($owner)->get(route('panel.clubs.index', $event))->assertOk()->assertSee('Club Sur');
        $this->put(route('panel.clubs.update', [$event, $club]), ['status' => 'approved'])->assertRedirect();
        $this->assertSame(ClubStatus::Approved, $club->fresh()->status);
        $this->put(route('panel.clubs.update', [$event, $club]), ['status' => 'rejected']);
        $this->assertSame(ClubStatus::Rejected, $club->fresh()->status);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('panel.clubs.index', $event))->assertForbidden();
        $this->put(route('panel.clubs.update', [$event, $club]), ['status' => 'approved'])->assertForbidden();
    }

    public function test_club_accounts_cannot_create_events(): void
    {
        $event = Event::factory()->registrationOpen()->create();

        $this->actingAs($this->clubFor($event))->get(route('panel.events.create'))->assertForbidden();
        $this->get(route('panel.events.index'))->assertRedirect(route('home'));
    }

    public function test_organizer_creates_a_club_account_already_approved_in_the_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->registrationOpen()->create();

        $this->actingAs($owner)->post(route('panel.clubs.store', $event), [
            'name' => 'Dojo Este', 'email' => 'este@example.com', 'password' => 'clave-segura-1',
        ])->assertSessionHasNoErrors();

        $club = User::firstWhere('email', 'este@example.com');
        $this->assertTrue($club->isClubAccount());
        $this->assertSame($owner->id, (int) $club->created_by_user_id);
        $this->assertSame(ClubStatus::Approved, $club->clubRegistrationFor($event)->status);

        // El club ya puede entrar y ver el formulario de inscripción.
        auth()->logout();
        $this->post('/acceso', ['email' => 'este@example.com', 'password' => 'clave-segura-1'])->assertRedirect();
        $this->get(route('public.registration.create', $event))->assertSee('name="first_name"', false);
    }

    public function test_existing_club_account_is_only_approved_and_keeps_its_password(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->registrationOpen()->create();
        $other = Event::factory()->registrationOpen()->create();
        $club = $this->clubFor($other);
        $hash = $club->password;

        $this->actingAs($owner)->post(route('panel.clubs.store', $event), ['name' => 'Otro', 'email' => $club->email, 'password' => 'otra-clave-22'])
            ->assertSessionHasNoErrors();

        $this->assertSame($hash, $club->fresh()->password);
        $this->assertSame(ClubStatus::Approved, $club->clubRegistrationFor($event)->status);
    }

    public function test_club_creation_validations(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->registrationOpen()->create();
        $existing = $this->clubFor($event, name: 'Dojo Norte');
        $plain = User::factory()->create(['email' => 'persona@example.com']);

        $this->actingAs($owner);
        $this->post(route('panel.clubs.store', $event), ['name' => 'X', 'email' => $plain->email, 'password' => 'clave-segura-1'])->assertSessionHasErrors('email');
        $this->post(route('panel.clubs.store', $event), ['name' => 'Y', 'email' => 'nuevo@example.com'])->assertSessionHasErrors('password');
        $this->post(route('panel.clubs.store', $event), ['name' => 'Dojo Norte', 'email' => 'nuevo@example.com', 'password' => 'clave-segura-1'])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);

        $this->actingAs(User::factory()->create())->post(route('panel.clubs.store', $event), ['name' => 'Z', 'email' => 'z@example.com', 'password' => 'clave-segura-1'])->assertForbidden();
    }
}
