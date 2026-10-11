<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicEventController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless($event->isListed(), 404);

        return view('public.events.show', [
            'event' => $event,
            'categories' => $event->categories()->where('is_published', true)->orderBy('name')->get(),
        ]);
    }

    public function poster(Event $event): StreamedResponse
    {
        abort_unless($event->isListed() && $event->hasPoster(), 404);

        return Storage::disk('local')->response($event->poster_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600']);
    }
}
