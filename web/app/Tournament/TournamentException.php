<?php

namespace App\Tournament;

class TournamentException extends \DomainException
{
    public const STALE_BRACKET = 'El cuadro está desactualizado respecto a las inscripciones confirmadas: regéneralo antes de anotar el primer resultado.';
}
