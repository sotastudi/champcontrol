<?php

namespace App\Tournament;

use App\Enums\BronzeRule;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Registration;
use Illuminate\Support\Collection;

final class PodiumCalculator
{
    /** Podio visible: los descalificados se omiten sin renumerar a los demás. @return list<Placement> */
    public function for(Category $category): array
    {
        return array_values(array_filter(
            $this->bracketPlacements($category),
            fn (Placement $p) => ! $p->registration->isDisqualified(),
        ));
    }

    /** Puestos que el cuadro asigna a un descalificado y quedan sin cubrir. @return list<int> */
    public function vacantPlaces(Category $category): array
    {
        $places = array_map(
            fn (Placement $p) => $p->place,
            array_filter($this->bracketPlacements($category), fn (Placement $p) => $p->registration->isDisqualified()),
        );

        return array_values(array_unique($places));
    }

    /** @return list<Placement> */
    private function bracketPlacements(Category $category): array
    {
        $bouts = $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner.competitor'])->get();
        $final = $bouts->first(fn (Bout $b) => $b->isFinal());

        if (! $final || ! $final->isCompleted()) {
            return [];
        }

        $placements = [new Placement(1, $final->winner), new Placement(2, $this->loser($final))];

        if ($category->bronze_rule === BronzeRule::BronzeMatch) {
            $bronze = $bouts->first(fn (Bout $b) => $b->is_bronze);
            if ($bronze?->isCompleted()) {
                $placements[] = new Placement(3, $bronze->winner);
                if ($category->show_fourth && ! $bronze->isBye()) {
                    $placements[] = new Placement(4, $this->loser($bronze));
                }
            }
        } else {
            foreach ($this->semifinals($bouts, $final) as $semi) {
                if ($semi->isCompleted() && ! $semi->isBye()) {
                    $placements[] = new Placement(3, $this->loser($semi));
                }
            }
        }

        return $placements;
    }

    /** @return Collection<int,Bout> */
    private function semifinals(Collection $bouts, Bout $final): Collection
    {
        return $final->round >= 2
            ? $bouts->where('round', $final->round - 1)->where('is_bronze', false)->sortBy('position')->values()
            : collect();
    }

    private function loser(Bout $bout): Registration
    {
        return $bout->slot_a_id === $bout->winner_id ? $bout->slotB : $bout->slotA;
    }
}
