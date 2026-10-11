<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Category;

final class BracketAdvancer
{
    /** Resuelve los pases cuyo único participante ya está colocado. */
    public function settle(Category $category): void
    {
        $category->bouts()->where('required_participants', 1)->whereNull('winner_id')
            ->orderBy('round')->orderBy('position')->get()
            ->each(function (Bout $bout) {
                $bout->refresh();
                if (! $bout->isCompleted() && $bout->filledSlots() === 1) {
                    $this->complete($bout, $bout->slot_a_id ?? $bout->slot_b_id);
                }
            });
    }

    /** Fija el ganador y coloca ganador y perdedor en sus combates destino (en cascada para pases). */
    public function complete(Bout $bout, int $winnerId): void
    {
        $loserId = $bout->isBye() ? null : ($bout->slot_a_id === $winnerId ? $bout->slot_b_id : $bout->slot_a_id);

        $bout->update(['winner_id' => $winnerId]);

        $this->place($bout->next_bout_id, $bout->next_slot, $winnerId);
        $this->place($bout->loser_next_bout_id, $bout->loser_next_slot, $loserId);
    }

    /** Vuelve al estado inicial de la primera ronda (solo válido sin resultados reales) y re-resuelve los pases. */
    public function reset(Category $category): void
    {
        $category->bouts()->where('round', '>', 1)->update(['slot_a_id' => null, 'slot_b_id' => null]);
        $category->bouts()->update(['winner_id' => null]);

        $this->settle($category);
    }

    private function place(?int $boutId, ?string $slot, ?int $registrationId): void
    {
        if ($boutId === null || $slot === null || $registrationId === null) {
            return;
        }

        $target = Bout::findOrFail($boutId);
        $target->update([$slot === 'a' ? 'slot_a_id' : 'slot_b_id' => $registrationId]);

        if ($target->isBye() && $target->filledSlots() === 1) {
            $this->complete($target, $registrationId);
        }
    }
}
