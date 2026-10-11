<?php

namespace App\Tournament;

use App\Enums\EventStatus;
use App\Models\Bout;
use Illuminate\Support\Facades\DB;

final class ResultRecorder
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /** @throws TournamentException */
    public function record(Bout $bout, int $winnerRegistrationId): void
    {
        $bout = $bout->fresh();

        if ($bout->category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para corregir resultados.');
        }
        if ($bout->isBye()) {
            throw new TournamentException('Un pase avanza automáticamente: no admite resultado.');
        }
        if ($bout->filledSlots() !== 2) {
            throw new TournamentException('Todavía no están definidos ambos participantes de este combate.');
        }
        if (! in_array($winnerRegistrationId, [$bout->slot_a_id, $bout->slot_b_id], true)) {
            throw new TournamentException('El ganador debe ser uno de los dos participantes del combate.');
        }
        if ($bout->winner_id === $winnerRegistrationId) {
            return; // idempotente
        }

        DB::transaction(function () use ($bout, $winnerRegistrationId) {
            if ($bout->isCompleted()) {
                $this->assertCorrectable($bout);
                $this->retract($bout);
                $bout->refresh();
            }

            $this->advancer->complete($bout, $winnerRegistrationId);
        });
    }

    private function dependents(Bout $bout): array
    {
        return array_filter([
            [$bout->next_bout_id, $bout->next_slot],
            [$bout->loser_next_bout_id, $bout->loser_next_slot],
        ], fn (array $d) => $d[0] !== null);
    }

    private function assertCorrectable(Bout $bout): void
    {
        foreach ($this->dependents($bout) as [$id]) {
            $target = Bout::findOrFail($id);
            if ($target->isCompleted() && ! $target->isBye()) {
                throw new TournamentException('Hay combates posteriores con resultado: corrígelos primero.');
            }
        }
    }

    /** Deshace la colocación de ganador y perdedor derivada de este combate. */
    private function retract(Bout $bout): void
    {
        foreach ($this->dependents($bout) as [$id, $slot]) {
            $target = Bout::findOrFail($id);
            if ($target->isBye() && $target->isCompleted()) {
                $this->retract($target);
                $target->refresh();
            }
            $target->update([$slot === 'a' ? 'slot_a_id' : 'slot_b_id' => null, 'winner_id' => $target->isBye() ? null : $target->winner_id]);
        }

        $bout->update(['winner_id' => null]);
    }
}
