<?php

namespace App\Http\Requests;

use App\Models\Competitor;
use Illuminate\Support\Carbon;

class PublicRegistrationRequest extends RegistrationRequest
{
    public function authorize(): bool
    {
        // 404 (no 403) antes de validar: no revela si un evento privado o cerrado existe.
        abort_unless($this->route('event')->acceptsPublicRegistrations(), 404);

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->isMinorOnEventDate()) {
            $rules['guardian_name'] = ['required', 'string', 'max:150'];
            $rules['guardian_contact'] = ['required', 'string', 'max:255'];
            $rules['guardian_consent'] = ['accepted'];
        }

        return $rules;
    }

    /** Menor en la fecha del evento; una fecha de nacimiento no válida no cuenta (la rechaza su propia regla). */
    private function isMinorOnEventDate(): bool
    {
        $birth = $this->input('birth_date');
        if (! is_string($birth) || $birth === '') {
            return false;
        }

        try {
            Carbon::parse($birth);
        } catch (\Throwable) {
            return false;
        }

        return (new Competitor(['birth_date' => $birth]))->isMinorOn($this->route('event')->starts_on);
    }
}
