<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** @return array<string,string> valor => etiqueta, para <select> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
