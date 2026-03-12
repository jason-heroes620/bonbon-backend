<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MembershipsController;
use App\Http\Controllers\ProductDiscountsController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\UserController;
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
    Route::get('/vendors/{vendor}', [VendorsController::class, 'edit'])->name('vendors.edit');
    Route::post('/vendors/{vendor}', [VendorsController::class, 'update'])->name('vendors.update');
    Route::get('/getVendorlist', [VendorsController::class, 'getVendorList'])->name('vendors.list');

    Route::get('/vouchers', [VouchersController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/all', [VouchersController::class, 'showAll'])->name('vouchers.all');
    Route::get('/vouchers/create', [VouchersController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers/create', [VouchersController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}', [VouchersController::class, 'edit'])->name('vouchers.edit');
    Route::put('/vouchers/{voucher}', [VouchersController::class, 'update'])->name('vouchers.update');

    Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
    Route::get('/products/all', [ProductsController::class, 'showAll'])->name('products.all');
    Route::get('/products/create', [ProductsController::class, 'create'])->name('products.create');
    Route::post('/products/create', [ProductsController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductsController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');

    Route::get('/product-discounts', [ProductDiscountsController::class, 'index'])->name('product_discounts.index');
    Route::get('/product-discounts/all', [ProductDiscountsController::class, 'showAll'])->name('product_discounts.all');
    Route::get('/product-discounts/create', [ProductDiscountsController::class, 'create'])->name('product_discounts.create');
    Route::post('/product-discounts/create', [ProductDiscountsController::class, 'store'])->name('product_discounts.store');
    Route::get('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'edit'])->name('product_discounts.edit');
    Route::put('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'update'])->name('product_discounts.update');
    Route::delete('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'destroy'])->name('product_discounts.destroy');
    Route::get('/product-discounts/products/search', [ProductDiscountsController::class, 'searchProducts'])->name('product_discounts.products.search');

    Route::get('/memberships', [MembershipsController::class, 'index'])->name('memberships.index');
    Route::get('/memberships/all', [MembershipsController::class, 'showAll'])->name('memberships.all');
    Route::get('/memberships/create', [MembershipsController::class, 'create'])->name('memberships.create');
    Route::post('/memberships/create', [MembershipsController::class, 'store'])->name('memberships.store');
    Route::get('/memberships/{membership}', [MembershipsController::class, 'edit'])->name('memberships.edit');
    Route::put('/memberships/{membership}', [MembershipsController::class, 'update'])->name('memberships.update');
    Route::delete('/memberships/{membership}', [MembershipsController::class, 'destroy'])->name('memberships.destroy');

    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::get('/categories/all', [CategoriesController::class, 'showAll'])->name('categories.all');
    Route::get('/categories/create', [CategoriesController::class, 'create'])->name('categories.create');
    Route::post('/categories/create', [CategoriesController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [CategoriesController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

    Route::get('/taxes', [TaxesController::class, 'index'])->name('taxes.index');
    Route::get('/taxes/all', [TaxesController::class, 'showAll'])->name('taxes.all');
    Route::get('/taxes/create', [TaxesController::class, 'create'])->name('taxes.create');
    Route::post('/taxes/create', [TaxesController::class, 'store'])->name('taxes.store');
    Route::get('/taxes/{tax}', [TaxesController::class, 'edit'])->name('taxes.edit');
    Route::put('/taxes/{tax}', [TaxesController::class, 'update'])->name('taxes.update');
    Route::delete('/taxes/{tax}', [TaxesController::class, 'destroy'])->name('taxes.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/all', [UserController::class, 'showAll'])->name('users.all');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
});
