<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Regla del evento: puesto de un retirado que nunca llegó a combatir. */
enum UnfoughtWithdrawalRule: string
{
    use HasOptions;

    case KeepPlace = 'keep_place';
    case NoPlace = 'no_place';

    public function label(): string
    {
        return match ($this) {
            self::KeepPlace => 'Conserva el puesto que le da el cuadro',
            self::NoPlace => 'Queda sin puesto en el podio (como un descalificado)',
        };
    }
}
