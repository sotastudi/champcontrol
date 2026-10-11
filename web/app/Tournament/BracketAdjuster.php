<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BracketAdjuster
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /**
     * Intercambia dos participantes de primera ronda. La estructura de pases no cambia.
     *
     * @throws TournamentException
     */
    public function swap(Category $category, int $registrationA, int $registrationB): void
    {
        if ($registrationA === $registrationB) {
            throw new TournamentException('Elige dos participantes distintos.');
        }
        if ($category->hasResults()) {
            throw new TournamentException('El cuadro ya tiene resultados: no se puede ajustar.');
        }

        $bouts = $category->bouts()->where('round', 1)->get();
        [$boutA, $slotA] = $this->locate($bouts, $registrationA);
        [$boutB, $slotB] = $this->locate($bouts, $registrationB);

        DB::transaction(function () use ($category, $boutA, $slotA, $boutB, $slotB, $registrationA, $registrationB) {
            $boutA->{$slotA} = $registrationB;
            $boutB->{$slotB} = $registrationA;
            $boutA->save();
            $boutB->save();

            $this->advancer->reset($category);
        });
    }

    /** @return array{0: Bout, 1: string} modelo y nombre de columna */
    private function locate(Collection $bouts, int $registrationId): array
    {
        foreach ($bouts as $bout) {
            foreach (['slot_a_id', 'slot_b_id'] as $column) {
                if ($bout->{$column} === $registrationId) {
                    return [$bout, $column];
                }
            }
        }

        throw new TournamentException('El participante no está en la primera ronda del cuadro.');
    }
}
