<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PublicController;
use App\Http\Controllers\TopUpTransactionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Web\Auth\{
    SessionUserController,
    UserSettingController,
};

use App\Http\Controllers\Web\User\{
    AdminPanelController,
    OperatorDashboardController,
    PassengerDashboardController,
};

//Public Controller
Route::controller(PublicController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/routes', 'routes');
    Route::get('/fares', 'fare');
    // Route::get('/queue', 'queue');
    // Route::get('/queue/partial', 'queuePartial')->name('queue.partial');
});

Route::livewire('/queue', 'pages::queue-page');

//Session Controller
Route::controller(SessionUserController::class)->group(function () {
    // Route::get('/login', 'create')->name('login');
    // Route::post('/login', 'store')->name('login.store');
    Route::post('/logout', 'destroy')->name('logout');
}); 

Route::livewire('/login', 'pages::auth.login')->name('login');

//Pannels
Route::middleware('auth')->group(function () {

    //Admin Section
    Route::get('/admin/dashboard', [AdminPanelController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('/admin/users', [AdminPanelController::class, 'users'])
        ->middleware('role:admin')
        ->name('admin.users');

    Route::livewire('/admin/edit/user/{user}', 'pages::content-by-role.admin.edit-user-info')
        ->name('admin.edit.user');
    Route::get('/admin/register/user', [AdminPanelController::class, 'register'])
        ->name('admin.register.user');
    Route::livewire('/admin/cards', 'pages::content-by-role.admin.cards')
        ->name('admin.cards')
        ->middleware('role:admin');
    Route::livewire('/admin/card/transaction/{user}', 'pages::content-by-role.admin.card-transaction')
        ->name('admin.card.transaction')
        ->middleware('role:admin');

    // Cashier Section
    Route::livewire('/cashier/dashboard', 'pages::content-by-role.cashier.index')
        ->middleware('role:cashier')
        ->name('cashier.dashboard');
        
    Route::livewire('/cashier/queue/vehicle', 'pages::content-by-role.cashier.queue-vehicle')
        ->middleware('role:cashier')
        ->name('cashier.queue.vehicle');

    //Operator Section
    Route::get('/operator/dashboard', [OperatorDashboardController::class, 'index'])
        ->middleware('role:operator')
        ->name('operator.dashboard');

    Route::livewire('/operator/vehicles', 'pages::content-by-role.operator.vehicles')->name('operator.vehicles');
    Route::get('/operator/vehicles/{vehicle}', [OperatorDashboardController::class, 'travelRecord'])->name('operator.travel.record');


    //Passenger Section
    Route::get('/passenger/dashboard',[PassengerDashboardController::class, 'index'])
        ->middleware('role:passenger')
        ->name('passenger.dashboard');
});


//Settings
Route::controller(UserSettingController::class)->group(function () {
    Route::get('/setting/profile', 'profile')->name('profile.edit');
    Route::get('/setting/appearance', 'appearance')->name('appearance.edit');
    Route::get('/setting/security', 'security')->name('security.edit');
});


Route::livewire('/payment/points', 'pages::load_points.options')->name('points.option');
Route::livewire('/tap/card', 'pages::tap_card.tap')->name('tap.card');

Route::livewire('/operator/balance', 'pages::load_points.points_balance')->name('operator.balance');

// Top-up routes
Route::middleware('auth')->group(function () {
    Route::get('/topup/success',   [TopUpTransactionController::class, 'success'])->name('topup.success');
    Route::get('/topup/cancel',    [TopUpTransactionController::class, 'cancel'])->name('topup.cancel');
});

Route::post('/webhook/paymongo', [WebhookController::class, 'handle'])->name('webhook.paymongo');