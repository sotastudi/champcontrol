<?php

namespace App\Registrations;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Competitor;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Números de competidor: una vez cerradas las inscripciones, se agrupa por club (orden alfabético; sin club al final),
 * dentro del club por apellidos y nombre, y se numera correlativamente desde 1. Un competidor tiene un único número
 * aunque esté en varias categorías; solo reciben número los que tienen alguna inscripción confirmada.
 * Volver a asignar renumera todo desde cero.
 */
final class BibNumberAssigner
{
    /** @throws RegistrationException */
    public function assign(Event $event): int
    {
        if (! in_array($event->status, [EventStatus::RegistrationClosed, EventStatus::InProgress], true)) {
            throw new RegistrationException('Cierra las inscripciones (estado «Inscripciones cerradas» o «En curso») antes de asignar los números de competidor.');
        }

        $competitors = Competitor::where('event_id', $event->id)
            ->whereHas('registrations', fn ($q) => $q->where('status', RegistrationStatus::Confirmed->value))
            ->get();

        $key = fn (?string $text) => Str::ascii(mb_strtolower(trim((string) $text)));
        $sorted = $competitors->sort(function (Competitor $a, Competitor $b) use ($key) {
            $clubA = $key($a->club);
            $clubB = $key($b->club);
            // Sin club al final.
            if (($clubA === '') !== ($clubB === '')) {
                return $clubA === '' ? 1 : -1;
            }

            return [$clubA, $key($a->last_name), $key($a->first_name), $a->id] <=> [$clubB, $key($b->last_name), $key($b->first_name), $b->id];
        })->values();

        DB::transaction(function () use ($event, $sorted) {
            Competitor::where('event_id', $event->id)->update(['bib_number' => null]);
            foreach ($sorted as $i => $competitor) {
                Competitor::whereKey($competitor->id)->update(['bib_number' => $i + 1]);
            }
        });

        return $sorted->count();
    }
}
