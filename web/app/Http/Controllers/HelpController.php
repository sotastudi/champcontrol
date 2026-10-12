<?php

namespace App\Http\Controllers;

use App\Support\HelpContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ayuda: guías por tipo de usuario, preguntas frecuentes y buscador. Pública. */
class HelpController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        return view('help.index', [
            'query' => mb_substr($query, 0, 100),
            'results' => $query === '' ? null : HelpContent::search(mb_substr($query, 0, 100)),
            'guides' => HelpContent::guides(),
            'faqs' => HelpContent::faqs(),
            // Guía que corresponde al usuario con sesión iniciada, para destacarla.
            'mine' => $user ? ($user->isClubAccount() ? 'club' : ($user->isTatamiAccount() ? 'tatami' : 'organizador')) : null,
        ]);
    }
}
