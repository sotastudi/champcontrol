<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ClubStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventClub;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Clubes registrados en el evento: el organizador los aprueba o rechaza antes de que puedan inscribir competidores. */
class ClubController extends Controller
{
    public function index(Event $event): View
    {
        return view('panel.clubs.index', ['event' => $event, 'clubs' => $event->clubs()->with('user')->get()->sortBy(fn ($c) => mb_strtolower($c->user->name))->values()]);
    }

    /**
     * El organizador crea la cuenta de un club y lo deja ya aprobado en este evento. Si el correo ya es de una cuenta de club,
     * solo se registra y aprueba en el evento (no se toca su contraseña ni sus datos).
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', Password::min(8)],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->isClubAccount()) {
            return back()->withInput()->withErrors(['email' => 'Ese correo pertenece a una cuenta que no es de club.']);
        }

        $created = false;
        if (! $user) {
            if (blank($data['password'] ?? null)) {
                return back()->withInput()->withErrors(['password' => 'Indica una contraseña (mínimo 8 caracteres) para crear la cuenta.']);
            }
            if (User::where('role', 'club')->where('name', $data['name'])->exists()) {
                return back()->withInput()->withErrors(['name' => 'Ya existe una cuenta de club con ese nombre.']);
            }
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->forceFill(['role' => 'club', 'created_by_user_id' => $request->user()->id])->save();
            $created = true;
        }

        $event->clubs()->updateOrCreate(['user_id' => $user->id], ['status' => ClubStatus::Approved]);

        return back()->with('status', ($created ? "Cuenta del club «{$user->name}» creada" : "El club «{$user->name}» ya tenía cuenta (no se cambia su contraseña)").' y aprobada en este evento.');
    }

    public function update(Request $request, Event $event, EventClub $club): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ClubStatus::class)]]);

        $club->update($data);

        return back()->with('status', 'Club actualizado.');
    }
}
