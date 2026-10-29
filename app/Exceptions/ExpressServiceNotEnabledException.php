<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ExpressServiceNotEnabledException extends RuntimeException
{
    public function __construct(int $consumerId)
    {
        parent::__construct("consumer [{$consumerId}] is not registered at express service.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The express service is not enabled for this consumer.',
            'code' => 'express_service_is_not_enabled',
        ], Response::HTTP_FORBIDDEN);
    }
}
