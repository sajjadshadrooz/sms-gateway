<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MessageHistoryUnavailableException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct("Could not read the SMS message history from ClickHouse: {$previous->getMessage()}", 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The message history is temporarily unavailable. Please try again.',
            'code' => 'message_history_unavailable',
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
