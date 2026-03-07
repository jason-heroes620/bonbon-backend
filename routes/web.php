<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VendorsController;
use App\Http\Controllers\VouchersController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }
    return redirect()->route('dashboard');
});

Route::get('/login', function () {
    return Inertia::render('login');
})->name('login');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/forgot-password', [AuthController::class, 'resetPasswordLink'])->name('password.email');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/vendors', [VendorsController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/all', [VendorsController::class, 'showAll'])->name('vendors.all');
    Route::get('/vendors/create', [VendorsController::class, 'create'])->name('vendors.create');
    Route::post('/vendors/create', [VendorsController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/{vendor}', [VendorsController::class, 'show'])->name('vendors.show');
    Route::get('/vendors/{vendor}', [VendorsController::class, 'edit'])->name('vendors.edit');
    Route::post('/vendors/{vendor}', [VendorsController::class, 'update'])->name('vendors.update');

    Route::get('/vouchers', [VouchersController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/all', [VouchersController::class, 'showAll'])->name('vouchers.all');
    Route::get('/vouchers/create', [VouchersController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers/create', [VouchersController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}', [VouchersController::class, 'edit'])->name('vouchers.edit');
    Route::post('/vouchers/{voucher}', [VouchersController::class, 'update'])->name('vouchers.update');

});
