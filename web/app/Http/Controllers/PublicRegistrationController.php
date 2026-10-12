<?php

namespace App\Http\Controllers;

use App\Enums\ClubStatus;
use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\EventClub;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    /** Formulario para clubes con el registro aprobado; el resto ve en qué paso está (iniciar sesión, registrar el club, esperar la aprobación). */
    public function create(Request $request, Event $event): View
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        $user = $request->user();
        $club = $user?->isClubAccount() ? $user->clubRegistrationFor($event) : null;

        if (! $club?->isApproved()) {
            return view('public.registration-club', ['event' => $event, 'user' => $user, 'club' => $club]);
        }

        return view('public.registration', [
            'event' => $event,
            'clubName' => $user->name,
            'categories' => $event->categories()->orderBy('name')->get(),
        ]);
    }

    /** El club pide participar en el campeonato; el organizador debe aprobarlo antes de que pueda inscribir. */
    public function joinClub(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);
        abort_unless($request->user()->isClubAccount(), 403);

        EventClub::firstOrCreate(['event_id' => $event->id, 'user_id' => $request->user()->id], ['status' => ClubStatus::Pending]);

        return redirect()->route('public.registration.create', $event)
            ->with('status', 'Solicitud enviada. El organizador debe aprobar tu club antes de que puedas inscribir competidores.');
    }

    public function store(PublicRegistrationRequest $request, Event $event, RegistrationService $service): RedirectResponse
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        // Solo se usan los campos validados: cualquier `status` enviado se descarta; el club es siempre el de la cuenta.
        $data = $request->validated();

        // Los datos del representante solo se guardan si el competidor es menor en la fecha del evento.
        if (! (new Competitor(['birth_date' => $data['birth_date']]))->isMinorOn($event->starts_on)) {
            $data = Arr::except($data, ['guardian_name', 'guardian_contact', 'guardian_consent']);
        }

        try {
            $service->register($event->categories()->findOrFail($data['category_id']), $data);
        } catch (RegistrationException) {
            // Mensaje neutro: no revelar si el competidor ya consta como inscrito.
            return back()->withInput()->withErrors(['error' => 'No hemos podido completar la inscripción. Si ya inscribiste a este competidor, contacta con el organizador.']);
        }

        return redirect()->route('public.registration.create', $event)
            ->with('status', 'Solicitud recibida. El organizador confirmará la inscripción. Puedes inscribir a otro competidor.');
    }
}
