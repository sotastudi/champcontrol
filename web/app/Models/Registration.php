<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'competitor_id', 'status'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => RegistrationStatus::class];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function isInBracket(): bool
    {
        return Bout::where(fn ($q) => $q->where('slot_a_id', $this->id)->orWhere('slot_b_id', $this->id))->exists();
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('registrations.category_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('registrations.status', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->whereHas('competitor', fn ($c) => $c->where(fn ($w) => $w
                    ->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('club', 'like', $like)));
            });
    }
}
