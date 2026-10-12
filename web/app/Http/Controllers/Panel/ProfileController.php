<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('panel.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Las cuentas de controlador de tatami pueden no tener correo; el resto lo necesita para entrar y recuperar la contraseña.
            'email' => [$user->isTatamiAccount() ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return redirect()->route('panel.profile.edit')->with('status', 'Datos actualizados.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return redirect()->route('panel.profile.edit')->with('status', 'Contraseña actualizada.');
    }

    /** Vista previa del logo propio. */
    public function logo(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isClubAccount() && $user->hasLogo(), 404);

        return Storage::disk('local')->response($user->logo_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Sube, sustituye o quita el logo del club. */
    public function updateLogo(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isClubAccount(), 403);

        $data = $request->validate([
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ]);

        if ($request->hasFile('logo')) {
            $user->deleteLogo();
            $user->forceFill(['logo_path' => $request->file('logo')->store('club-logos', 'local')])->save();
            $message = 'Logo actualizado.';
        } elseif ($request->boolean('remove_logo')) {
            $user->deleteLogo();
            $message = 'Logo eliminado.';
        } else {
            return back()->withErrors(['logo' => 'Selecciona una imagen.']);
        }

        return redirect()->route('panel.profile.edit')->with('status', $message);
    }
}
