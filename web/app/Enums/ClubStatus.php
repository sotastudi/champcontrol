<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClubStatus: string
{
    use HasOptions;

    case Invited = 'invited';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invitado',
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Invited => 'text-bg-info',
            self::Pending => 'text-bg-orange',
            self::Approved => 'text-bg-success',
            self::Rejected => 'text-bg-secondary',
        };
    }
}
