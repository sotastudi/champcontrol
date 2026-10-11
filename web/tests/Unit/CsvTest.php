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
            'más' => ['+34600', '\'+34600'],
            'menos' => ['-1', '\'-1'],
            'arroba' => ['@SUM(A1)', '\'@SUM(A1)'],
        ];
    }
}
