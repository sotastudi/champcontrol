<?php

namespace App\Tournament;

use App\Enums\BronzeRule;
use App\Enums\DisqualifiedPodiumRule;
use App\Enums\UnfoughtWithdrawalRule;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Registration;
use Illuminate\Support\Collection;

final class PodiumCalculator
{
    /**
     * Podio visible según las reglas del evento: los descalificados se omiten siempre (y, con «sin puesto», también
     * los retirados que no llegaron a combatir); los demás se renumeran o no según la regla de descalificación.
     *
     * @return list<Placement>
     */
    public function for(Category $category): array
    {
        return $this->resolve($category)['podium'];
    }

    /** Puestos del cuadro que quedan sin cubrir (solo con la regla «vacante»). @return list<int> */
    public function vacantPlaces(Category $category): array
    {
        return $this->resolve($category)['vacant'];
    }

    /** @return array{podium: list<Placement>, vacant: list<int>} */
    private function resolve(Category $category): array
    {
        $bouts = $category->bouts()->with(['slotA.competitor', 'slotB.competitor', 'winner.competitor'])->get();
        $event = $category->event;
        $noPlaceIfUnfought = $event->unfoughtWithdrawalRule() === UnfoughtWithdrawalRule::NoPlace;
        $fought = $noPlaceIfUnfought ? $this->registrationsThatFought($bouts) : [];

        $kept = [];
        $removed = [];
        foreach ($this->bracketPlacements($category, $bouts) as $placement) {
            $registration = $placement->registration;
            $dropped = $registration->isDisqualified()
                || ($noPlaceIfUnfought && $registration->isWithdrawn() && ! isset($fought[$registration->id]));
            $dropped ? $removed[] = $placement : $kept[] = $placement;
        }

        if ($event->dqPodiumRule() === DisqualifiedPodiumRule::ShiftUp) {
            return ['podium' => $this->shiftUp($kept, $category), 'vacant' => []];
        }

        $keptPlaces = array_map(fn (Placement $p) => $p->place, $kept);
        $vacant = array_diff(array_map(fn (Placement $p) => $p->place, $removed), $keptPlaces);
        sort($vacant);

        return ['podium' => $kept, 'vacant' => array_values(array_unique($vacant))];
    }

    /** Renumeración densa por rango respetando empates; descarta lo que supere el máximo permitido. @param list<Placement> $placements @return list<Placement> */
    private function shiftUp(array $placements, Category $category): array
    {
        $places = array_values(array_unique(array_map(fn (Placement $p) => $p->place, $placements)));
        sort($places);
        $rank = array_flip($places);
        $max = $category->show_fourth && $category->bronze_rule === BronzeRule::BronzeMatch ? 4 : 3;

        $result = [];
        foreach ($placements as $p) {
            $new = $rank[$p->place] + 1;
            if ($new <= $max) {
                $result[] = new Placement($new, $p->registration);
            }
        }

        return $result;
    }

    /** Inscripciones que disputaron algún combate real (dos participantes, con ganador y sin incomparecencia). @return array<int,true> */
    private function registrationsThatFought(Collection $bouts): array
    {
        $ids = [];
        foreach ($bouts as $bout) {
            if ((int) $bout->required_participants === 2 && $bout->winner_id !== null && ! $bout->isWalkover()) {
                $ids[$bout->slot_a_id] = true;
                $ids[$bout->slot_b_id] = true;
            }
        }

        return $ids;
    }

    /** @return list<Placement> */
    private function bracketPlacements(Category $category, Collection $bouts): array
    {
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
