<?php

namespace Database\Factories;

use App\Enums\WalletReservationStatus;
use App\Models\Wallet;
use App\Models\WalletReservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletReservation>
 */
class WalletReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'amount' => fake()->numberBetween(1, 100_000),
            'status' => WalletReservationStatus::Pending,
            'reference_id' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletReservationStatus::Confirmed,
        ]);
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletReservationStatus::Released,
        ]);
    }

    /**
     * A pending reservation whose expiry time has already passed.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletReservationStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WalletReservationStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
