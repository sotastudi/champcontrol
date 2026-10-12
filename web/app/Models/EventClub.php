<?php

namespace App\Models;

use App\Enums\ClubStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de un club en un evento. El nombre del club es el de su cuenta de usuario. */
#[Fillable(['event_id', 'user_id', 'status'])]
class EventClub extends Model
{
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
