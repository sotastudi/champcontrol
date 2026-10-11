<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listed_event_is_visible_and_on_home(): void
    {
        $event = Event::factory()->create(['is_public' => true, 'status' => EventStatus::RegistrationOpen, 'name' => 'Copa Visible']);

        $this->get(route('home'))->assertSee('Copa Visible');
        $this->get(route('public.events.show', $event))->assertOk()->assertSee('Copa Visible');
    }

    public function test_private_and_draft_events_are_not_public(): void
    {
        $private = Event::factory()->create(['is_public' => false, 'status' => EventStatus::InProgress, 'name' => 'Copa Privada']);
        $draft = Event::factory()->create(['is_public' => true, 'status' => EventStatus::Draft, 'name' => 'Copa Borrador']);

        $this->get(route('home'))->assertDontSee('Copa Privada')->assertDontSee('Copa Borrador');
        $this->get(route('public.events.show', $private))->assertNotFound();
        $this->get(route('public.events.show', $draft))->assertNotFound();
    }

    public function test_registration_window_limits_the_public_form(): void
    {
        $event = Event::factory()->create([
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
            'registration_opens_at' => '2026-11-01 10:00:00', 'registration_closes_at' => '2026-11-10 20:30:00',
        ]);

        foreach ([['2026-10-31 23:59:00', false], ['2026-11-01 10:00:00', true], ['2026-11-10 20:30:00', true], ['2026-11-10 20:31:00', false]] as [$now, $open]) {
            $this->travelTo(Carbon::parse($now));
            $this->assertSame($open, $event->fresh()->acceptsPublicRegistrations(), $now);
            $this->get(route('public.registration.create', $event))->assertStatus($open ? 200 : 404);
        }
    }

    public function test_without_dates_the_window_is_always_open_and_one_sided_windows_work(): void
    {
        $base = ['is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen];
        $this->travelTo(Carbon::parse('2026-11-05 12:00:00'));

        $this->assertTrue(Event::factory()->create($base)->acceptsPublicRegistrations());
        $this->assertTrue(Event::factory()->create($base + ['registration_opens_at' => '2026-11-01 00:00:00'])->acceptsPublicRegistrations());
        $this->assertFalse(Event::factory()->create($base + ['registration_opens_at' => '2026-11-06 00:00:00'])->acceptsPublicRegistrations());
        $this->assertFalse(Event::factory()->create($base + ['registration_closes_at' => '2026-11-04 00:00:00'])->acceptsPublicRegistrations());
    }

    public function test_public_page_shows_the_registration_dates(): void
    {
        $event = Event::factory()->create([
            'is_public' => true, 'status' => EventStatus::RegistrationOpen,
            'registration_opens_at' => '2026-11-01 10:00:00', 'registration_closes_at' => '2026-11-10 20:30:00',
        ]);

        $this->get(route('public.events.show', $event))->assertSee('1 de noviembre de 2026, 10:00')->assertSee('10 de noviembre de 2026, 20:30');
    }
}
