<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Único punto de autorización: solo el propietario gestiona el evento y todo lo que cuelga de él. */
    public function manage(User $user, Event $event): bool
    {
        return (int) $event->user_id === (int) $user->getKey();
    }

    /** Puntuar el combate actual de un tatami: el propietario del evento o un controlador asignado a ese tatami. */
    public function operate(User $user, Event $event, int|string $tatami): bool
    {
        return $this->manage($user, $event)
            || $event->operators()->where('user_id', $user->getKey())->where('tatami', (int) $tatami)->exists();
    }
}
