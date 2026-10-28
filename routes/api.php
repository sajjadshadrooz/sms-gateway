<?php

use App\Http\Controllers\Api\ExpressSmsMessageController;
use App\Http\Controllers\Api\SmsMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('consumers/{consumer}')->name('consumers.')->group(function () {
    Route::post('sms-messages', [SmsMessageController::class, 'store'])
        ->name('sms-messages.store');

    Route::post('express-sms-messages', [ExpressSmsMessageController::class, 'store'])
        ->name('express-sms-messages.store');
});
