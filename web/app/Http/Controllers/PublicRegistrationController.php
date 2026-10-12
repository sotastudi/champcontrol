<?php

namespace App\Http\Controllers;

use App\Enums\ClubStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Category;
use App\Models\ClubAthlete;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\EventClub;
use App\Models\Registration;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    /** Formulario para clubes con el registro aprobado; el resto ve en qué paso está (iniciar sesión, registrar el club, esperar la aprobación). */
    public function create(Request $request, Event $event, RegistrationService $service): View
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);

        $user = $request->user();
        $club = $user?->isClubAccount() ? $user->clubRegistrationFor($event) : null;

        if (! $club?->isApproved()) {
            return view('public.registration-club', ['event' => $event, 'user' => $user, 'club' => $club]);
        }

        $categories = $event->categories()->orderBy('name')->get();

        return view('public.registration', [
            'event' => $event,
            'clubName' => $user->name,
            'categories' => $categories,
            'roster' => $this->roster($user, $event, $categories, $service),
        ]);
    }

    /**
     * Agrega un competidor del plantel del club a una categoría del evento, sin rellenar el formulario, si cumple sus requisitos.
     */
    public function fromRoster(Request $request, Event $event, RegistrationService $service): RedirectResponse
    {
        abort_unless($event->acceptsPublicRegistrations(), 404);
        $user = $request->user();
        abort_unless($user->isClubAccount() && $user->clubRegistrationFor($event)?->isApproved(), 403);

        $data = $request->validate(['athlete_id' => ['required', 'integer'], 'category_id' => ['required', 'integer']]);
        $athlete = $user->athletes()->findOrFail($data['athlete_id']);
        $category = $event->categories()->findOrFail($data['category_id']);

        $problems = $this->problems($athlete, $category);
        if ($problems !== []) {
            return back()->withErrors(['error' => $athlete->displayName().' no puede entrar en «'.$category->name.'»: '.implode('; ', $problems).'.']);
        }

        try {
            $service->register($category, $athlete->registrationData($user->name));
        } catch (RegistrationException) {
            return back()->withErrors(['error' => 'No hemos podido completar la inscripción. Si ya inscribiste a este competidor en esa categoría, contacta con el organizador.']);
        }

        return back()->with('status', $athlete->displayName().' agregado a «'.$category->name.'». El organizador confirmará la inscripción.');
    }

    /** Motivos por los que un competidor del plantel no puede agregarse a la categoría (vacío si cumple). @return list<string> */
    private function problems(ClubAthlete $athlete, Category $category): array
    {
        $problems = $category->mismatchesFor($athlete->asCompetitor());

        if ($athlete->asCompetitor()->isMinorOn($category->event->starts_on) && ! $athlete->hasGuardianConsent()) {
            $problems[] = $athlete->asCompetitor()->isMinorOn(now())
                ? 'es menor y faltan los datos o el consentimiento del representante (edítalo en «Mis competidores»)'
                : 'será menor de edad en la fecha del evento y no constan datos de representante: contacta con el organizador para inscribirlo';
        }

        return $problems;
    }

    /** Plantel del club con, por competidor, las categorías a las que puede agregarse y en las que ya está inscrito. */
    private function roster($user, Event $event, $categories, RegistrationService $service): array
    {
        return $user->athletes()->orderBy('last_name')->orderBy('first_name')->get()->map(function (ClubAthlete $athlete) use ($event, $categories, $service, $user) {
            $competitor = $service->findCompetitor($event, $athlete->registrationData($user->name));
            $registered = $competitor ? Registration::where('competitor_id', $competitor->id)->where('status', '!=', RegistrationStatus::Cancelled->value)->pluck('category_id')->all() : [];
            $options = $categories->map(fn (Category $c) => ['category' => $c, 'problems' => $this->problems($athlete, $c), 'registered' => in_array($c->id, $registered, true)]);

            return ['athlete' => $athlete, 'options' => $options];
        })->all();
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
