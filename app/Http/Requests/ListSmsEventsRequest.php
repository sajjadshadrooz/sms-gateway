<?php

namespace App\Http\Requests;

use App\Enums\SmsType;
use App\Http\Requests\Concerns\ResolvesConsumer;
use App\Services\ClickHouse\ClickHouseMessageHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ListSmsEventsRequest extends FormRequest
{
    use ResolvesConsumer;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sms_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['nullable', Rule::in(array_map(fn ($event) => $event->value, ClickHouseMessageHistory::VISIBLE_EVENTS))],
            'type' => ['nullable', Rule::enum(SmsType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): array
    {
        $filters = array_filter($this->safe()->only(['sms_id', 'event', 'type']), fn ($value) => $value !== null);

        foreach (['from', 'to'] as $bound) {
            if ($this->filled($bound)) {
                $filters[$bound] = Carbon::parse($this->input($bound))->utc()->format('Y-m-d H:i:s.v');
            }
        }

        if (isset($filters['sms_id'])) {
            $filters['sms_id'] = (int) $filters['sms_id'];
        }

        return $filters;
    }
}
