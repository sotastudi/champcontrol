<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WithdrawalType: string
{
    use HasOptions;

    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Withdrawn => 'Retirada',
            self::Disqualified => 'Descalificación',
        };
    }
}
