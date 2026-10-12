<?php

namespace Tests;

use App\Enums\ClubStatus;
use App\Models\Event;
use App\Models\EventClub;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Simula que el visitante ya introdujo el código de acceso a los resultados del evento. */
    protected function seeResults(Event $event): static
    {
        return $this->withSession(['event_access' => [$event->id => $event->accessToken()]]);
    }

    /** Cuenta de club con el registro en el evento en el estado indicado (por defecto, aprobado). */
    protected function clubFor(Event $event, ClubStatus $status = ClubStatus::Approved, string $name = 'Club Sur'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['role' => 'club'])->save();
        EventClub::create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $status]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
