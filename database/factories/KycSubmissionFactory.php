<?php

namespace Database\Factories;

use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycSubmission>
 */
class KycSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'document_type' => 'passport',
            'document_path' => 'kyc/document.jpg',
            'selfie_path' => 'kyc/selfie.jpg',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'reviewed_at' => now()]);
    }
}
