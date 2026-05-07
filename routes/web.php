<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PublicController;
use App\Http\Controllers\Web\Auth\{
    SessionUserController,
    UserSettingController,
};

use App\Http\Controllers\Web\User\{
    AdminDashboardController,
    OperatorDashboardController,
    PassengerDashboardController,
};

//Public Controller
Route::controller(PublicController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/routes', 'routes');
    Route::get('/fares', 'fare');
    Route::get('/queue', 'queue');
    // Route::get('/queue/partial', 'queuePartial')->name('queue.partial');
});

//Session Controller
Route::controller(SessionUserController::class)->group(function () {
    // Route::get('/login', 'create')->name('login');
    // Route::post('/login', 'store')->name('login.store');
    Route::post('/logout', 'destroy')->name('logout');
}); 

Route::livewire('/login', 'pages::auth.login')->name('login');

//Users Dashboard
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard',    [AdminDashboardController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('/operator/dashboard', [OperatorDashboardController::class, 'index'])
        ->middleware('role:operator')
        ->name('operator.dashboard');

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

