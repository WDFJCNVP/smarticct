<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\RfidCardRegistrationController;
use App\Http\Controllers\Api\SkipVehicleController;
use App\Http\Controllers\Webhook\PaymongoController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/cards/tap', [CardController::class, 'tap']);
Route::post('/rfid/tap', [RfidCardRegistrationController::class, 'store']);
Route::post('/queue/skip', [SkipVehicleController::class, 'skip'])
    ->middleware(['auth', 'role:admin, cashier']);

Route::post('/webhooks/paymongo', [PaymongoController::class, 'handleWebhook']);
Route::post('/webhooks/paymongo/disbursement', [PaymongoController::class, 'handleDisbursementWebhook'])
    ->name('webhooks.paymongo.disbursement');