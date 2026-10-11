<?php

namespace App\Tournament;

use App\Enums\BronzeRule;
use App\Enums\RegistrationStatus;
use App\Models\Bout;
use App\Models\Category;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BracketGenerator
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /**
     * @param  Closure(Collection<int,int>): Collection<int,int>|null  $shuffler  reordena los ids de inscripción (sorteo)
     *
     * @throws TournamentException
     */
    public function generate(Category $category, ?Closure $shuffler = null): void
    {
        if ($category->hasResults()) {
            throw new TournamentException('La categoría ya tiene resultados: el cuadro no se puede regenerar.');
        }

        $ids = $category->registrations()->where('status', RegistrationStatus::Confirmed->value)->orderBy('id')->pluck('id');

        if ($ids->count() === 1) {
            throw new TournamentException('Solo hay un inscrito confirmado: no se genera cuadro. Resuelve la clasificación manualmente según el reglamento.');
        }
        if ($ids->isEmpty()) {
            throw new TournamentException('No hay inscripciones confirmadas para generar el cuadro.');
        }

        $ordered = ($shuffler ?? fn (Collection $c) => $c->shuffle())($ids)->values();

        DB::transaction(function () use ($category, $ordered) {
            $category->bouts()->update(['next_bout_id' => null, 'loser_next_bout_id' => null]);
            $category->bouts()->delete();

            $this->build($category, $ordered);

            $category->forceFill(['bracket_generated_at' => now()])->save();
            $this->advancer->settle($category);
        });
    }

    private function build(Category $category, Collection $ids): void
    {
        $n = $ids->count();
        $size = 2;
        $rounds = 1;
        while ($size < $n) {
            $size *= 2;
            $rounds++;
        }

        $seeds = $this->seedOrder($size);
        $number = 0;
        /** @var array<int,array<int,Bout>> $grid */
        $grid = [];
        $bronze = null;

        for ($round = 1; $round <= $rounds; $round++) {
            // El combate por el bronce se disputa antes que la final.
            if ($round === $rounds && $rounds >= 2 && $category->bronze_rule === BronzeRule::BronzeMatch) {
                $semiByes = $rounds === 2 ? count(array_filter($grid[1], fn (Bout $b) => $b->isBye())) : 0;
                $bronze = $this->create($category, ['round' => $round, 'position' => 1, 'is_bronze' => true, 'required_participants' => 2 - $semiByes], $number);
            }

            for ($p = 1; $p <= ($size >> $round); $p++) {
                $attributes = ['round' => $round, 'position' => $p, 'required_participants' => 2];

                if ($round === 1) {
                    $slotA = $ids[$seeds[2 * ($p - 1)] - 1] ?? null;
                    $slotB = $ids[$seeds[2 * ($p - 1) + 1] - 1] ?? null;
                    $attributes += ['slot_a_id' => $slotA, 'slot_b_id' => $slotB];
                    $attributes['required_participants'] = ($slotA && $slotB) ? 2 : 1;
                }

                $grid[$round][$p] = $this->create($category, $attributes, $number);
            }
        }

        foreach ($grid as $round => $bouts) {
            if ($round === $rounds) {
                continue;
            }
            foreach ($bouts as $p => $bout) {
                $bout->next_bout_id = $grid[$round + 1][intdiv($p + 1, 2)]->id;
                $bout->next_slot = $p % 2 === 1 ? 'a' : 'b';
                if ($bronze && $round === $rounds - 1) {
                    $bout->loser_next_bout_id = $bronze->id;
                    $bout->loser_next_slot = $p === 1 ? 'a' : 'b';
                }
                $bout->save();
            }
        }
    }

    private function create(Category $category, array $attributes, int &$number): Bout
    {
        $required = $attributes['required_participants'];

        return $category->bouts()->create($attributes + ['number' => $required === 2 ? ++$number : null]);
    }

    /**
     * Orden de siembra estándar: [1,2] → [1,4,2,3] → [1,8,4,5,2,7,3,6]…
     * Las plazas por encima de n son pases; con n > size/2 nunca se enfrentan dos pases.
     *
     * @return list<int>
     */
    private function seedOrder(int $size): array
    {
        $order = [1];
        for ($n = 1; $n < $size; $n *= 2) {
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = 2 * $n + 1 - $seed;
            }
            $order = $next;
        }

        return $order;
    }
}
