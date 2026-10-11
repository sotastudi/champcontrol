<?php

namespace Tests\Concerns;

use App\Enums\BronzeRule;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Registration;
use App\Tournament\BracketGenerator;

trait BuildsBrackets
{
    protected function categoryWithConfirmed(int $n, BronzeRule $rule = BronzeRule::BronzeMatch): Category
    {
        $category = Category::factory()->create(['bronze_rule' => $rule]);
        Registration::factory()->count($n)->confirmed()->forCategory($category)->create();

        return $category;
    }

    /** Genera el cuadro con orden de siembra determinista (ids ascendentes = sorteo identidad). */
    protected function generated(int $n, BronzeRule $rule = BronzeRule::BronzeMatch): Category
    {
        $category = $this->categoryWithConfirmed($n, $rule);
        app(BracketGenerator::class)->generate($category, fn ($ids) => $ids->values());

        return $category->refresh();
    }

    protected function bout(Category $category, int $round, int $position, bool $bronze = false): Bout
    {
        return $category->bouts()->where('round', $round)->where('position', $position)->where('is_bronze', $bronze)->firstOrFail();
    }
}
