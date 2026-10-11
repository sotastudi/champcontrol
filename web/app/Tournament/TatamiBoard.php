<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Estado de cada tatami del evento: sus categorías y qué se está combatiendo. No hay un estado «en curso» explícito:
 * el combate actual de un tatami es el siguiente combate listo (ambos participantes definidos y sin ganador) de sus
 * categorías, por orden de categoría y de número de combate; a continuación vienen los siguientes listos.
 */
final class TatamiBoard
{
    public const UPCOMING = 3;

    /** @return list<array{number:int, categories:Collection, now:?Bout, next:Collection, pending:int}> */
    public function for(Event $event): array
    {
        $categories = $event->categories()->orderBy('id')->get();
        $ready = Bout::whereIn('category_id', $categories->pluck('id'))
            ->where('required_participants', 2)->whereNull('winner_id')
            ->whereNotNull('slot_a_id')->whereNotNull('slot_b_id')
            ->with(['category', 'slotA.competitor', 'slotB.competitor'])
            ->get()
            ->sortBy(fn (Bout $b) => sprintf('%09d-%09d', $b->category_id, $b->number ?? 0))
            ->values();

        $board = [];
        for ($n = 1; $n <= $event->tatamiCount(); $n++) {
            $own = $categories->where('tatami', $n)->values();
            $queue = $ready->filter(fn (Bout $b) => $own->contains('id', $b->category_id))->values();

            $board[] = [
                'number' => $n,
                'categories' => $own,
                'now' => $queue->first(),
                'next' => $queue->slice(1, self::UPCOMING)->values(),
                'pending' => $queue->count(),
            ];
        }

        return $board;
    }
}
