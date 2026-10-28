<?php

use App\Http\Controllers\Api\ExpressSmsMessageController;
use App\Http\Controllers\Api\SmsMessageController;
use App\Http\Controllers\Api\WalletChargeController;
use App\Http\Controllers\Api\WalletTransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('consumers/{consumer}')->name('consumers.')->group(function () {
    Route::post('sms-messages', [SmsMessageController::class, 'store'])
        ->name('sms-messages.store');

    Route::post('express-sms-messages', [ExpressSmsMessageController::class, 'store'])
        ->name('express-sms-messages.store');

    Route::post('wallet/charges', [WalletChargeController::class, 'store'])
        ->name('wallet.charges.store');

    Route::get('wallet/transactions', [WalletTransactionController::class, 'index'])
        ->name('wallet.transactions.index');
});
