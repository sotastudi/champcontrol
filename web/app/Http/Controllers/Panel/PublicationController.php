<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

class PublicationController extends Controller
{
    public function toggle(Event $event, Category $category): RedirectResponse
    {
        if (! $category->is_published && ! $category->bouts()->exists()) {
            return back()->withErrors(['error' => 'Genera el cuadro antes de publicar la categoría.']);
        }

        // is_published no está en #[Fillable] a propósito: solo cambia por esta acción.
        $category->forceFill(['is_published' => ! $category->is_published])->save();

        return back()->with('status', $category->is_published ? 'Categoría publicada.' : 'Categoría oculta.');
    }
}
