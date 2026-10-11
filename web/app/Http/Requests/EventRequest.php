<?php

namespace App\Http\Requests;

use App\Enums\BronzeRule;
use App\Enums\DisqualifiedPodiumRule;
use App\Enums\DrawCriteria;
use App\Enums\EventStatus;
use App\Enums\PreResultsWithdrawalRule;
use App\Enums\UnfoughtWithdrawalRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La propiedad se comprueba en el middleware can:manage,event; crear solo exige sesión.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'discipline' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:200'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'is_public' => ['boolean'],
            'registration_form_enabled' => ['boolean'],
            'dq_podium_rule' => ['sometimes', 'required', Rule::enum(DisqualifiedPodiumRule::class)],
            'unfought_withdrawal_rule' => ['sometimes', 'required', Rule::enum(UnfoughtWithdrawalRule::class)],
            'default_bronze_rule' => ['sometimes', 'required', Rule::enum(BronzeRule::class)],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', Rule::when($this->filled('registration_opens_at'), ['after:registration_opens_at'])],
            'tatami_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:50', function (string $attribute, mixed $value, \Closure $fail) {
                $event = $this->route('event');
                $max = $event?->categories()->max('tatami');
                if ($max !== null && (int) $value < (int) $max) {
                    $fail("Hay categorías asignadas al tatami {$max}: reasígnalas antes de reducir el número de tatamis.");
                }
            }],
            'poster' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_poster' => ['boolean'],
            'draw_criteria' => ['sometimes', 'required', Rule::enum(DrawCriteria::class)],
            'withdrawal_before_results_rule' => ['sometimes', 'required', Rule::enum(PreResultsWithdrawalRule::class)],
        ];
    }
}
