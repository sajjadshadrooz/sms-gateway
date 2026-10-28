<?php

use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// without midleware for not checking db at first.
Route::get('/ready', ReadinessController::class)->name('ready')->withoutMiddleware('web');
