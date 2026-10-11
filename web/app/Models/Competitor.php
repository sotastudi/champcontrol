<?php

namespace App\Models;

use App\Enums\Sex;
use Carbon\CarbonInterface;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes', 'guardian_name', 'guardian_contact'])]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    /** Campos de datos personales que se copian de un formulario. */
    public const FIELDS = ['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes', 'guardian_name', 'guardian_contact'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'weight' => 'float',
            'guardian_consent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function displayName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /** Clave de comparación de nombres: sin acentos, minúsculas y espacios colapsados. */
    public static function normalizeName(string $value): string
    {
        return Str::of($value)->squish()->ascii()->lower()->toString();
    }

    /** Años cumplidos en $date (el día del cumpleaños ya cuenta como cumplido). */
    public function ageOn(CarbonInterface $date): int
    {
        $age = $date->year - $this->birth_date->year;

        if ($date->format('md') < $this->birth_date->format('md')) {
            $age--;
        }

        return $age;
    }

    /** Menor de edad en $date (según `champcontrol.adult_age`). */
    public function isMinorOn(CarbonInterface $date): bool
    {
        return $this->ageOn($date) < config('champcontrol.adult_age');
    }

    public function hasGuardianData(): bool
    {
        return filled(trim((string) $this->guardian_name)) && filled(trim((string) $this->guardian_contact));
    }
}
