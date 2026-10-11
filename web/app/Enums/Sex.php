<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Sex: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Femenino',
        };
    }
}
