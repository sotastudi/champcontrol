<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Category;
use App\Models\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'competitor_id' => Competitor::factory(),
            'status' => RegistrationStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => RegistrationStatus::Confirmed]);
    }

    /** Crea un competidor nuevo en el mismo evento que la categoría (evita mezclar eventos en tests). */
    public function forCategory(Category $category): static
    {
        return $this->state(fn () => [
            'category_id' => $category->id,
            'competitor_id' => Competitor::factory()->create(['event_id' => $category->event_id])->id,
        ]);
    }
}
