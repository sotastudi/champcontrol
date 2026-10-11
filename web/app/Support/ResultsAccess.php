<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Request;

/** Quién puede ver los resultados públicos de un evento: quien introdujo su código (en la sesión) o su propietario/controladores. */
final class ResultsAccess
{
    public function allows(Request $request, Event $event): bool
    {
        $user = $request->user();
        if ($user && ($user->can('manage', $event) || $event->operators()->where('user_id', $user->getKey())->exists())) {
            return true;
        }

        $granted = $request->session()->get('event_access', []);

        return isset($granted[$event->id]) && hash_equals($event->accessToken(), (string) $granted[$event->id]);
    }

    public function grant(Request $request, Event $event): void
    {
        $request->session()->put('event_access.'.$event->id, $event->accessToken());
    }
}
