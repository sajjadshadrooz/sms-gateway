<?php

namespace Database\Factories;

use App\Models\Consumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumer>
 */
class ConsumerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'has_express_service' => false,
        ];
    }

    /**
     * Indicate that the consumer is subscribed to the express service.
     */
    public function withExpressService(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_express_service' => true,
        ]);
    }
}
