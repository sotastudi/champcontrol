<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function create(Event $event): View
    {
        return view('panel.categories.create', ['event' => $event, 'defaultBronzeRule' => $event->defaultBronzeRule()->value]);
    }

    public function store(CategoryRequest $request, Event $event): RedirectResponse
    {
        $event->categories()->create($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría creada.');
    }

    public function edit(Event $event, Category $category): View
    {
        return view('panel.categories.edit', compact('event', 'category'));
    }

    public function update(CategoryRequest $request, Event $event, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría actualizada.');
    }

    public function destroy(Event $event, Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('panel.events.show', $event)->with('status', 'Categoría eliminada.');
    }
}
