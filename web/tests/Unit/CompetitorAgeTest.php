<?php

namespace Tests\Unit;

use App\Models\Competitor;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CompetitorAgeTest extends TestCase
{
    private function competitor(string $birth): Competitor
    {
        $competitor = new Competitor;
        // Atributo en bruto: asignar un cast de fecha exigiría conexión a BD (test unitario sin BD).
        $competitor->setRawAttributes(['birth_date' => $birth]);

        return $competitor;
    }

    public function test_birthday_on_the_event_day_counts_as_turned(): void
    {
        $this->assertSame(15, $this->competitor('2010-06-15')->ageOn(Carbon::parse('2025-06-15')));
    }

    public function test_day_before_birthday_has_not_turned_yet(): void
    {
        $this->assertSame(14, $this->competitor('2010-06-15')->ageOn(Carbon::parse('2025-06-14')));
    }

    public function test_leap_day_birthday_in_non_leap_year(): void
    {
        $this->assertSame(16, $this->competitor('2008-02-29')->ageOn(Carbon::parse('2025-02-28'))); // aún no cumple 17
        $this->assertSame(17, $this->competitor('2008-02-29')->ageOn(Carbon::parse('2025-03-01')));
    }
}
