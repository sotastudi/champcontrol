<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\CategoryResetter;
use App\Tournament\ResultRecorder;
use App\Tournament\TournamentException;
use Illuminate\Http\RedirectResponse;

/** Solo el propietario del evento reinicia resultados (de un combate o de toda una categoría). */
class ResultResetController extends Controller
{
    public function bout(Event $event, Category $category, Bout $bout, ResultRecorder $results): RedirectResponse
    {
        try {
            $results->clear($bout);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Resultado del combate '.($bout->number ?? '').' reiniciado.');
    }

    public function category(Event $event, Category $category, CategoryResetter $resetter): RedirectResponse
    {
        try {
            $count = $resetter->reset($category);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', "Resultados de «{$category->name}» reiniciados ({$count} ".($count === 1 ? 'combate' : 'combates').').');
    }
}
