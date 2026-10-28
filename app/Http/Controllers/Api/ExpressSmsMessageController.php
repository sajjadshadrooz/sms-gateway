<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendSms;
use App\Enums\SmsType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendExpressSmsRequest;
use App\Http\Resources\SmsMessageResource;
use App\Models\Consumer;
use Illuminate\Http\JsonResponse;

class ExpressSmsMessageController extends Controller
{
    public function store(SendExpressSmsRequest $request, Consumer $consumer, SendSms $sendSms): JsonResponse
    {
        $message = $sendSms->handle(
            $consumer,
            SmsType::Express,
            $request->string('to')->toString(),
            $request->string('message')->toString(),
        );

        return SmsMessageResource::make($message)->response()->setStatusCode(202);
    }
}
