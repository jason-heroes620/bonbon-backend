<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountsController;
use App\Http\Controllers\EvCategoriesController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\MembershipsController;
use App\Http\Controllers\MembershipTypesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\ProductDiscountsController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ReferralsController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInterestListController;
use App\Http\Controllers\VendorsController;
use App\Http\Controllers\VouchersController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }
    return redirect()->route('dashboard');
});

Route::get('/update-config', function () {
    Artisan::call('config:clear');
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
});

Route::get('/delete-account', [AuthController::class, 'deleteAccount'])->name('delete-account');
Route::post('/delete-account', [AuthController::class, 'requestAccountDeletion'])->name('delete-account.request');

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

    Route::get('/events', [EventsController::class, 'index'])->name('events.index');
    Route::get('/events/all', [EventsController::class, 'showAll'])->name('events.all');
    Route::get('/events/create', [EventsController::class, 'create'])->name('events.create');
    Route::post('/events/create', [EventsController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventsController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventsController::class, 'update'])->name('events.update');

    Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
    Route::get('/products/all', [ProductsController::class, 'showAll'])->name('products.all');
    Route::get('/products/create', [ProductsController::class, 'create'])->name('products.create');
    Route::post('/products/create', [ProductsController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductsController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
    Route::get('/getProductList', [ProductsController::class, 'getProductList'])->name('products.list');

    Route::get('/product-discounts', [ProductDiscountsController::class, 'index'])->name('product_discounts.index');
    Route::get('/product-discounts/all', [ProductDiscountsController::class, 'showAll'])->name('product_discounts.all');
    Route::get('/product-discounts/create', [ProductDiscountsController::class, 'create'])->name('product_discounts.create');
    Route::post('/product-discounts/create', [ProductDiscountsController::class, 'store'])->name('product_discounts.store');
    Route::get('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'edit'])->name('product_discounts.edit');
    Route::put('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'update'])->name('product_discounts.update');
    Route::delete('/product-discounts/{productDiscount}', [ProductDiscountsController::class, 'destroy'])->name('product_discounts.destroy');
    Route::get('/product-discounts/products/search', [ProductDiscountsController::class, 'searchProducts'])->name('product_discounts.products.search');

    // Discounts
    Route::get('/discounts', [DiscountsController::class, 'index'])->name('discounts.index');
    Route::get('/discounts/all', [DiscountsController::class, 'showAll'])->name('discounts.all');
    Route::get('/discounts/create', [DiscountsController::class, 'create'])->name('discounts.create');
    Route::post('/discounts/create', [DiscountsController::class, 'store'])->name('discounts.store');
    Route::get('/discounts/{discount}', [DiscountsController::class, 'edit'])->name('discounts.edit');
    Route::put('/discounts/{discount}', [DiscountsController::class, 'update'])->name('discounts.update');

    Route::get('/memberships', [MembershipsController::class, 'index'])->name('memberships.index');
    Route::get('/memberships/all', [MembershipsController::class, 'showAll'])->name('memberships.all');
    Route::get('/memberships/create', [MembershipsController::class, 'create'])->name('memberships.create');
    Route::post('/memberships/create', [MembershipsController::class, 'store'])->name('memberships.store');
    Route::get('/memberships/{membership}', [MembershipsController::class, 'edit'])->name('memberships.edit');
    Route::put('/memberships/{membership}', [MembershipsController::class, 'update'])->name('memberships.update');
    Route::delete('/memberships/{membership}', [MembershipsController::class, 'destroy'])->name('memberships.destroy');

    Route::get('/membership-types', [MembershipTypesController::class, 'index'])->name('membership_types.index');
    Route::get('/membership-types/all', [MembershipTypesController::class, 'showAll'])->name('membership_types.all');
    Route::get('/membership-types/create', [MembershipTypesController::class, 'create'])->name('membership_types.create');
    Route::post('/membership-types/create', [MembershipTypesController::class, 'store'])->name('membership_types.store');
    Route::get('/membership-types/{membershipType}', [MembershipTypesController::class, 'edit'])->name('membership_types.edit');
    Route::put('/membership-types/{membershipType}', [MembershipTypesController::class, 'update'])->name('membership_types.update');

    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::get('/categories/all', [CategoriesController::class, 'showAll'])->name('categories.all');
    Route::get('/categories/create', [CategoriesController::class, 'create'])->name('categories.create');
    Route::post('/categories/create', [CategoriesController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [CategoriesController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');
    Route::get('/getCategoryList', [CategoriesController::class, 'getCategoryList'])->name('categories.list');

    Route::get('/ev-categories', [EvCategoriesController::class, 'index'])->name('ev_categories.index');
    Route::get('/ev-categories/all', [EvCategoriesController::class, 'showAll'])->name('ev_categories.all');
    Route::get('/ev-categories/create', [EvCategoriesController::class, 'create'])->name('ev_categories.create');
    Route::post('/ev-categories/create', [EvCategoriesController::class, 'store'])->name('ev_categories.store');
    Route::get('/ev-categories/{evCategory}', [EvCategoriesController::class, 'edit'])->name('ev_categories.edit');
    Route::put('/ev-categories/{evCategory}', [EvCategoriesController::class, 'update'])->name('ev_categories.update');
    Route::delete('/ev-categories/{evCategory}', [EvCategoriesController::class, 'destroy'])->name('ev_categories.destroy');
    Route::get('/getEvCategoryList', [EvCategoriesController::class, 'getEvCategoryList'])->name('ev_categories.list');

    Route::get('/taxes', [TaxesController::class, 'index'])->name('taxes.index');
    Route::get('/taxes/all', [TaxesController::class, 'showAll'])->name('taxes.all');
    Route::get('/taxes/create', [TaxesController::class, 'create'])->name('taxes.create');
    Route::post('/taxes/create', [TaxesController::class, 'store'])->name('taxes.store');
    Route::get('/taxes/{tax}', [TaxesController::class, 'edit'])->name('taxes.edit');
    Route::put('/taxes/{tax}', [TaxesController::class, 'update'])->name('taxes.update');
    Route::delete('/taxes/{tax}', [TaxesController::class, 'destroy'])->name('taxes.destroy');

    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::get('/orders/all', [OrdersController::class, 'showAll'])->name('orders.all');
    Route::get('/orders/create', [OrdersController::class, 'create'])->name('orders.create');
    Route::post('/orders/create', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrdersController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{order}', [OrdersController::class, 'update'])->name('orders.update');

    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
    Route::get('/payments/all', [PaymentsController::class, 'showAll'])->name('payments.all');
    Route::get('/payments/create', [PaymentsController::class, 'create'])->name('payments.create');
    Route::post('/payments/create', [PaymentsController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentsController::class, 'edit'])->name('payments.edit');
    Route::put('/payments/{payment}', [PaymentsController::class, 'update'])->name('payments.update');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/all', [UserController::class, 'showAll'])->name('users.all');
    Route::get('/users/options', [UserController::class, 'options'])->name('users.options');
    Route::get('/getUserList', [UserController::class, 'getUserList'])->name('users.list');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/user-interest-list', [UserInterestListController::class, 'index'])->name('user_interest_list.index');
    Route::get('/user-interest-list/all', [UserInterestListController::class, 'showAll'])->name('user_interest_list.all');

    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/all', [NotificationsController::class, 'showAll'])->name('notifications.all');
    Route::get('/notifications/create', [NotificationsController::class, 'create'])->name('notifications.create');
    Route::post('/notifications/create', [NotificationsController::class, 'store'])->name('notifications.store');
    Route::get('/notifications/{notification}', [NotificationsController::class, 'edit'])->name('notifications.edit');
    Route::put('/notifications/{notification}', [NotificationsController::class, 'update'])->name('notifications.update');
    Route::post('/notifications/{notification}/send', [NotificationsController::class, 'send'])->name('notifications.send');
    Route::delete('/notifications/{notification}', [NotificationsController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/referrals', [ReferralsController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/all', [ReferralsController::class, 'showAll'])->name('referrals.all');
});
