<?php

namespace App\Models;

use App\Enums\Sex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Competidor del plantel de un club: sus datos se guardan una vez y se agregan a las categorías de cualquier evento con un clic. */
#[Fillable(['first_name', 'last_name', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'guardian_name', 'guardian_contact'])]
class ClubAthlete extends Model
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'weight' => 'float',
            'guardian_consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /** Competidor sin guardar con estos datos, para comprobar los requisitos de una categoría. */
    public function asCompetitor(): Competitor
    {
        return new Competitor([
            'first_name' => $this->first_name, 'last_name' => $this->last_name, 'birth_date' => $this->birth_date,
            'sex' => $this->sex, 'weight' => $this->weight, 'level' => $this->level,
        ]);
    }

    public function hasGuardianConsent(): bool
    {
        return filled($this->guardian_name) && filled($this->guardian_contact) && $this->guardian_consent_at !== null;
    }

    /** Datos para RegistrationService::register() con el club de la cuenta. @return array<string,mixed> */
    public function registrationData(string $club): array
    {
        return [
            'first_name' => $this->first_name, 'last_name' => $this->last_name, 'club' => $club,
            'birth_date' => $this->birth_date->toDateString(), 'sex' => $this->sex->value, 'weight' => $this->weight,
            'level' => $this->level, 'email' => $this->email, 'phone' => $this->phone,
            'guardian_name' => $this->guardian_name, 'guardian_contact' => $this->guardian_contact,
            'guardian_consent' => $this->guardian_consent_at !== null,
        ];
    }
}
