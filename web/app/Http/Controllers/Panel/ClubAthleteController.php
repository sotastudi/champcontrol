<?php

namespace App\Http\Controllers\Panel;

use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Models\ClubAthlete;
use App\Models\Competitor;
use App\Support\MartialLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Plantel de competidores de la cuenta de club. */
class ClubAthleteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeClub($request);

        return view('panel.athletes.index', ['athletes' => $request->user()->athletes()->orderBy('last_name')->orderBy('first_name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeClub($request);
        [$data, $consent] = $this->validated($request);

        if ($this->duplicate($request, $data)) {
            return back()->withInput()->withErrors(['first_name' => 'Ya tienes un competidor con ese nombre y fecha de nacimiento.']);
        }

        $athlete = $request->user()->athletes()->make($data);
        $athlete->guardian_consent_at = $consent ? now() : null;
        $athlete->save();

        return redirect()->route('panel.athletes.index')->with('status', "«{$athlete->displayName()}» añadido a tus competidores.");
    }

    public function edit(Request $request, ClubAthlete $athlete): View
    {
        $this->authorizeAthlete($request, $athlete);

        return view('panel.athletes.edit', ['athlete' => $athlete]);
    }

    public function update(Request $request, ClubAthlete $athlete): RedirectResponse
    {
        $this->authorizeAthlete($request, $athlete);
        [$data, $consent] = $this->validated($request);

        if ($this->duplicate($request, $data, $athlete)) {
            return back()->withInput()->withErrors(['first_name' => 'Ya tienes un competidor con ese nombre y fecha de nacimiento.']);
        }

        $athlete->fill($data);
        $athlete->guardian_consent_at = $consent ? ($athlete->guardian_consent_at ?? now()) : null;
        $athlete->save();

        return redirect()->route('panel.athletes.index')->with('status', 'Competidor actualizado. Las inscripciones ya hechas no cambian.');
    }

    public function destroy(Request $request, ClubAthlete $athlete): RedirectResponse
    {
        $this->authorizeAthlete($request, $athlete);
        $athlete->delete();

        return redirect()->route('panel.athletes.index')->with('status', 'Competidor eliminado de tu plantel. Las inscripciones ya hechas se mantienen.');
    }

    /** @return array{0: array<string,mixed>, 1: bool} datos validados y consentimiento del representante */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'level' => ['required', Rule::in(MartialLevels::values())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_contact' => ['nullable', 'string', 'max:255'],
            'guardian_consent' => ['nullable', 'boolean'],
        ]);

        $consent = (bool) ($data['guardian_consent'] ?? false);
        unset($data['guardian_consent']);

        // La declaración del representante exige sus datos.
        if ($consent && (blank($data['guardian_name'] ?? null) || blank($data['guardian_contact'] ?? null))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['guardian_name' => 'Indica el nombre y el contacto del representante para registrar su consentimiento.']);
        }

        return [$data, $consent];
    }

    private function duplicate(Request $request, array $data, ?ClubAthlete $except = null): bool
    {
        $first = Competitor::normalizeName($data['first_name']);
        $last = Competitor::normalizeName($data['last_name']);

        return $request->user()->athletes()->whereDate('birth_date', $data['birth_date'])
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))->get()
            ->contains(fn (ClubAthlete $a) => Competitor::normalizeName($a->first_name) === $first && Competitor::normalizeName($a->last_name) === $last);
    }

    private function authorizeClub(Request $request): void
    {
        abort_unless($request->user()->isClubAccount(), 403);
    }

    private function authorizeAthlete(Request $request, ClubAthlete $athlete): void
    {
        $this->authorizeClub($request);
        abort_unless((int) $athlete->user_id === (int) $request->user()->id, 404);
    }
}
