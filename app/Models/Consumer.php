<?php

namespace App\Models;

use Database\Factories\ConsumerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'last_name', 'email', 'has_express_service'])]
class Consumer extends Model
{
    /** @use HasFactory<ConsumerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_express_service' => 'boolean',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function fullName(): string
    {
        return trim("{$this->name} {$this->last_name}");
    }
}
