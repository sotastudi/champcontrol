<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Voto de un juez: AKA (rojo, primer participante del combate), SHIRO (blanco, segundo) o hikiwake (empate). */
enum JudgeVote: string
{
    use HasOptions;

    case Aka = 'aka';
    case Shiro = 'shiro';
    case Hikiwake = 'hikiwake';

    public function label(): string
    {
        return match ($this) {
            self::Aka => 'AKA (rojo)',
            self::Shiro => 'SHIRO (blanco)',
            self::Hikiwake => 'Hikiwake (empate)',
        };
    }
}
