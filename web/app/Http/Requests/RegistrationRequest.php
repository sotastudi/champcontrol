<?php

namespace App\Http\Requests;

use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La propiedad se comprueba en el middleware can:manage,event.
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'club' => ['nullable', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'level' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('event_id', $this->route('event')->id)],
        ];
    }
}
