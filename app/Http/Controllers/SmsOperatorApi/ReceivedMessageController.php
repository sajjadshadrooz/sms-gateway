<?php

namespace App\Http\Controllers\SmsOperatorApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceivedMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $fake = config('services.sms_operator.fake');

        if ($fake['enabled']) {
            $latency = random_int(
                (int) $fake['latency_min'],
                (int) $fake['latency_max']
            );

            usleep($latency * 1000);

            $failureRate = (int) $fake['failure_rate'];

            if (random_int(1, 100) <= $failureRate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'SMS operator is temporarily unavailable.',
                    'code' => 'operator_temporarily_unavailable',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'SMS message received.',
            'code' => 'sms_message_received',
        ], Response::HTTP_OK);
    }
}