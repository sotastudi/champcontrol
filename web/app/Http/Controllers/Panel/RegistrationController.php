<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualRegistrationRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Registrations\RegistrationException;
use App\Registrations\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $filters = $request->only('category_id', 'status', 'q');

        return view('panel.registrations.index', [
            'event' => $event,
            'categories' => $event->categories()->orderBy('name')->get(),
            'registrations' => $event->registrations()->with(['competitor', 'category.event'])->filter($filters)
                ->orderBy('categories.name')->orderBy('registrations.id')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Event $event): View
    {
        return view('panel.registrations.create', [
            'event' => $event,
            'categories' => $event->categories()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(ManualRegistrationRequest $request, Event $event, RegistrationService $service): RedirectResponse
    {
        $data = $request->validated();
        $category = $event->categories()->findOrFail($data['category_id']);

        try {
            $service->register($category, $data, RegistrationStatus::from($data['status']));
        } catch (RegistrationException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('panel.registrations.index', $event)->with('status', 'Inscripción registrada.');
    }

    public function update(Request $request, Event $event, Registration $registration, RegistrationService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(RegistrationStatus::class)]]);

        try {
            $service->changeStatus($registration, RegistrationStatus::from($data['status']));
        } catch (RegistrationException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Estado actualizado.');
    }
}
