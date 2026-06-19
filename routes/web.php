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
    commuterDashboardController,
};

//Public Controller
Route::controller(PublicController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/routes', 'routes');
    Route::get('/fares', 'fare');
    // Route::get('/queue', 'queue');
    // Route::get('/queue/partial', 'queuePartial')->name('queue.partial');
});

Route::livewire('/queue', 'pages::queue-page')->name('live.queue');

//Session Controller
Route::controller(SessionUserController::class)->group(function () {
    // Route::get('/login', 'create')->name('login');
    // Route::post('/login', 'store')->name('login.store');
    Route::post('/logout', 'destroy')->name('logout');
}); 

Route::livewire('/login', 'pages::auth.login')->name('login');


//Pannels
Route::middleware('auth')->group(function () {

    Route::livewire('/user/card', 'pages::card')
        ->name('user.card');
    Route::livewire('/user/queue', 'pages::queue-page')
        ->name('user.queue');

    Route::livewire('/admin/dashboard', 'pages::content-by-role.admin.index')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::livewire('/admin/users', 'pages::content-by-role.admin.users')
        ->middleware('role:admin')
        ->name('admin.users');

    Route::livewire('/admin/edit/user/{user}', 'pages::content-by-role.admin.edit-user-info')
        ->name('admin.edit.user');

    Route::livewire('/admin/register/user', 'pages::content-by-role.admin.register')
        ->middleware('role:admin')
        ->name('admin.register.user');

    Route::livewire('/admin/cards', 'pages::content-by-role.admin.cards')
        ->name('admin.cards')
        ->middleware('role:admin');
    Route::livewire('/admin/card/transaction/{user}', 'pages::content-by-role.admin.card-transaction')
        ->name('admin.card.transaction')
        ->middleware('role:admin');

    Route::livewire('/admin/travel/record', 'pages::content-by-role.admin.travel-record')
        ->name('admin.travel.record')
        ->middleware('role:admin');

        
    // Cashier Section
    Route::livewire('/cashier/dashboard', 'pages::content-by-role.cashier.index')
        ->middleware('role:cashier')
        ->name('cashier.dashboard');
        
    Route::livewire('/cashier/queue', 'pages::content-by-role.cashier.queue-layout')
        ->middleware('role:cashier')
        ->name('cashier.queue');

    Route::livewire('/cashier/queue/vehicle', 'pages::content-by-role.cashier.queue-vehicle')
        ->middleware('role:cashier')
        ->name('cashier.queue.vehicle');

    Route::livewire('/cashier/active/group', 'pages::content-by-role.cashier.active-group')
        ->middleware('role:cashier')
        ->name('cashier.active-group');

    //Operator Section
    Route::livewire('/operator/dashboard', 'pages::content-by-role.operator.index')
        ->middleware('role:operator')
        ->name('operator.dashboard');

    Route::livewire('/operator/vehicles', 'pages::content-by-role.operator.vehicles')
        ->middleware('role:operator')
        ->name('operator.vehicles');
    Route::livewire('/operator/vehicles/{vehicle}', 'pages::content-by-role.operator.queueing_records')
        ->middleware('role:operator')
        ->name('operator.travel.record');
    
    Route::livewire('/operator/queueing', 'pages::content-by-role.operator.live-queue')
        ->middleware('role:operator')
        ->name('operator.live.queue');
    
    Route::livewire('/operator/queued/vehicle', 'pages::content-by-role.operator.queued-vehicle')
        ->middleware('role:operator')
        ->name('operator.queued.vehicle');

    //commuter Section
    Route::livewire('/commuter/dashboard', 'pages::content-by-role.commuter.index')
        ->middleware('role:commuter')
        ->name('commuter.dashboard');
    Route::livewire('/commuter/travel/record', 'pages::content-by-role.commuter.travel-record')
        ->middleware('role:commuter')
        ->name('commuter.travel.record');

    //Notification
    Route::livewire('/notification', 'pages::notifications')
        ->name('notifications');
    Route::livewire('/notification/{user_notification}', 'pages::notification')
        ->name('notification');

    // Settings
    Route::livewire('/setting/profile', 'pages::profile')
        ->name('profile.edit');
    Route::livewire('/setting/appearance', 'pages::settings.appearance')
        ->name('appearance.edit');
    Route::livewire('/setting/security', 'pages::settings.security')
        ->name('security.edit');

    //Queue
});


//Settings
// Route::controller(UserSettingController::class)->group(function () {
//     Route::get('/setting/profile', 'profile')->name('profile.edit');
//     Route::get('/setting/appearance', 'appearance')->name('appearance.edit');
//     Route::get('/setting/security', 'security')->name('security.edit');
// });


Route::livewire('/payment/points', 'pages::load_points.options')->name('points.option');
Route::livewire('/tap/card', 'pages::tap_card.tap')->name('tap.card');

Route::livewire('/operator/balance', 'pages::load_points.points_balance')->name('operator.balance');

// Top-up routes
Route::middleware('auth')->group(function () {
    Route::get('/topup/success',   [TopUpTransactionController::class, 'success'])->name('topup.success');
    Route::get('/topup/cancel',    [TopUpTransactionController::class, 'cancel'])->name('topup.cancel');
});

Route::post('/webhook/paymongo', [WebhookController::class, 'handle'])->name('webhook.paymongo');