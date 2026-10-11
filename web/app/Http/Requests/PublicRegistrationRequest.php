<?php

namespace App\Http\Requests;

use App\Models\Competitor;
use Illuminate\Validation\Validator;

class PublicRegistrationRequest extends RegistrationRequest
{
    public function authorize(): bool
    {
        // 404 (no 403) antes de validar: no revela si un evento privado o cerrado existe.
        abort_unless($this->route('event')->acceptsPublicRegistrations(), 404);

        return true;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('birth_date') || ! $this->filled('birth_date')) {
                return;
            }

            $competitor = new Competitor(['birth_date' => $this->input('birth_date')]);
            if ($competitor->ageOn($this->route('event')->starts_on) < config('champcontrol.public_form_min_age')) {
                $validator->errors()->add('birth_date', 'Este formulario no admite menores de edad. Contacta con el organizador.');
            }
        }];
    }
}
