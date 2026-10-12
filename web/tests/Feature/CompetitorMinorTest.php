<?php

namespace Tests\Feature;

use App\Models\Competitor;
use Carbon\Carbon;
use Tests\TestCase;

class CompetitorMinorTest extends TestCase
{
    private function competitor(string $birth, array $extra = []): Competitor
    {
        $competitor = new Competitor;
        $competitor->setRawAttributes(['birth_date' => $birth] + $extra); // sin BD

        return $competitor;
    }

    public function test_is_minor_on_uses_the_exact_birthday(): void
    {
        $c = $this->competitor('2008-11-20');

        $this->assertFalse($c->isMinorOn(Carbon::parse('2026-11-20')));   // cumple 18 ese día
        $this->assertTrue($c->isMinorOn(Carbon::parse('2026-11-19')));
    }

    public function test_is_minor_on_uses_the_adult_age_config(): void
    {
        config(['kumitecontrol.adult_age' => 21]);

        $this->assertTrue($this->competitor('2008-11-20')->isMinorOn(Carbon::parse('2026-11-20')));
        $this->assertFalse($this->competitor('2005-11-20')->isMinorOn(Carbon::parse('2026-11-20')));
    }

    public function test_has_guardian_data_needs_name_and_contact(): void
    {
        $this->assertFalse($this->competitor('2010-01-01')->hasGuardianData());
        $this->assertFalse($this->competitor('2010-01-01', ['guardian_name' => 'Ana', 'guardian_contact' => ' '])->hasGuardianData());
        $this->assertTrue($this->competitor('2010-01-01', ['guardian_name' => 'Ana', 'guardian_contact' => 'a@b.test'])->hasGuardianData());
    }
}
