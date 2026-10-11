<?php

namespace App\Tournament;

use App\Enums\EventStatus;
use App\Enums\JudgeVote;
use App\Models\Bout;
use Illuminate\Support\Facades\DB;

/**
 * Puntuación de un combate: wazari/ippon, abandono, votos de los jueces y faltas (1-4).
 * Las faltas solo se registran (no cambian el resultado). El ganador se deduce, por orden:
 * abandono (gana el otro) > ippon (2 wazari = 1 ippon; gana al instante) > mayoría de los jueces
 * (AKA = primer participante, SHIRO = segundo; el empate no suma). Sin ippon ni abandono es obligatorio el voto de todos
 * los jueces de la categoría (2, 3 o 5; 3 si no se indica). Si los jueces empatan, el combate queda sin ganador para resolverlo a mano.
 */
final class ScoreRecorder
{
    public const MAX_WAZARI = 2;

    public function __construct(private readonly ResultRecorder $results) {}

    /**
     * @param  array{a_wazari?:int,b_wazari?:int,a_ippon?:bool,b_ippon?:bool,a_warnings?:int,b_warnings?:int,abandoned_slot?:?string,judge_votes?:list<string>}  $score
     * @return int|null id de inscripción del ganador deducido, o null si no hay ganador
     *
     * @throws TournamentException
     */
    public function record(Bout $bout, array $score): ?int
    {
        $bout = $bout->fresh();

        if ($bout->category->event->status === EventStatus::Finished) {
            throw new TournamentException('El evento está finalizado: reábrelo para corregir resultados.');
        }
        if ($bout->isBye() || $bout->isWalkover() || $bout->filledSlots() !== 2) {
            throw new TournamentException('Este combate no admite puntuación (pase, incomparecencia o participantes sin definir).');
        }

        $data = $this->normalise($score);
        $data = $this->applyIpponVotes($bout, $data);
        $this->assertJudgesVoted($bout, $data);
        $winnerSlot = $this->winnerSlot($data);
        $winnerId = $winnerSlot === null ? null : ($winnerSlot === 'a' ? $bout->slot_a_id : $bout->slot_b_id);

        DB::transaction(function () use ($bout, $data, $winnerId) {
            $bout->forceFill($data)->save();

            if ($winnerId !== null) {
                $this->results->record($bout, $winnerId);
            }
        });

        return $winnerId;
    }

    /** @return array<string,mixed> */
    private function normalise(array $score): array
    {
        $data = [];
        foreach (['a', 'b'] as $side) {
            $wazari = min(self::MAX_WAZARI, max(0, (int) ($score["{$side}_wazari"] ?? 0)));
            $data["{$side}_wazari"] = $wazari;
            // 2 wazari = 1 ippon.
            $data["{$side}_ippon"] = (bool) ($score["{$side}_ippon"] ?? false) || $wazari >= self::MAX_WAZARI;
            $data["{$side}_warnings"] = min(4, max(0, (int) ($score["{$side}_warnings"] ?? 0)));
        }

        $abandoned = $score['abandoned_slot'] ?? null;
        if ($abandoned !== null && $abandoned !== '' && ! in_array($abandoned, ['a', 'b'], true)) {
            throw new TournamentException('Abandono no válido.');
        }
        $data['abandoned_slot'] = $abandoned === '' ? null : $abandoned;

        // Cada posición es un juez; un juez que no ha votado queda en null (se conserva su posición).
        $votes = array_values(array_map(fn ($v) => $v === '' ? null : $v, $score['judge_votes'] ?? []));
        foreach ($votes as $vote) {
            if ($vote !== null && JudgeVote::tryFrom((string) $vote) === null) {
                throw new TournamentException('Voto de juez no válido.');
            }
        }
        while ($votes !== [] && end($votes) === null) {
            array_pop($votes);
        }
        $data['judge_votes'] = $votes === [] ? null : $votes;

        if ($data['a_ippon'] && $data['b_ippon']) {
            throw new TournamentException('Solo un participante puede ganar por ippon.');
        }
        if ($data['abandoned_slot'] !== null && $data[$data['abandoned_slot'].'_ippon']) {
            throw new TournamentException('El participante que abandona no puede tener ippon a su favor.');
        }

        return $data;
    }

    /** @return 'a'|'b'|null */
    private function winnerSlot(array $data): ?string
    {
        if ($data['abandoned_slot'] !== null) {
            return $data['abandoned_slot'] === 'a' ? 'b' : 'a';
        }
        if ($data['a_ippon'] || $data['b_ippon']) {
            return $data['a_ippon'] ? 'a' : 'b';
        }

        $votes = array_count_values(array_filter($data['judge_votes'] ?? [], fn ($v) => $v !== null));
        $aka = $votes[JudgeVote::Aka->value] ?? 0;
        $shiro = $votes[JudgeVote::Shiro->value] ?? 0;
        if ($aka !== $shiro) {
            return $aka > $shiro ? 'a' : 'b';
        }

        return null; // jueces empatados (o todo empate): se resuelve a mano
    }

    /** Sin ippon ni abandono deciden los jueces: han de haber votado todos. */
    private function assertJudgesVoted(Bout $bout, array $data): void
    {
        if ($data['abandoned_slot'] !== null || $data['a_ippon'] || $data['b_ippon']) {
            return;
        }

        $judges = $this->judgesFor($bout);
        $voted = count(array_filter($data['judge_votes'] ?? [], fn ($v) => $v !== null));

        if ($voted < $judges) {
            throw new TournamentException("Faltan los votos de los jueces: marca la bandera de cada uno de los {$judges} jueces ({$voted} de {$judges}).");
        }
    }

    /** Quien gana por ippon se lleva el voto de todos los jueces (AKA = bandera roja, SHIRO = bandera blanca). */
    private function applyIpponVotes(Bout $bout, array $data): array
    {
        if ($data['abandoned_slot'] === null && ($data['a_ippon'] xor $data['b_ippon'])) {
            $data['judge_votes'] = array_fill(0, $this->judgesFor($bout), $data['a_ippon'] ? JudgeVote::Aka->value : JudgeVote::Shiro->value);
        }

        return $data;
    }

    private function judgesFor(Bout $bout): int
    {
        return in_array((int) $bout->category->referees, [2, 3, 5], true) ? (int) $bout->category->referees : 3;
    }
}
