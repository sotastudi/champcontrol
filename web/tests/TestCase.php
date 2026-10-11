<?php

namespace Tests;

use App\Models\Event;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Simula que el visitante ya introdujo el código de acceso a los resultados del evento. */
    protected function seeResults(Event $event): static
    {
        return $this->withSession(['event_access' => [$event->id => $event->accessToken()]]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
