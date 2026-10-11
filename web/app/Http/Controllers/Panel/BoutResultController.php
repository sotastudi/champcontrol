<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoutResultController extends Controller
{
    public function update(Request $request, Event $event, Category $category, Bout $bout, ResultRecorder $recorder): RedirectResponse
    {
        $data = $request->validate(['winner_id' => ['required', 'integer', 'min:1']]);

        try {
            $recorder->record($bout, (int) $data['winner_id']);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Resultado registrado.');
    }
}
