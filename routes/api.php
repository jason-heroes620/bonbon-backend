<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VendorsController;
use App\Http\Controllers\Api\VouchersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(
    function () {
        // Events
        Route::get('/events', [EventsController::class, 'events']);
        Route::get('/events/{event_id}', [EventsController::class, 'event']);


        // Vouchers
        Route::get('/vouchers', [VouchersController::class, 'vouchers']);
        Route::get('/vouchers/{voucher_id}', [VouchersController::class, 'voucher']);
        Route::post('/vouchers/{voucher_id}/claim', [VouchersController::class, 'claim']);
        Route::get('/vouchers/{voucher_id}/history', [VouchersController::class, 'history']);
        Route::get('/vouchers/{voucher_id}/check-validity', [VouchersController::class, 'checkIfVoucherIsValid']);

        // My Vouchers
        Route::get('/my-vouchers', [VouchersController::class, 'myVouchers']);

        // Vendors
        Route::get('/vendors', [VendorsController::class, 'vendors']);
        Route::get('/getVendor/{vendor_id}', [VendorsController::class, 'vendor']);
        Route::get('/vendor-categories', [VendorsController::class, 'vendorCategories']);

        // Account
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/account', [AuthController::class, 'destroy']);
        Route::post('/referral-gifts/claim', [AuthController::class, 'claimReferralGift']);

        // Membership
        Route::get('/memberships', [MembershipController::class, 'membership']);

        // Payment
        Route::post('/payments/create', [PaymentController::class, 'createPayment']);
        Route::post('/payments/backend-callback', [PaymentController::class, 'backendCallback']);
        Route::post('/payments/frontend-callback', function (Request $request) {
            $status = $request->Status; // 1 = Success, 0 = Fail

            if ($status == "1") {
                return redirect("bonbonc://payment-success?refNo=" . $request->RefNo);
            } else {
                return redirect("bonbonc://payment-failed?refNo=" . $request->RefNo);
            }
        });

        // discount
        Route::post('/discounts/validate', [DiscountController::class, 'validateDiscount']);
    }
);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::post('/auth/google', [SocialAuthController::class, 'google']);
Route::post('/auth/apple', [SocialAuthController::class, 'apple']);
