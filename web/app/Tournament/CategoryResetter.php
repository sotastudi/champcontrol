<?php

namespace App\Tournament;

use App\Enums\EventStatus;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/** Reinicia todos los resultados de una categoría y deja el cuadro como recién generado (los emparejamientos se conservan). */
final class CategoryResetter
{
    public function __construct(private readonly BracketAdvancer $advancer) {}

    /**
     * @return int número de combates que tenían resultado o puntuación
     *
     * @throws TournamentException
     */
    public function reset(Category $category): int
    {
        if ($category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para reiniciar resultados.');
        }
        if ($category->bouts()->doesntExist()) {
            throw new TournamentException('Esta categoría aún no tiene cuadro.');
        }

        return DB::transaction(function () use ($category) {
            $affected = $category->bouts()->where('required_participants', 2)
                ->where(fn ($q) => $q->whereNotNull('winner_id')->orWhere('a_wazari', '>', 0)->orWhere('b_wazari', '>', 0)
                    ->orWhere('a_ippon', true)->orWhere('b_ippon', true)->orWhere('a_warnings', '>', 0)->orWhere('b_warnings', '>', 0)
                    ->orWhereNotNull('abandoned_slot')->orWhereNotNull('judge_votes'))
                ->count();

            $category->bouts()->update(ResultRecorder::BLANK_SCORE + ['winner_id' => null]);
            $this->advancer->reset($category);

            // Las bajas siguen registradas: sus incomparecencias se vuelven a aplicar sobre el cuadro limpio.
            $category->registrations()->whereNotNull('withdrawal_type')->get()
                ->each(fn ($registration) => $this->advancer->resolveWalkovers($registration));

            return $affected;
        });
    }
}
