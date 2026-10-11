<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Regla del evento: qué pasa con el puesto de un descalificado en el podio. */
enum DisqualifiedPodiumRule: string
{
    use HasOptions;

    case Vacant = 'vacant';
    case ShiftUp = 'shift_up';

    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'Dejar el puesto vacante (sin renumerar)',
            self::ShiftUp => 'Que los puestos siguientes suban',
        };
    }
}
