<?php

namespace App\Http\Resources;

use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SmsMessage
 */
class SmsMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'to' => $this->recipient,
            'message' => $this->body,
            'cost' => $this->cost,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
