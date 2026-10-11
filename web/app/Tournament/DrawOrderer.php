<?php

namespace App\Tournament;

use App\Enums\DrawCriteria;
use App\Models\Registration;
use App\Support\MartialLevels;
use Illuminate\Support\Collection;

/**
 * Sorteo del primer combate según los criterios elegidos (nivel: mayor primero; peso: menor primero;
 * después club), con orden aleatorio entre iguales. Los de mayor nivel reciben los pases; el resto se empareja
 * con su vecino de la lista (nivel y peso parecidos) y se intercambian rivales para que el primer
 * combate no sea entre competidores del mismo club cuando sea posible.
 */
final class DrawOrderer
{
    /**
     * @param  Collection<int,Registration>  $registrations  con `competitor` cargado
     * @param  list<int>  $seeds  orden de siembra del cuadro (ver BracketGenerator::seedOrder)
     * @return Collection<int,int> ids de inscripción; el índice i-1 es la plaza de siembra i
     */
    public function order(Collection $registrations, array $seeds, DrawCriteria $criteria = DrawCriteria::LevelWeightClub): Collection
    {
        $n = $registrations->count();
        $size = count($seeds);
        $ranks = array_flip(MartialLevels::values());

        $comparators = [];
        foreach ($criteria->sortKeys() as $key) {
            $comparators[] = $key === 'level'
                ? fn ($a, $b) => ($ranks[$b->competitor->level] ?? -1) <=> ($ranks[$a->competitor->level] ?? -1)
                : fn ($a, $b) => ($a->competitor->weight ?? INF) <=> ($b->competitor->weight ?? INF);
        }
        if ($criteria->avoidsSameClub()) {
            $comparators[] = fn ($a, $b) => $this->club($a) <=> $this->club($b);
        }

        $shuffled = $registrations->shuffle();
        $sorted = ($comparators ? $shuffled->sort(function ($a, $b) use ($comparators) {
            foreach ($comparators as $cmp) {
                if (($r = $cmp($a, $b)) !== 0) {
                    return $r;
                }
            }

            return 0;
        }) : $shuffled)->values()->all();

        $byes = array_splice($sorted, 0, $size - $n);
        $fighters = $sorted;
        if ($criteria->avoidsSameClub()) {
            $this->separateClubs($fighters);
        }

        $ordered = array_fill(0, $n, null);
        $byeIndex = 0;
        $fightIndex = 0;
        for ($p = 0; $p < $size / 2; $p++) {
            $a = $seeds[2 * $p];
            $b = $seeds[2 * $p + 1];
            if ($b > $n) {
                $ordered[$a - 1] = $byes[$byeIndex++]->id;
            } else {
                $ordered[$a - 1] = $fighters[$fightIndex++]->id;
                $ordered[$b - 1] = $fighters[$fightIndex++]->id;
            }
        }

        return collect($ordered);
    }

    private function club(Registration $r): string
    {
        return mb_strtolower(trim((string) $r->competitor->club));
    }

    private function sameClub(Registration $x, Registration $y): bool
    {
        return $this->club($x) !== '' && $this->club($x) === $this->club($y);
    }

    /** @param  list<Registration>  $f  parejas consecutivas (0-1, 2-3…); se modifica en sitio */
    private function separateClubs(array &$f): void
    {
        $count = count($f);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            if (! $this->sameClub($f[$i], $f[$i + 1])) {
                continue;
            }
            for ($j = $i + 2; $j < $count; $j++) {
                $partner = $j % 2 === 0 ? $j + 1 : $j - 1;
                // Intercambiar f[i+1] con f[j]: válido si ninguna de las dos parejas resultantes coincide en club.
                if (! $this->sameClub($f[$i], $f[$j]) && ! $this->sameClub($f[$i + 1], $f[$partner])) {
                    [$f[$i + 1], $f[$j]] = [$f[$j], $f[$i + 1]];
                    break;
                }
            }
        }
    }
}
