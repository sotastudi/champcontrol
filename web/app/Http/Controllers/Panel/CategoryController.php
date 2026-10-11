<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function create(Event $event): View
    {
        return view('panel.categories.create', ['event' => $event, 'defaultBronzeRule' => $event->defaultBronzeRule()->value]);
    }

    public function store(CategoryRequest $request, Event $event): RedirectResponse
    {
        $category = $event->categories()->create($request->validated());
        $category->placeInTatami();

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría creada.');
    }

    public function edit(Event $event, Category $category): View
    {
        return view('panel.categories.edit', compact('event', 'category'));
    }

    public function update(CategoryRequest $request, Event $event, Category $category): RedirectResponse
    {
        $category->update($request->validated());
        if ($category->wasChanged('tatami') || ($category->tatami !== null && $category->tatami_position === null)) {
            $category->placeInTatami();
        }

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría actualizada.');
    }

    public function destroy(Event $event, Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría eliminada.');
    }

    /** Sube o baja una categoría dentro de su tatami (intercambia su posición con la vecina). */
    public function move(Request $request, Event $event, Category $category): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        abort_if($category->tatami === null, 404);

        DB::transaction(function () use ($event, $category, $data) {
            // Normaliza 1..n por si hay huecos o repetidos y luego intercambia con la vecina.
            $siblings = $event->categories()->where('tatami', $category->tatami)->orderBy('tatami_position')->orderBy('id')->lockForUpdate()->get()->values();
            foreach ($siblings as $i => $sibling) {
                $sibling->forceFill(['tatami_position' => $i + 1])->save();
            }

            $index = $siblings->search(fn (Category $c) => $c->id === $category->id);
            $swapWith = $data['direction'] === 'up' ? $index - 1 : $index + 1;
            if (isset($siblings[$swapWith])) {
                [$a, $b] = [$siblings[$index], $siblings[$swapWith]];
                $a->forceFill(['tatami_position' => $swapWith + 1])->save();
                $b->forceFill(['tatami_position' => $index + 1])->save();
            }
        });

        return back()->with('status', 'Orden del tatami actualizado.');
    }

    /** Fija el orden completo de las categorías de un tatami (arrastrar y soltar). */
    public function reorder(Request $request, Event $event, int $tatami): RedirectResponse|JsonResponse
    {
        abort_unless($tatami >= 1 && $tatami <= $event->tatamiCount(), 404);
        $data = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['integer']]);

        $categories = $event->categories()->where('tatami', $tatami)->get()->keyBy('id');
        $order = array_map('intval', $data['order']);
        if (count($order) !== $categories->count() || array_diff($order, $categories->keys()->all()) || count(array_unique($order)) !== count($order)) {
            $error = 'El orden enviado no coincide con las categorías de este tatami.';

            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $error], 422) : back()->withErrors(['error' => $error]);
        }

        DB::transaction(function () use ($order, $categories) {
            foreach ($order as $i => $id) {
                $categories[$id]->forceFill(['tatami_position' => $i + 1])->save();
            }
        });

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Orden del tatami actualizado.');
    }
}
