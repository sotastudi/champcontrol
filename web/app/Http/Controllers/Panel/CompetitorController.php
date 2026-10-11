<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompetitorRequest;
use App\Models\Competitor;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompetitorController extends Controller
{
    public function edit(Event $event, Competitor $competitor): View
    {
        return view('panel.competitors.edit', ['event' => $event, 'competitor' => $competitor]);
    }

    public function update(CompetitorRequest $request, Event $event, Competitor $competitor): RedirectResponse
    {
        $competitor->update($request->validated());

        return redirect()->route('panel.registrations.index', $event)->with('status', 'Datos actualizados.');
    }
}
