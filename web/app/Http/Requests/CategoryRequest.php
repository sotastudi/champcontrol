<?php

namespace App\Http\Requests;

use App\Enums\BronzeRule;
use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La propiedad se comprueba en el middleware can:manage,event.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150',
                Rule::unique('categories', 'name')->where('event_id', $this->route('event')->id)->ignore($this->route('category')?->id)],
            'modality' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'min_weight' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'max_weight' => ['nullable', 'numeric', 'min:0', 'max:300', 'gte:min_weight'],
            'level' => ['nullable', 'string', 'max:100'],
            'bronze_rule' => ['required', Rule::enum(BronzeRule::class)],
            'show_fourth' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // El 4.º puesto solo existe con combate por el bronce.
        if ($this->input('bronze_rule') !== BronzeRule::BronzeMatch->value) {
            $this->merge(['show_fourth' => false]);
        }
    }
}
