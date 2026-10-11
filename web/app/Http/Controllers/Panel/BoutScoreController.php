<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScoreBoutRequest;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\ScoreRecorder;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;

class BoutScoreController extends Controller
{
    public function update(ScoreBoutRequest $request, Event $event, Category $category, Bout $bout, ScoreRecorder $scores): RedirectResponse
    {
        try {
            $winner = $scores->record($bout, $request->scoreData());
        } catch (TournamentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', $winner === null
            ? 'Puntuación guardada. No hay ganador deducible (empate): resuélvelo con «Gana».'
            : 'Puntuación guardada y ganador registrado.');
    }
}
