<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        return view('panel.events.index', [
            'events' => $request->user()->events()->orderByDesc('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.events.create');
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $request->user()->events()->create($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Evento creado.');
    }

    public function show(Event $event): View
    {
        return view('panel.events.show', ['event' => $event->load(['categories' => fn ($q) => $q->orderBy('name')->withCount([
            'registrations as confirmed_count' => fn ($r) => $r->where('status', RegistrationStatus::Confirmed->value),
        ])])]);
    }

    public function edit(Event $event): View
    {
        return view('panel.events.edit', ['event' => $event]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Evento actualizado.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('panel.events.index')->with('status', 'Evento eliminado.');
    }
}
