<?php

namespace App\Tournament;

use App\Models\Bout;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Estado de cada tatami del evento: sus categorías y qué se está combatiendo. No hay un estado «en curso» explícito:
 * el combate actual de un tatami es el siguiente combate listo (ambos participantes definidos y sin ganador) de sus
 * categorías, por orden de categoría dentro del tatami (el que fija el organizador) y de número de combate; a continuación vienen los siguientes listos.
 */
final class TatamiBoard
{
    public const UPCOMING = 3;

    /**
     * @param  bool  $publishedOnly  solo categorías publicadas (para la página pública)
     * @return list<array{number:int, categories:Collection, now:?Bout, next:Collection, pending:int}> */
    public function for(Event $event, bool $publishedOnly = false): array
    {
        $categories = $event->categories()->when($publishedOnly, fn ($q) => $q->where('is_published', true))->orderBy('tatami')->orderBy('tatami_position')->orderBy('id')->get();
        $rank = $categories->pluck('id')->flip();
        $ready = Bout::whereIn('category_id', $categories->pluck('id'))
            ->where('required_participants', 2)->whereNull('winner_id')
            ->whereNotNull('slot_a_id')->whereNotNull('slot_b_id')
            ->with(['category', 'slotA.competitor', 'slotB.competitor'])
            ->get()
            ->sortBy(fn (Bout $b) => sprintf('%09d-%09d', $rank[$b->category_id] ?? 0, $b->number ?? 0))
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
