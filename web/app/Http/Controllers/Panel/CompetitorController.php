<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompetitorRequest;
use App\Models\Competitor;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitorController extends Controller
{
    public function edit(Event $event, Competitor $competitor): View
    {
        return view('panel.competitors.edit', ['event' => $event, 'competitor' => $competitor]);
    }

    public function update(CompetitorRequest $request, Event $event, Competitor $competitor): RedirectResponse
    {
        $competitor->update($request->safe()->except(['photo', 'remove_photo']));

        if ($request->hasFile('photo')) {
            $this->deletePhoto($competitor);
            $competitor->forceFill(['photo_path' => $request->file('photo')->store('competitor-photos', 'local')])->save();
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($competitor);
            $competitor->forceFill(['photo_path' => null])->save();
        }

        // Sin representante no puede quedar un consentimiento registrado.
        if (blank($competitor->guardian_name) && $competitor->guardian_consent_at !== null) {
            $competitor->forceFill(['guardian_consent_at' => null])->save();
        }

        return redirect()->route('panel.registrations.index', $event)->with('status', 'Datos actualizados.');
    }

    /** La foto es un dato privado: solo la sirve el panel, al propietario del evento. */
    public function photo(Event $event, Competitor $competitor): StreamedResponse
    {
        abort_unless($competitor->hasPhoto(), 404);

        return Storage::disk('local')->response($competitor->photo_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
    }

    private function deletePhoto(Competitor $competitor): void
    {
        if (filled($competitor->photo_path)) {
            Storage::disk('local')->delete($competitor->photo_path);
        }
    }
}
