<?php

namespace App\Http\Requests;

use App\Enums\JudgeVote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Datos de puntuación de un combate (los usan el propietario del evento y los controladores de tatami). */
class ScoreBoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la hacen los middleware de la ruta (can:manage / can:operate).
    }

    public function rules(): array
    {
        return [
            'a_wazari' => ['nullable', 'integer', 'min:0', 'max:2'],
            'b_wazari' => ['nullable', 'integer', 'min:0', 'max:2'],
            'a_ippon' => ['boolean'],
            'b_ippon' => ['boolean'],
            'a_warnings' => ['nullable', 'integer', 'min:0', 'max:4'],
            'b_warnings' => ['nullable', 'integer', 'min:0', 'max:4'],
            'abandoned_slot' => ['nullable', Rule::in(['a', 'b'])],
            'judge_votes' => ['nullable', 'array', 'max:9'],
            'judge_votes.*' => ['nullable', Rule::enum(JudgeVote::class)],
        ];
    }

    /** @return array<string,mixed> con judge_votes como lista por posición de juez (null = no ha votado) */
    public function scoreData(): array
    {
        $data = $this->validated();

        // judge_votes[i]: i = posición del juez; los que no votan no se envían.
        $votes = [];
        foreach ($data['judge_votes'] ?? [] as $i => $vote) {
            $votes[(int) $i] = $vote;
        }
        ksort($votes);
        $data['judge_votes'] = $votes === [] ? [] : array_map(fn ($i) => $votes[$i] ?? null, range(0, max(array_keys($votes))));

        return $data;
    }
}
