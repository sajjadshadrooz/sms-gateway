<?php

namespace App\Http\Requests;

use App\Exceptions\ExpressServiceNotEnabledException;

class SendExpressSmsRequest extends SendSmsRequest
{
    public function authorize(): bool
    {
        return $this->consumer()?->has_express_service ?? true;
    }

    protected function failedAuthorization(): never
    {
        throw new ExpressServiceNotEnabledException($this->consumer()->id);
    }
}
