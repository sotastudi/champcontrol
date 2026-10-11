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
}
