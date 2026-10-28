<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_id' => Consumer::factory(),
            'balance' => fake()->numberBetween(0, 1_000_000),
            'reserved_balance' => 0,
        ];
    }

    /**
     * Indicate that the wallet has no funds at all.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
            'reserved_balance' => 0,
        ]);
    }

    /**
     * Set an explicit balance, optionally with part of it already reserved.
     */
    public function withBalance(int $balance, int $reserved = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
            'reserved_balance' => $reserved,
        ]);
    }
}
