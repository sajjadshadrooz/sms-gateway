<?php

namespace App\Http\Controllers\Api;

use App\Actions\ChargeWallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChargeWalletRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;

class WalletChargeController extends Controller
{
    public function store(ChargeWalletRequest $request, ChargeWallet $chargeWallet): JsonResponse
    {
        $consumer = $request->consumer();

        if ($consumer === null) {
            return $this->consumerNotFound();
        }

        $transaction = $chargeWallet->handle(
            $consumer,
            $request->integer('amount'),
            $request->filled('description') ? $request->string('description')->toString() : null,
        );

        return WalletTransactionResource::make($transaction)
            ->additional(['wallet' => WalletResource::make($transaction->wallet)])
            ->response()
            ->setStatusCode(201);
    }
}
