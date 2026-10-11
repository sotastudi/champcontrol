<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Support\Csv;
use App\Tournament\PodiumCalculator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function registrations(Request $request, Event $event): StreamedResponse
    {
        $registrations = $event->registrations()->with(['competitor', 'category'])
            ->filter($request->only('category_id', 'status', 'q'))
            ->orderBy('categories.name')->orderBy('registrations.id')->get();

        $rows = $registrations->map(fn (Registration $r) => [
            $r->category->name, $r->competitor->last_name, $r->competitor->first_name, $r->competitor->club,
            $r->competitor->birth_date->format('d/m/Y'), $r->competitor->sex?->label(), $r->competitor->weight,
            $r->competitor->email, $r->competitor->phone, $r->status->label(),
            $r->competitor->isMinorOn($event->starts_on) ? 'Sí' : 'No',
            $r->competitor->guardian_name, $r->competitor->guardian_contact, $r->competitor->guardian_consent_at?->format('d/m/Y H:i'),
        ]);

        return Csv::download(
            'inscritos-'.$event->slug.'.csv',
            ['Categoría', 'Apellidos', 'Nombre', 'Club', 'Fecha de nacimiento', 'Sexo', 'Peso', 'Email', 'Teléfono', 'Estado', 'Menor', 'Representante', 'Contacto representante', 'Consentimiento'],
            $rows,
        );
    }

    public function standings(Event $event, PodiumCalculator $podium): StreamedResponse
    {
        $rows = [];
        $categories = $event->categories()->orderBy('name')->get()->each->setRelation('event', $event);
        foreach ($categories as $category) {
            foreach ($podium->for($category) as $p) {
                $c = $p->registration->competitor;
                $rows[] = [$category->name, $p->place, $c->last_name, $c->first_name, $c->club];
            }
        }

        return Csv::download('clasificacion-'.$event->slug.'.csv', ['Categoría', 'Puesto', 'Apellidos', 'Nombre', 'Club'], $rows);
    }
}
