<?php

namespace App\Support;

/** Niveles (grados) disponibles: 10.º kyu a 1.er kyu y 1.er a 10.º dan. Se guarda el texto. */
final class MartialLevels
{
    /** @return array<string, string> texto => texto, en orden de menor a mayor grado */
    public static function options(): array
    {
        $levels = [];
        foreach (range(10, 1) as $n) {
            $levels["{$n}º kyu"] = "{$n}º kyu";
        }
        foreach (range(1, 10) as $n) {
            $levels["{$n}º dan"] = "{$n}º dan";
        }

        return $levels;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::options());
    }
}
