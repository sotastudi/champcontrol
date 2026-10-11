<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JudgeVote;
use App\Http\Controllers\Controller;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\ScoreRecorder;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoutScoreController extends Controller
{
    public function update(Request $request, Event $event, Category $category, Bout $bout, ScoreRecorder $scores): RedirectResponse
    {
        $data = $request->validate([
            'a_wazari' => ['nullable', 'integer', 'min:0', 'max:2'],
            'b_wazari' => ['nullable', 'integer', 'min:0', 'max:2'],
            'a_ippon' => ['boolean'],
            'b_ippon' => ['boolean'],
            'a_warnings' => ['nullable', 'integer', 'min:0', 'max:9'],
            'b_warnings' => ['nullable', 'integer', 'min:0', 'max:9'],
            'abandoned_slot' => ['nullable', Rule::in(['a', 'b'])],
            'judge_votes' => ['nullable', 'array', 'max:9'],
            'judge_votes.*' => ['nullable', Rule::enum(JudgeVote::class)],
        ]);
        // judge_votes[i]: i = posición del juez; los que no votan no se envían.
        $votes = [];
        foreach ($data['judge_votes'] ?? [] as $i => $vote) {
            $votes[(int) $i] = $vote;
        }
        ksort($votes);
        $data['judge_votes'] = $votes === [] ? [] : array_map(fn ($i) => $votes[$i] ?? null, range(0, max(array_keys($votes))));

        try {
            $winner = $scores->record($bout, $data);
        } catch (TournamentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', $winner === null
            ? 'Puntuación guardada. No hay ganador deducible (empate): resuélvelo con «Gana».'
            : 'Puntuación guardada y ganador registrado.');
    }
}
