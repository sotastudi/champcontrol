<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EventStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case InProgress = 'in_progress';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::RegistrationOpen => 'Inscripciones abiertas',
            self::RegistrationClosed => 'Inscripciones cerradas',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizado',
        };
    }
}
