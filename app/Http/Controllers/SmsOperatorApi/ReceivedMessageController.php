<?php

namespace App\Http\Controllers\SmsOperatorApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;


class ReceivedMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'message_id' => ['required'],
                'to' => ['required', 'string'],
                'message' => ['required', 'string'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid SMS request.',
                'code' => 'invalid_request',
                'errors' => $exception->errors(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $key = "sms-operator:received:{$data['message_id']}";

        if (Cache::has($key)) {
            return $this->received(duplicate: true);
        }

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

        // add() is atomic: a concurrent duplicate that lost the race is reported as such.
        if (! Cache::add($key, true, now()->addDay())) {
            return $this->received(duplicate: true);
        }

        return $this->received(duplicate: false);
    }

    private function received(bool $duplicate): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'SMS message received.',
            'code' => 'sms_message_received',
            'duplicate' => $duplicate,
        ], Response::HTTP_OK);
    }
}
