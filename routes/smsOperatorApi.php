<?php

use App\Http\Controllers\SmsOperatorApi\ReceivedMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('sms-operator')->name('sms-operator.')->group(function () {
    Route::post('receive', [ReceivedMessageController::class, 'store'])
        ->name('messages.store');
});
