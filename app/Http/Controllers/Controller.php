<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function consumerNotFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Consumer not found.',
            'code' => 'consumer_not_found',
        ], 404);
    }
}
