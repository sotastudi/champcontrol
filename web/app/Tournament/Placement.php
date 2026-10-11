<?php

namespace App\Tournament;

use App\Models\Registration;

final readonly class Placement
{
    /** Retirado (conserva el puesto del cuadro); solo lo muestra la vista del organizador. */
    public bool $withdrawn;

    public function __construct(public int $place, public Registration $registration)
    {
        $this->withdrawn = $registration->isWithdrawn();
    }
}
