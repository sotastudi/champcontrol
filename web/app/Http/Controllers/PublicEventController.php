<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless($event->isListed(), 404);

        return view('public.events.show', ['event' => $event]);
    }
}
