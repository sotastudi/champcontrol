<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Support\Csv;
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
        ]);

        return Csv::download(
            'inscritos-'.$event->slug.'.csv',
            ['Categoría', 'Apellidos', 'Nombre', 'Club', 'Fecha de nacimiento', 'Sexo', 'Peso', 'Email', 'Teléfono', 'Estado'],
            $rows,
        );
    }
}
