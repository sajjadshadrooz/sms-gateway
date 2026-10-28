<?php

namespace App\Http\Requests;

class SendExpressSmsRequest extends SendSmsRequest
{
    public function authorize(): bool
    {
        return $this->consumer()?->has_express_service ?? true;
    }
}
