<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Criterios de ordenación del sorteo del primer combate (se evita el mismo club cuando es posible). */
enum DrawCriteria: string
{
    use HasOptions;

    case LevelWeightClub = 'level_weight_club';
    case WeightLevelClub = 'weight_level_club';
    case LevelClub = 'level_club';
    case WeightClub = 'weight_club';
    case ClubOnly = 'club_only';
    case Random = 'random';

    public function label(): string
    {
        return match ($this) {
            self::LevelWeightClub => 'Nivel > peso > club',
            self::WeightLevelClub => 'Peso > nivel > club',
            self::LevelClub => 'Nivel > club',
            self::WeightClub => 'Peso > club',
            self::ClubOnly => 'Solo club (azar, evitando el mismo club)',
            self::Random => 'Azar puro',
        };
    }

    /** @return list<'level'|'weight'> */
    public function sortKeys(): array
    {
        return match ($this) {
            self::LevelWeightClub => ['level', 'weight'],
            self::WeightLevelClub => ['weight', 'level'],
            self::LevelClub => ['level'],
            self::WeightClub => ['weight'],
            self::ClubOnly, self::Random => [],
        };
    }

    public function avoidsSameClub(): bool
    {
        return $this !== self::Random;
    }
}
