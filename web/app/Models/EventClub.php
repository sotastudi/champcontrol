<?php

namespace App\Models;

use App\Enums\ClubStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de un club en un evento. El nombre del club es el de su cuenta de usuario. */
#[Fillable(['event_id', 'user_id', 'status', 'color'])]
class EventClub extends Model
{
    /** Paleta de colores de club fáciles de distinguir entre sí (Okabe-Ito ampliada), todos con buen contraste. */
    public const PALETTE = ['#0072b2', '#d55e00', '#009e73', '#cc79a7', '#e69f00', '#6a3d9a', '#b2182b', '#56b4e9', '#7a7a00', '#1b7837', '#8c564b', '#000000'];

    protected function casts(): array
    {
        return ['status' => ClubStatus::class];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === ClubStatus::Approved;
    }
}
