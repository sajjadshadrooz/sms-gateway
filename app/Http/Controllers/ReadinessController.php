<?php

namespace App\Http\Controllers;

use App\Services\DatabaseReadiness;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// Readiness probe: reports whether the app can serve traffic that needs the database.
class ReadinessController extends Controller
{
    public function __invoke(DatabaseReadiness $databaseReadiness): JsonResponse
    {
        $ready = $databaseReadiness->isReady();

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => [
                'database' => [
                    'status' => $ready ? 'ok' : 'failed',
                ],
            ],
        ], $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
