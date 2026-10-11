<?php

namespace App\Models;

use App\Enums\Sex;
use Carbon\CarbonInterface;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes'])]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    /** Campos de datos personales que se copian de un formulario. */
    public const FIELDS = ['first_name', 'last_name', 'club', 'birth_date', 'sex', 'weight', 'level', 'email', 'phone', 'notes'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'weight' => 'float',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
}
