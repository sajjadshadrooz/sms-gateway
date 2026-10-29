<?php

namespace App\Http\Controllers\SmsOperatorApi;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class ReceivedMessageController extends Controller
{
    public function index(): JsonResponse
    {
       return response()->json([
            'status' => 'ok',
            'message' => 'sms message received.',
            'code' => 'sms_message_received'
        ], Response::HTTP_OK);
    }
}