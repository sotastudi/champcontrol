<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScoreBoutRequest;
use App\Models\Bout;
use App\Models\Event;
use App\Tournament\ScoreRecorder;
use App\Tournament\TatamiBoard;
use App\Tournament\TournamentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vista de un tatami: el combate actual y su puntuación, para el propietario y los controladores de ese tatami (policy `operate`). */
class TatamiController extends Controller
{
    public function show(Request $request, Event $event, int $tatami, TatamiBoard $board): View
    {
        abort_unless($tatami >= 1 && $tatami <= $event->tatamiCount(), 404);

        $state = $board->for($event)[$tatami - 1];
        $sequence = $board->sequence($event, $tatami);

        // Se puede volver a un combate anterior (?combate=ID) para revisarlo; por defecto, el actual (o el último si ya terminaron todos).
        $selected = $request->filled('combate') ? $sequence->firstWhere('id', (int) $request->query('combate')) : null;
        abort_if($request->filled('combate') && ! $selected, 404);
        $bout = $selected ?? $state['now'] ?? $sequence->last();

        $index = $bout ? $sequence->search(fn ($b) => $b->id === $bout->id) : false;
        $currentIndex = $state['now'] ? $sequence->search(fn ($b) => $b->id === $state['now']->id) : false;

        return view('panel.tatamis.show', [
            'event' => $event,
            'tatami' => $state,
            'sequence' => $sequence,
            'bout' => $bout,
            'isCurrent' => $bout && $state['now'] && $bout->id === $state['now']->id,
            // Anterior: siempre que haya; siguiente: solo hasta llegar al combate actual.
            'previous' => $index !== false && $index > 0 ? $sequence[$index - 1] : null,
            'following' => $index !== false && ($currentIndex === false ? $index < $sequence->count() - 1 : $index < $currentIndex) ? $sequence[$index + 1] : null,
        ]);
    }

    public function score(ScoreBoutRequest $request, Event $event, int $tatami, Bout $bout, TatamiBoard $board, ScoreRecorder $scores): RedirectResponse|JsonResponse
    {
        abort_unless($tatami >= 1 && $tatami <= $event->tatamiCount(), 404);
        $bout->loadMissing('category');
        abort_unless((int) $bout->category->event_id === (int) $event->id && (int) $bout->category->tatami === $tatami, 404);

        // Solo el combate actual o uno ya puntuado (para corregirlo tras «Desbloquear»); nunca uno futuro.
        $current = $board->for($event)[$tatami - 1]['now'];
        $reviewable = $bout->isCompleted() && $board->sequence($event, $tatami)->contains('id', $bout->id);
        if (! $reviewable && (! $current || $current->id !== $bout->id)) {
            $message = 'Solo se puede puntuar el combate actual del tatami o corregir uno ya puntuado.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 422)
                : back()->withErrors(['error' => $message]);
        }

        try {
            $winner = $scores->record($bout, $request->scoreData());
        } catch (TournamentException $e) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $message = $winner === null
            ? 'Puntuación guardada. No hay ganador deducible (empate): lo resuelve el organizador.'
            : 'Puntuación guardada y ganador registrado.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'winner' => $winner, 'message' => $message]) : back()->with('status', $message);
    }
}
