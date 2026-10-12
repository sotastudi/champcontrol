<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Registrations\RegistrationException;
use App\Registrations\TestDataGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Datos de prueba de un evento marcado como «Test»: categorías, inscripciones y su limpieza. */
class TestDataController extends Controller
{
    public function categories(Request $request, Event $event, TestDataGenerator $generator): RedirectResponse
    {
        abort_unless($event->is_test, 404);
        $data = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:'.TestDataGenerator::MAX_CATEGORIES]]);

        $created = $generator->categories($event, (int) $data['count']);

        return back()->with('status', $created > 0 ? "Categorías de prueba creadas: {$created}." : 'Esas categorías de prueba ya existían.');
    }

    public function registrations(Request $request, Event $event, TestDataGenerator $generator): RedirectResponse
    {
        abort_unless($event->is_test, 404);
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'participants' => ['required', 'integer', 'min:1', 'max:'.TestDataGenerator::MAX_PARTICIPANTS],
            'clubs' => ['required', 'integer', 'min:1', 'max:50'],
            'confirmed' => ['boolean'],
        ]);
        $category = $event->categories()->findOrFail($data['category_id']);

        try {
            $created = $generator->registrations($category, (int) $data['participants'], (int) $data['clubs'], $request->boolean('confirmed'));
        } catch (RegistrationException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', "Inscripciones de prueba creadas en «{$category->name}»: {$created}.");
    }

    public function clean(Event $event, TestDataGenerator $generator): RedirectResponse
    {
        abort_unless($event->is_test, 404);

        return back()->with('status', 'Inscripciones de prueba borradas: '.$generator->clean($event).'.');
    }
}
