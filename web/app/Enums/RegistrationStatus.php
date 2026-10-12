<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RegistrationStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-orange',
            self::Confirmed => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
