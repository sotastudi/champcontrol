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
        if ($bout->isWalkover()) {
            throw new TournamentException('Victoria por incomparecencia: no se puede corregir.');
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
        $category = $bout->category;
        if (! $category->isRegenerationLocked() && $category->bracketIsStale()) {
            throw new TournamentException(TournamentException::STALE_BRACKET);
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

    /** Valores iniciales de la puntuación de un combate (sin resultado). */
    public const BLANK_SCORE = [
        'a_wazari' => 0, 'b_wazari' => 0, 'a_ippon' => false, 'b_ippon' => false,
        'a_warnings' => 0, 'b_warnings' => 0, 'abandoned_slot' => null, 'judge_votes' => null, 'result_type' => 'normal',
    ];

    /**
     * Reinicia el resultado de un combate: borra ganador y puntuación y retira de los combates siguientes a quienes
     * avanzaron de él. Igual que una corrección, se rechaza si algún combate posterior ya tiene resultado.
     *
     * @throws TournamentException
     */
    public function clear(Bout $bout): void
    {
        $bout = $bout->fresh();

        if ($bout->category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para reiniciar resultados.');
        }
        if ($bout->isBye()) {
            throw new TournamentException('Un pase avanza automáticamente: no tiene resultado que reiniciar.');
        }
        if ($bout->isWalkover()) {
            throw new TournamentException('Victoria por incomparecencia (por una baja): no se puede reiniciar.');
        }
        if (! $bout->isCompleted() && ! $this->hasScore($bout)) {
            throw new TournamentException('Este combate no tiene resultado ni puntuación que reiniciar.');
        }

        DB::transaction(function () use ($bout) {
            if ($bout->isCompleted()) {
                $this->assertCorrectable($bout);
                $this->retract($bout);
                $bout->refresh();
            }
            $bout->forceFill(self::BLANK_SCORE)->save();
        });
    }

    private function hasScore(Bout $bout): bool
    {
        return $bout->a_wazari > 0 || $bout->b_wazari > 0 || $bout->a_ippon || $bout->b_ippon
            || $bout->a_warnings > 0 || $bout->b_warnings > 0 || $bout->abandoned_slot !== null || ! empty($bout->judge_votes);
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
                throw new TournamentException($target->isWalkover()
                    ? 'Un combate posterior se resolvió por incomparecencia y no puede corregirse.'
                    : 'Hay combates posteriores con resultado: corrígelos primero.');
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
