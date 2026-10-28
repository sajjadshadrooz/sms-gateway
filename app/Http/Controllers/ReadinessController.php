<?php

namespace App\Http\Controllers;

use App\Services\DatabaseReadiness;
use App\Services\KafkaReadiness;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// Readiness probe: reports whether the app can serve traffic that needs the database and kafka.
class ReadinessController extends Controller
{
    public function __invoke(DatabaseReadiness $databaseReadiness, KafkaReadiness $kafkaReadiness): JsonResponse
    {
        $databaseReady = $databaseReadiness->isReady();
        $kafkaReady = $kafkaReadiness->isReady();

        $ready = $databaseReady && $kafkaReady;

       return response()->json([
    'status' => $ready ? 'ready' : 'not_ready',
    'checks' => [
        'database' => [
            'status' => $databaseReady ? 'ok' : 'failed',
        ],
        'kafka' => [
            'status' => $kafkaReady ? 'ok' : 'failed',
        ],
    ],
], $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
