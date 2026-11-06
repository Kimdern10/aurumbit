<?php

namespace Database\Factories;

use App\Enums\PlanCategory;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word().' plan';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'category' => PlanCategory::Bitcoin,
            'summary' => fake()->sentence(12),
            'min_micros' => 50_000_000,
            'max_micros' => 100_000_000_000,
            'duration_days' => 30,
            'target_return_bps' => 300,
            'risk_level' => 'high',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function gold(): static
    {
        return $this->state(fn (): array => ['category' => PlanCategory::Gold]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
