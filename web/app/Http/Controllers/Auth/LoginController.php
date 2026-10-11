<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'], // usuario o correo electrónico
            'password' => ['required', 'string'],
        ]);

        // Con «@» es un correo; si no, el nombre de usuario (las cuentas de controlador de tatami pueden no tener correo).
        $login = str_contains($data['email'], '@')
            ? ['email' => $data['email']]
            : ['username' => mb_strtolower(trim($data['email']))];

        if (! Auth::attempt($login + ['password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.events.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
