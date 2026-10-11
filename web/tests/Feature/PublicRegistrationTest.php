<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function data(Category $category, array $o = []): array
    {
        return array_merge([
            'first_name' => 'Lucía', 'last_name' => 'Martín Gil', 'club' => 'Club Sur',
            'birth_date' => '2000-03-10', 'sex' => 'female', 'weight' => 55,
            'email' => 'lucia@example.com', 'category_id' => $category->id,
        ], $o);
    }

    private function openEvent(): array
    {
        $event = Event::factory()->registrationOpen()->create(['starts_on' => '2026-11-20']);

        return [$event, Category::factory()->for($event)->create()];
    }

    public function test_visitor_registers_without_account_and_is_always_pending(): void
    {
        [$event, $category] = $this->openEvent();

        $this->get(route('public.registration.create', $event))->assertOk()->assertSee($category->name);

        // `status=confirmed` inyectado debe ignorarse.
        $this->post(route('public.registration.store', $event), $this->data($category, ['status' => 'confirmed']))
            ->assertRedirect(route('public.events.show', $event));

        $this->assertSame(RegistrationStatus::Pending, Registration::firstOrFail()->status);
    }

    public function test_form_is_not_available_when_event_is_private_closed_or_disabled(): void
    {
        [$event, $category] = $this->openEvent();

        foreach ([
            ['is_public' => false],
            ['status' => EventStatus::RegistrationClosed],
            ['registration_form_enabled' => false],
            ['status' => EventStatus::Draft],
        ] as $change) {
            $event->update($change);
            $this->get(route('public.registration.create', $event))->assertNotFound();
            $this->post(route('public.registration.store', $event), $this->data($category))->assertNotFound();
            $event->refresh()->forceFill(['is_public' => true, 'status' => EventStatus::RegistrationOpen, 'registration_form_enabled' => true])->save();
        }

        $this->assertDatabaseCount('registrations', 0);
    }

    private function guardian(array $o = []): array
    {
        return array_merge([
            'guardian_name' => 'Marta Gil Pérez', 'guardian_contact' => 'marta@familia.test', 'guardian_consent' => '1',
        ], $o);
    }

    public function test_minor_with_guardian_data_and_consent_is_accepted_as_pending(): void
    {
        [$event, $category] = $this->openEvent();

        // 17 años y 364 días el día del evento.
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21'] + $this->guardian()))
            ->assertSessionHasNoErrors()->assertRedirect(route('public.events.show', $event));

        $competitor = Competitor::firstOrFail();
        $this->assertSame('Marta Gil Pérez', $competitor->guardian_name);
        $this->assertSame('marta@familia.test', $competitor->guardian_contact);
        $this->assertNotNull($competitor->guardian_consent_at);
        $this->assertSame(RegistrationStatus::Pending, Registration::firstOrFail()->status);
    }

    public function test_minor_without_guardian_data_or_consent_is_rejected_and_nothing_is_saved(): void
    {
        [$event, $category] = $this->openEvent();

        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21']))
            ->assertSessionHasErrors(['guardian_name', 'guardian_contact', 'guardian_consent']);
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21'] + $this->guardian(['guardian_consent' => '0'])))
            ->assertSessionHasErrors(['guardian_consent'])->assertSessionDoesntHaveErrors(['guardian_name', 'guardian_contact']);

        $this->assertDatabaseCount('competitors', 0);
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_exactly_eighteen_on_event_day_is_adult_and_guardian_data_is_discarded(): void
    {
        [$event, $category] = $this->openEvent();

        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-20']))
            ->assertSessionHasNoErrors();
        $this->post(route('public.registration.store', $event), $this->data($category, ['first_name' => 'Otra', 'birth_date' => '2008-11-20'] + $this->guardian()))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('registrations', 2);
        $this->assertSame(0, Competitor::whereNotNull('guardian_name')->orWhereNotNull('guardian_contact')->orWhereNotNull('guardian_consent_at')->count());
    }

    public function test_adult_sending_a_free_form_consent_value_is_accepted_and_nothing_guardian_is_stored(): void
    {
        [$event, $category] = $this->openEvent();

        $this->post(route('public.registration.store', $event), $this->data($category, $this->guardian(['guardian_consent' => 'on'])))
            ->assertSessionHasNoErrors();

        $competitor = Competitor::firstOrFail();
        $this->assertNull($competitor->guardian_name);
        $this->assertNull($competitor->guardian_contact);
        $this->assertNull($competitor->guardian_consent_at);
    }

    public function test_minor_can_consent_with_yes_or_on(): void
    {
        [$event, $category] = $this->openEvent();

        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21'] + $this->guardian(['guardian_consent' => 'on'])))
            ->assertSessionHasNoErrors();
        $this->assertNotNull(Competitor::firstOrFail()->guardian_consent_at);
    }

    public function test_invalid_birth_date_is_a_validation_error_not_a_server_error(): void
    {
        [$event, $category] = $this->openEvent();

        foreach (['no-es-fecha', '2999-01-01', ['x']] as $value) {
            $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => $value] + $this->guardian()))
                ->assertSessionHasErrors('birth_date');
        }
        $this->assertDatabaseCount('competitors', 0);
    }

    public function test_form_shows_guardian_section_in_spanish_and_no_adults_only_notice(): void
    {
        [$event] = $this->openEvent();

        $this->get(route('public.registration.create', $event))->assertOk()
            ->assertSee('Datos del representante legal')
            ->assertSee('Declaro ser el padre, madre o representante legal del menor')
            ->assertSee('Los datos del representante no se publican.')
            ->assertDontSee('Solo mayores');
    }

    public function test_guardian_validation_messages_are_in_spanish(): void
    {
        [$event, $category] = $this->openEvent();

        $this->from(route('public.registration.create', $event))->followingRedirects()
            ->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21']))
            ->assertSee('Debes indicar el nombre del representante')
            ->assertSee('Debes aceptar')
            ->assertDontSee('validation.');
    }

    public function test_category_of_another_event_and_duplicates_are_rejected(): void
    {
        [$event, $category] = $this->openEvent();
        $foreign = Category::factory()->create();

        $this->post(route('public.registration.store', $event), $this->data($foreign))->assertSessionHasErrors('category_id');

        $this->post(route('public.registration.store', $event), $this->data($category));
        $response = $this->from(route('public.registration.create', $event))
            ->followingRedirects()
            ->post(route('public.registration.store', $event), $this->data($category))
            ->assertDontSee('ya está inscrito');
        $this->assertSame(1, substr_count($response->getContent(), 'No hemos podido completar la inscripción'));
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_invalid_payload_to_unavailable_event_is_404_not_validation_redirect(): void
    {
        foreach ([
            ['is_public' => false],
            ['status' => EventStatus::Draft],
            ['status' => EventStatus::RegistrationClosed],
            ['registration_form_enabled' => false],
        ] as $change) {
            $event = Event::factory()->registrationOpen()->create($change);
            Category::factory()->for($event)->create();

            $this->post(route('public.registration.store', $event), [])->assertNotFound();
        }

        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('competitors', 0);
    }
}
