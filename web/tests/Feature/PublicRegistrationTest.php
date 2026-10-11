<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Category;
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

    public function test_minors_are_rejected_until_consent_flow_exists(): void
    {
        [$event, $category] = $this->openEvent();

        // 17 años y 364 días el día del evento.
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-21']))
            ->assertSessionHasErrors('birth_date');
        // 18 exactos el día del evento: permitido.
        $this->post(route('public.registration.store', $event), $this->data($category, ['birth_date' => '2008-11-20']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('registrations', 1);
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
