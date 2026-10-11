<?php

namespace App\Tournament;

final class RoundLabel
{
    public static function for(int $round, int $totalRounds): string
    {
        return match ($totalRounds - $round) {
            0 => 'Final',
            1 => 'Semifinales',
            2 => 'Cuartos de final',
            3 => 'Octavos de final',
            default => 'Ronda '.$round,
        };
    }
}
