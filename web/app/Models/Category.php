<?php

namespace App\Models;

use App\Enums\BronzeRule;
use App\Enums\Sex;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'modality', 'sex', 'min_age', 'max_age', 'min_weight', 'max_weight', 'level', 'bronze_rule', 'show_fourth'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'bronze_rule' => BronzeRule::class,
            'min_weight' => 'float',
            'max_weight' => 'float',
            'show_fourth' => 'boolean',
            'is_published' => 'boolean',
            'bracket_generated_at' => 'datetime',
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

    /**
     * Avisos (no bloqueos): el organizador asigna o valida la categoría.
     *
     * @return list<string>
     */
    public function mismatchesFor(Competitor $competitor): array
    {
        $problems = [];
        $age = $competitor->ageOn($this->event->starts_on);

        if ($this->min_age !== null && $age < $this->min_age) {
            $problems[] = "Edad {$age}: inferior al mínimo ({$this->min_age})";
        }
        if ($this->max_age !== null && $age > $this->max_age) {
            $problems[] = "Edad {$age}: superior al máximo ({$this->max_age})";
        }
        if ($this->sex !== null && $competitor->sex !== null && $competitor->sex !== $this->sex) {
            $problems[] = 'Sexo distinto al de la categoría';
        }
        if ($competitor->weight !== null) {
            if (($this->min_weight !== null && $competitor->weight < $this->min_weight)
                || ($this->max_weight !== null && $competitor->weight > $this->max_weight)) {
                $problems[] = "Peso {$competitor->weight} kg fuera del rango";
            }
        }

        return $problems;
    }
}
