<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendSms;
use App\Enums\SmsType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsRequest;
use App\Http\Resources\SmsMessageResource;
use Illuminate\Http\JsonResponse;

class SmsMessageController extends Controller
{
    public function store(SendSmsRequest $request, SendSms $sendSms): JsonResponse
    {
        $consumer = $request->consumer();

        if ($consumer === null) {
            return $this->consumerNotFound();
        }

        $message = $sendSms->handle(
            $consumer,
            SmsType::Normal,
            $request->string('to')->toString(),
            $request->string('message')->toString(),
        );

        return SmsMessageResource::make($message)->response()->setStatusCode(202);
    }
}
