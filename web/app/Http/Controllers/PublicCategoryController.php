<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Tournament\PodiumCalculator;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Event $event, Category $category, PodiumCalculator $podium): View
    {
        abort_unless($event->isListed() && $category->is_published, 404);

        return view('public.categories.show', [
            'event' => $event,
            'category' => $category,
            'bouts' => $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner.competitor'])->get(),
            'placements' => $podium->for($category),
            'editable' => false,
        ]);
    }
}
