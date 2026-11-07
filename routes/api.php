<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('user', [AuthController::class, 'user'])->name('user');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        require __DIR__.'/pos.php';
    });
});
