<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesConsumer;
use Illuminate\Foundation\Http\FormRequest;

class SendSmsRequest extends FormRequest
{
    use ResolvesConsumer;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.regex' => 'The recipient must be a phone number of 10 to 15 digits, optionally prefixed with +.',
        ];
    }
}
