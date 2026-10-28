<?php

namespace App\Http\Requests;

use App\Models\Consumer;

class SendExpressSmsRequest extends SendSmsRequest
{
    public function authorize(): bool
    {
        $consumer = $this->route('consumer');

        return $consumer instanceof Consumer && $consumer->has_express_service;
    }
}
