<?php

namespace App\Http\Controllers\Panel;

use App\Enums\WithdrawalType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Tournament\TournamentException;
use App\Tournament\WithdrawalRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WithdrawalController extends Controller
{
    public function store(Request $request, Event $event, Category $category, WithdrawalRecorder $recorder): RedirectResponse
    {
        $data = $request->validate([
            'registration_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(WithdrawalType::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $registration = $category->registrations()->findOrFail($data['registration_id']);

        try {
            $recorder->record($registration, WithdrawalType::from($data['type']), $data['reason'] ?? null);
        } catch (TournamentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Baja registrada.');
    }
}
