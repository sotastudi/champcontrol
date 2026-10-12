<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ClubStatus;
use App\Http\Controllers\Controller;
use App\Models\EventClub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Invitaciones que recibe un club de los organizadores: las acepta (queda aprobado en el evento) o las rechaza. */
class InvitationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isClubAccount(), 403);

        return view('panel.invitations.index', ['invitations' => $request->user()->pendingInvitations()->get()]);
    }

    public function accept(Request $request, EventClub $invitation): RedirectResponse
    {
        $this->authorizeInvitation($request, $invitation);
        $invitation->update(['status' => ClubStatus::Approved]);

        return redirect()->route('panel.invitations.index')->with('status', 'Invitación aceptada: tu club ya participa en «'.$invitation->event->name.'».');
    }

    public function decline(Request $request, EventClub $invitation): RedirectResponse
    {
        $this->authorizeInvitation($request, $invitation);
        $invitation->update(['status' => ClubStatus::Rejected]);

        return redirect()->route('panel.invitations.index')->with('status', 'Invitación rechazada.');
    }

    private function authorizeInvitation(Request $request, EventClub $invitation): void
    {
        abort_unless($request->user()->isClubAccount()
            && (int) $invitation->user_id === (int) $request->user()->id
            && $invitation->status === ClubStatus::Invited, 403);
    }
}
