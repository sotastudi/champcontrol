<?php

namespace Database\Factories;

use App\Enums\BronzeRule;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->unique()->words(2, true),
            'bronze_rule' => BronzeRule::BronzeMatch,
            'show_fourth' => false,
            'is_published' => false,
        ];
    }
}
