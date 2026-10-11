<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TatamiOperator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** El propietario del evento autoriza (por correo) a usuarios ya registrados como controladores de un tatami. */
class TatamiOperatorController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'tatamis' => ['required', 'array', 'min:1'],
            'tatamis.*' => ['integer', 'min:1', 'max:'.$event->tatamiCount()],
        ], ['username.regex' => 'El usuario solo puede llevar letras minúsculas, números, punto, guion y guion bajo.']);

        // Una cuenta ya existente (por usuario o, si se indica, por correo) solo recibe acceso: no se toca su contraseña ni sus datos.
        $user = User::where('username', $data['username'])->first()
            ?? (filled($data['email'] ?? null) ? User::where('email', $data['email'])->first() : null);
        $created = false;

        if (! $user) {
            // Cuenta nueva solo para puntuar: usuario y contraseña (correo opcional) que decide el organizador.
            if (blank($data['password'] ?? null)) {
                return back()->withInput()->withErrors(['password' => 'Indica una contraseña (mínimo 6 caracteres) para crear la cuenta.']);
            }
            $user = new User([
                'name' => ($data['name'] ?? null) ?: $data['username'],
                'username' => $data['username'],
                'email' => ($data['email'] ?? null) ?: null,
                'password' => $data['password'],
            ]);
            $user->forceFill(['role' => 'tatami', 'created_by_user_id' => $request->user()->id])->save();
            $created = true;
        }

        $added = 0;
        foreach (array_unique(array_map('intval', $data['tatamis'])) as $tatami) {
            $added += $event->operators()->firstOrCreate(['user_id' => $user->id, 'tatami' => $tatami])->wasRecentlyCreated ? 1 : 0;
        }

        $list = implode(', ', array_unique(array_map('intval', $data['tatamis'])));

        return back()->with('status', ($created ? "Cuenta «{$data['username']}» creada. " : "«{$data['username']}» ya tenía cuenta (no se cambia su contraseña). ")
            .($added > 0 ? "Puede puntuar los tatamis: {$list}." : "Ya tenía acceso a los tatamis: {$list}."));
    }

    /**
     * Edita un controlador: sus tatamis en este evento y, si la cuenta la creó este organizador, también su usuario,
     * nombre, correo y contraseña (la contraseña solo cambia si se rellena). Sin correo no hay «recuperar contraseña».
     */
    public function update(Request $request, Event $event, TatamiOperator $operator): RedirectResponse
    {
        $user = $operator->user;
        $own = $user->isTatamiAccount() && (int) $user->created_by_user_id === (int) $request->user()->id;

        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $rules = [
            'tatamis' => ['required', 'array', 'min:1'],
            'tatamis.*' => ['integer', 'min:1', 'max:'.$event->tatamiCount()],
        ];
        if ($own) {
            $rules += [
                'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => ['nullable', 'string', 'min:6', 'max:100'],
            ];
        }
        $data = $request->validate($rules, ['username.regex' => 'El usuario solo puede llevar letras minúsculas, números, punto, guion y guion bajo.']);

        DB::transaction(function () use ($event, $user, $own, $data) {
            if ($own) {
                $user->forceFill([
                    'username' => $data['username'],
                    'name' => ($data['name'] ?? null) ?: $data['username'],
                    'email' => ($data['email'] ?? null) ?: null,
                ]);
                if (filled($data['password'] ?? null)) {
                    $user->forceFill(['password' => $data['password']]);
                }
                $user->save();
            }

            $wanted = array_unique(array_map('intval', $data['tatamis']));
            $event->operators()->where('user_id', $user->id)->whereNotIn('tatami', $wanted)->delete();
            foreach ($wanted as $tatami) {
                $event->operators()->firstOrCreate(['user_id' => $user->id, 'tatami' => $tatami]);
            }
        });

        return back()->with('status', 'Controlador actualizado'.($own ? '' : ' (solo los tatamis: la cuenta no la creaste tú)').'.');
    }

    public function destroy(Event $event, TatamiOperator $operator): RedirectResponse
    {
        $operator->delete();

        return back()->with('status', 'Controlador de tatami retirado.');
    }
}
