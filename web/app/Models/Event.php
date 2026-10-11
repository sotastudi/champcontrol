<?php

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

#[Fillable(['name', 'discipline', 'description', 'starts_on', 'venue', 'status', 'is_public', 'registration_form_enabled'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'status' => EventStatus::class,
            'is_public' => 'boolean',
            'registration_form_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->slug ??= (Str::slug($event->name) ?: 'evento').'-'.Str::lower(Str::random(6));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, Category::class);
    }

    public function scopeListed(Builder $query): void
    {
        $query->where('is_public', true)->where('status', '!=', EventStatus::Draft->value);
    }

    public function isListed(): bool
    {
        return $this->is_public && $this->status !== EventStatus::Draft;
    }

    public function acceptsPublicRegistrations(): bool
    {
        return $this->isListed()
            && $this->registration_form_enabled
            && $this->status === EventStatus::RegistrationOpen;
    }
}
