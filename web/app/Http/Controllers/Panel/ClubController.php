<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ClubStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventClub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Clubes registrados en el evento: el organizador los aprueba o rechaza antes de que puedan inscribir competidores. */
class ClubController extends Controller
{
    public function index(Event $event): View
    {
        return view('panel.clubs.index', ['event' => $event, 'clubs' => $event->clubs()->with('user')->get()->sortBy(fn ($c) => mb_strtolower($c->user->name))->values()]);
    }

    public function update(Request $request, Event $event, EventClub $club): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ClubStatus::class)]]);

        $club->update($data);

        return back()->with('status', 'Club actualizado.');
    }
}
