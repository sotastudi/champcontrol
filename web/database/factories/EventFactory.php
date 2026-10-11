<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'discipline' => fake()->randomElement(['Judo', 'Kárate', 'Taekwondo']),
            'description' => fake()->paragraph(),
            'starts_on' => now()->addMonth()->toDateString(),
            'venue' => fake()->city(),
            'status' => EventStatus::Draft,
            'is_public' => false,
            'registration_form_enabled' => false,
        ];
    }

    public function registrationOpen(): static
    {
        return $this->state(fn () => [
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
        ]);
    }
}
