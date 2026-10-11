<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
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
        ];
    }
}
