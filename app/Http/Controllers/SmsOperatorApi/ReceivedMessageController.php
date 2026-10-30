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
        return response()->json([
            'status' => 'ok',
            'message' => 'sms message received.',
            'code' => 'sms_message_received',
        ], Response::HTTP_OK);
    }
}
