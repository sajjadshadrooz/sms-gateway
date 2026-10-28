<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Consumer;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletTransactionController extends Controller
{
    public function index(int $consumerId): AnonymousResourceCollection|JsonResponse
    {
        $consumer = Consumer::find($consumerId);

        if ($consumer === null) {
            return $this->consumerNotFound();
        }

        $transactions = WalletTransaction::query()
            ->whereRelation('wallet', 'consumer_id', $consumer->id)
            ->orderByDesc('id')
            ->paginate();

        return WalletTransactionResource::collection($transactions);
    }
}
