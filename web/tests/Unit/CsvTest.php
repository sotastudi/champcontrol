<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_cell_neutralises_formulas(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Csv::cell($input));
    }

    public static function cells(): array
    {
        return [
            'texto' => ['Dojo Norte', 'Dojo Norte'],
            'null' => [null, ''],
            'número' => [58.5, '58.5'],
            'igual' => ['=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'],
            'teléfono con +' => ['+34 600 000 000', '+34 600 000 000'],
            'teléfono pegado' => ['+34600000000', '+34600000000'],
            'número negativo' => ['-5', '-5'],
            'suma interior' => ['+34+1', '\'+34+1'],
            'resta con operación' => ['-1+2', '\'-1+2'],
            'dde' => ["+cmd|' /C calc'!A0", "'+cmd|' /C calc'!A0"],
            'arroba' => ['@SUM(A1)', '\'@SUM(A1)'],
        ];
    }
}
