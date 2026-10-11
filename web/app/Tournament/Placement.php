<?php

namespace App\Tournament;

use App\Models\Registration;

final readonly class Placement
{
    public function __construct(public int $place, public Registration $registration) {}
}
