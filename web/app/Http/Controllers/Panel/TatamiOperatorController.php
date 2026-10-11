<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TatamiOperator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** El propietario del evento autoriza (por correo) a usuarios ya registrados como controladores de un tatami. */
class TatamiOperatorController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'tatami' => ['required', 'integer', 'min:1', 'max:'.$event->tatamiCount()],
        ]);

        $user = User::where('email', $data['email'])->first();
        $password = null;

        if (! $user) {
            // Cuenta nueva solo para puntuar: el organizador la crea y le entrega la contraseña provisional.
            if (blank($data['name'] ?? null)) {
                return back()->withInput()->withErrors(['name' => 'Indica el nombre para crear la cuenta del controlador.']);
            }
            $password = Str::password(12, symbols: false);
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $password]);
            $user->forceFill(['role' => 'tatami'])->save();
        }

        $operator = $event->operators()->firstOrCreate(['user_id' => $user->id, 'tatami' => (int) $data['tatami']]);

        $back = back()->with('status', $operator->wasRecentlyCreated
            ? "{$data['email']} puede puntuar el tatami {$data['tatami']}."
            : "{$data['email']} ya controlaba el tatami {$data['tatami']}.");

        // La contraseña provisional se muestra una sola vez, solo al crear la cuenta.
        return $password ? $back->with('new_operator', ['email' => $data['email'], 'password' => $password]) : $back;
    }

    public function destroy(Event $event, TatamiOperator $operator): RedirectResponse
    {
        $operator->delete();

        return back()->with('status', 'Controlador de tatami retirado.');
    }
}
