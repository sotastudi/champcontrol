<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * DECISIÓN PENDIENTE: la regla real del bronce depende del reglamento de cada campeonato.
 * BronzeMatch es solo la propuesta inicial. Ver docs/decisiones-pendientes.md.
 */
enum BronzeRule: string
{
    use HasOptions;

    case BronzeMatch = 'bronze_match';
    case SharedBronze = 'shared_bronze';

    public function label(): string
    {
        return match ($this) {
            self::BronzeMatch => 'Combate por el bronce entre perdedores de semifinales (propuesta — DECISIÓN PENDIENTE)',
            self::SharedBronze => 'Dos terceros puestos, sin combate',
        };
    }
}
