<?php

use App\Http\Controllers\SmsOperatorApi\ReceivedMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('sms-operator/')->name('consumers.')->group(function () {
    Route::get('receive', [ReceivedMessageController::class, 'index'])
        ->name('sms-operator.transactions.index');
});