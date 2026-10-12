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
            // Los tests de clasificación parten de la regla clásica; los valores por defecto reales se prueban en EventTest.
            'dq_podium_rule' => 'vacant',
            'unfought_withdrawal_rule' => 'keep_place',
        ];
    }

    public function registrationOpen(): static
    {
        return $this->state(fn () => [
            'is_public' => true, 'registration_form_enabled' => true, 'status' => EventStatus::RegistrationOpen,
        ]);
    }
}
