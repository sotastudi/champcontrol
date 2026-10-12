<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Modalidad de una categoría. Solo las de combate generan cuadro y sorteo; el resto se gestiona como inscripción y lista. */
enum Modality: string
{
    use HasOptions;

    case Kumite = 'kumite';
    case Tameshikari = 'tameshikari';
    case Exhibition = 'exhibition';
    case AgreedBout = 'agreed_bout';
    case Kata = 'kata';

    public function label(): string
    {
        return match ($this) {
            self::Kumite => 'Kumite',
            self::Tameshikari => 'Tameshikari',
            self::Exhibition => 'Exhibición',
            self::AgreedBout => 'Combate pactado',
            self::Kata => 'Katas',
        };
    }

    public function hasBracket(): bool
    {
        return match ($this) {
            self::Kumite, self::AgreedBout => true,
            default => false,
        };
    }
}
