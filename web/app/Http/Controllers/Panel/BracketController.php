<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\BracketAdjuster;
use App\Tournament\BracketGenerator;
use App\Tournament\PodiumCalculator;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BracketController extends Controller
{
    public function show(Event $event, Category $category, PodiumCalculator $podium): View
    {
        $bouts = $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner'])->get();

        return view('panel.brackets.show', [
            'event' => $event,
            'category' => $category,
            'bouts' => $bouts,
            'placements' => $podium->for($category),
            'confirmedCount' => $category->registrations()->where('status', 'confirmed')->count(),
            'stale' => $category->bracketIsStale(),
            'hasResults' => $category->hasResults(),
        ]);
    }

    public function store(Event $event, Category $category, BracketGenerator $generator): RedirectResponse
    {
        try {
            $generator->generate($category);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('panel.brackets.show', [$event, $category])->with('status', 'Cuadro generado.');
    }

    public function swap(Request $request, Event $event, Category $category, BracketAdjuster $adjuster): RedirectResponse
    {
        $data = $request->validate([
            'registration_a' => ['required', 'integer'],
            'registration_b' => ['required', 'integer'],
        ]);

        try {
            $adjuster->swap($category, (int) $data['registration_a'], (int) $data['registration_b']);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Participantes intercambiados.');
    }
}
