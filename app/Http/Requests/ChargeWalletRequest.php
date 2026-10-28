<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConsumer;
use Illuminate\Foundation\Http\FormRequest;

class ChargeWalletRequest extends FormRequest
{
    use ResolvesConsumer;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
