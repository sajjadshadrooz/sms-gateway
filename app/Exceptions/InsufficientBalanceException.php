<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class InsufficientBalanceException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct("Insufficient wallet balance: {$required} required, {$available} available.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient wallet balance.',
            'code' => 'insufficient_balance',
            'required' => $this->required,
            'available' => $this->available,
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
