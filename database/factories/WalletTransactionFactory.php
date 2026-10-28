<?php

namespace Database\Factories;

use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 100_000);

        return [
            'wallet_id' => Wallet::factory(),
            'type' => WalletTransactionType::Credit,
            'amount' => $amount,
            'balance_after' => $amount,
            'description' => null,
        ];
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransactionType::Debit,
        ]);
    }
}
