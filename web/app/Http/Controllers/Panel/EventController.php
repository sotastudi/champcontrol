<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Registrations\BibNumberAssigner;
use App\Registrations\RegistrationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $event = $request->user()->events()->create($request->safe()->except(['poster', 'remove_poster', 'rules_pdf', 'remove_rules_pdf']));
        $this->savePoster($request, $event);

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
        $event->update($request->safe()->except(['poster', 'remove_poster', 'rules_pdf', 'remove_rules_pdf']));
        $this->savePoster($request, $event);

        return redirect()->route('panel.events.show', $event)->with('status', 'Evento actualizado.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->deletePoster($event);
        $this->deleteRulesPdf($event);
        $event->competitors()->whereNotNull('photo_path')->pluck('photo_path')->each(fn ($path) => Storage::disk('local')->delete($path));
        $event->delete();

        return redirect()->route('panel.events.index')->with('status', 'Evento eliminado.');
    }

    /** El póster se guarda en el disco privado y se sirve por una ruta propia (solo si el evento es público). */
    public function poster(Event $event): StreamedResponse
    {
        abort_unless($event->hasPoster(), 404);

        return Storage::disk('local')->response($event->poster_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function savePoster(EventRequest $request, Event $event): void
    {
        if ($request->hasFile('poster')) {
            $this->deletePoster($event);
            $event->forceFill(['poster_path' => $request->file('poster')->store('posters', 'local')])->save();
        } elseif ($request->boolean('remove_poster')) {
            $this->deletePoster($event);
            $event->forceFill(['poster_path' => null])->save();
        }

        if ($request->hasFile('rules_pdf')) {
            $this->deleteRulesPdf($event);
            $file = $request->file('rules_pdf');
            $event->forceFill([
                'rules_pdf_path' => $file->store('rules', 'local'),
                'rules_pdf_name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 100, '').'.pdf',
            ])->save();
        } elseif ($request->boolean('remove_rules_pdf')) {
            $this->deleteRulesPdf($event);
            $event->forceFill(['rules_pdf_path' => null, 'rules_pdf_name' => null])->save();
        }
    }

    public function assignBibs(Event $event, BibNumberAssigner $bibs): RedirectResponse
    {
        try {
            $count = $bibs->assign($event);
        } catch (RegistrationException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', "Números de competidor asignados a {$count} participantes (agrupados por club).");
    }

    public function rules(Event $event): StreamedResponse
    {
        abort_unless($event->hasRulesPdf(), 404);

        return Storage::disk('local')->download($event->rules_pdf_path, $event->rules_pdf_name ?: 'normas.pdf', ['X-Content-Type-Options' => 'nosniff']);
    }

    private function deleteRulesPdf(Event $event): void
    {
        if (filled($event->rules_pdf_path)) {
            Storage::disk('local')->delete($event->rules_pdf_path);
        }
    }

    private function deletePoster(Event $event): void
    {
        if (filled($event->poster_path)) {
            Storage::disk('local')->delete($event->poster_path);
        }
    }
}
