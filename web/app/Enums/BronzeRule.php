<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Regla del bronce APROBADA por la organización: BronzeMatch (combate entre los perdedores de
 * semifinales). SharedBronze (dos terceros) queda disponible por categoría. Ver docs/decisiones-pendientes.md.
 */
enum BronzeRule: string
{
    use HasOptions;

    case BronzeMatch = 'bronze_match';
    case SharedBronze = 'shared_bronze';

    public function label(): string
    {
        return match ($this) {
            self::BronzeMatch => 'Combate por el bronce entre perdedores de semifinales',
            self::SharedBronze => 'Dos terceros puestos, sin combate',
        };
    }
}
