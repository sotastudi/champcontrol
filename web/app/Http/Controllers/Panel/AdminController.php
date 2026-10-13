<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vista de solo lectura para la cuenta superadmin: organizadores, clubs, eventos e inscritos de toda la plataforma. */
class AdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('panel.admin.index', [
            'organizers' => User::where('role', 'organizer')->withCount('events')->orderBy('name')->get(),
            'clubs' => User::where('role', 'club')->withCount('clubRegistrations')->orderBy('name')->get(),
            'events' => Event::with('user')->withCount([
                'registrations as pending_count' => fn ($q) => $q->where('registrations.status', RegistrationStatus::Pending->value),
                'registrations as confirmed_count' => fn ($q) => $q->where('registrations.status', RegistrationStatus::Confirmed->value),
            ])->orderByDesc('starts_on')->get(),
            'registrations' => Registration::with(['competitor', 'category.event'])->latest()->paginate(50),
        ]);
    }
}
