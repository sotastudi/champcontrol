<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\Competitor;
use App\Models\Event;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function create(Event $event): View
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        return view('public.registration', [
            'event' => $event,
            'categories' => $event->categories()->orderBy('name')->get(),
            'minAge' => config('champcontrol.public_form_min_age'),
        ]);
    }

    public function store(RegistrationRequest $request, Event $event, RegistrationService $service): RedirectResponse
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        // Solo se usan los campos validados: cualquier `status` enviado se descarta.
        $data = $request->validated();

        $competitor = new Competitor(['birth_date' => $data['birth_date']]);
        if ($competitor->ageOn($event->starts_on) < config('champcontrol.public_form_min_age')) {
            return back()->withInput()->withErrors([
                'birth_date' => 'Este formulario no admite menores de edad. Contacta con el organizador.',
            ]);
        }

        try {
            $service->register($event->categories()->findOrFail($data['category_id']), $data);
        } catch (RegistrationException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('public.events.show', $event)
            ->with('status', 'Solicitud recibida. El organizador confirmará tu inscripción.');
    }
}
