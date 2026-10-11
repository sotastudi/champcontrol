<?php

namespace App\Models;

use App\Enums\BronzeRule;
use App\Enums\DisqualifiedPodiumRule;
use App\Enums\DrawCriteria;
use App\Enums\EventStatus;
use App\Enums\PreResultsWithdrawalRule;
use App\Enums\UnfoughtWithdrawalRule;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'discipline', 'description', 'starts_on', 'venue', 'status', 'is_public', 'registration_form_enabled',
    'dq_podium_rule', 'unfought_withdrawal_rule', 'default_bronze_rule', 'withdrawal_before_results_rule', 'draw_criteria'])]
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
            'dq_podium_rule' => DisqualifiedPodiumRule::class,
            'unfought_withdrawal_rule' => UnfoughtWithdrawalRule::class,
            'default_bronze_rule' => BronzeRule::class,
            'withdrawal_before_results_rule' => PreResultsWithdrawalRule::class,
            'draw_criteria' => DrawCriteria::class,
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

    /** Reglas del campeonato: nunca null (tras create() el atributo puede estar sin refrescar y la BD aplica el DEFAULT). */
    public function dqPodiumRule(): DisqualifiedPodiumRule
    {
        return $this->dq_podium_rule ?? DisqualifiedPodiumRule::Vacant;
    }

    public function unfoughtWithdrawalRule(): UnfoughtWithdrawalRule
    {
        return $this->unfought_withdrawal_rule ?? UnfoughtWithdrawalRule::KeepPlace;
    }

    public function hasPoster(): bool
    {
        return filled($this->poster_path) && Storage::disk('local')->exists($this->poster_path);
    }

    public function drawCriteria(): DrawCriteria
    {
        return $this->draw_criteria ?? DrawCriteria::LevelWeightClub;
    }

    public function defaultBronzeRule(): BronzeRule
    {
        return $this->default_bronze_rule ?? BronzeRule::BronzeMatch;
    }

    public function preResultsWithdrawalRule(): PreResultsWithdrawalRule
    {
        return $this->withdrawal_before_results_rule ?? PreResultsWithdrawalRule::CancelAndRegenerate;
    }
}
