<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScoreBoutRequest;
use App\Models\Bout;
use App\Models\Event;
use App\Tournament\ScoreRecorder;
use App\Tournament\TatamiBoard;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Vista de un tatami: el combate actual y su puntuación, para el propietario y los controladores de ese tatami (policy `operate`). */
class TatamiController extends Controller
{
    public function show(Event $event, int $tatami, TatamiBoard $board): View
    {
        abort_unless($tatami >= 1 && $tatami <= $event->tatamiCount(), 404);

        return view('panel.tatamis.show', ['event' => $event, 'tatami' => $board->for($event)[$tatami - 1]]);
    }

    public function score(ScoreBoutRequest $request, Event $event, int $tatami, Bout $bout, TatamiBoard $board, ScoreRecorder $scores): RedirectResponse
    {
        abort_unless($tatami >= 1 && $tatami <= $event->tatamiCount(), 404);
        $bout->loadMissing('category');
        abort_unless((int) $bout->category->event_id === (int) $event->id && (int) $bout->category->tatami === $tatami, 404);

        $current = $board->for($event)[$tatami - 1]['now'];
        if (! $current || $current->id !== $bout->id) {
            return back()->withErrors(['error' => 'Solo se puede puntuar el combate actual del tatami.']);
        }

        try {
            $winner = $scores->record($bout, $request->scoreData());
        } catch (TournamentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', $winner === null
            ? 'Puntuación guardada. No hay ganador deducible (empate): lo resuelve el organizador.'
            : 'Puntuación guardada y ganador registrado.');
    }
}
