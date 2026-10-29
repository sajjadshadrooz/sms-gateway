<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SmsQueueUnavailableException extends RuntimeException
{
    public function __construct(string $topic, Throwable $previous)
    {
        parent::__construct("Could not publish to Kafka topic [{$topic}]: {$previous->getMessage()}", 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The message could not be queued for delivery. Please try again.',
            'code' => 'queue_unavailable',
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
