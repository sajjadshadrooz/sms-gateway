<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSmsEventsRequest;
use App\Http\Resources\SmsEventResource;
use App\Services\ClickHouse\ClickHouseMessageHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmsEventController extends Controller
{
    public function index(ListSmsEventsRequest $request, ClickHouseMessageHistory $history): AnonymousResourceCollection|JsonResponse
    {
        $consumer = $request->consumer();

        if ($consumer === null) {
            return $this->consumerNotFound();
        }

        $events = $history->forConsumer(
            $consumer->id,
            $request->filters(),
            $request->integer('page', 1),
            $request->integer('per_page', 15),
        )->withPath($request->url())->withQueryString();

        return SmsEventResource::collection($events);
    }
}
