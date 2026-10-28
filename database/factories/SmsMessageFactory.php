<?php

namespace Database\Factories;

use App\Enums\SmsMessageStatus;
use App\Enums\SmsType;
use App\Models\Consumer;
use App\Models\SmsMessage;
use App\Models\WalletReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_id' => Consumer::factory(),
            'wallet_reservation_id' => WalletReservation::factory(),
            'type' => SmsType::Normal,
            'recipient' => fake()->numerify('0912#######'),
            'body' => fake()->sentence(),
            'cost' => 100,
            'status' => SmsMessageStatus::Pending,
        ];
    }

    public function express(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SmsType::Express,
        ]);
    }
}
