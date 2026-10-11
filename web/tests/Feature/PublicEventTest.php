<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
