<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Regla del evento: cómo se gestiona una baja antes de que haya resultados. */
enum PreResultsWithdrawalRule: string
{
    use HasOptions;

    case CancelAndRegenerate = 'cancel_and_regenerate';
    case AllowWalkover = 'allow_walkover';

    public function label(): string
    {
        return match ($this) {
            self::CancelAndRegenerate => 'Cancelar la inscripción y regenerar el cuadro',
            self::AllowWalkover => 'Permitir registrar la baja como incomparecencia desde el principio',
        };
    }
}
