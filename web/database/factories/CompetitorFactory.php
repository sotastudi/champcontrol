<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName().' '.fake()->lastName(),
            'club' => fake()->company(),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-12 years')->format('Y-m-d'),
            'sex' => fake()->randomElement(['male', 'female']),
            'weight' => fake()->randomFloat(1, 40, 100),
            'level' => null,
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('6########'),
            'notes' => null,
        ];
    }
}
