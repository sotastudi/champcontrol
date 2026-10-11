<?php

namespace App\Http\Requests;

use App\Enums\RegistrationStatus;
use Illuminate\Validation\Rule;

class ManualRegistrationRequest extends RegistrationRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['required', Rule::in([RegistrationStatus::Pending->value, RegistrationStatus::Confirmed->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
