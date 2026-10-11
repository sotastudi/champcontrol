<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScoreBoutRequest;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\ScoreRecorder;
use App\Tournament\TournamentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class BoutScoreController extends Controller
{
    public function update(ScoreBoutRequest $request, Event $event, Category $category, Bout $bout, ScoreRecorder $scores): RedirectResponse|JsonResponse
    {
        try {
            $winner = $scores->record($bout, $request->scoreData());
        } catch (TournamentException $e) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $message = $winner === null
            ? 'Puntuación guardada. No hay ganador deducible (empate): resuélvelo con «Gana».'
            : 'Puntuación guardada y ganador registrado.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'winner' => $winner, 'message' => $message]) : back()->with('status', $message);
    }
}
