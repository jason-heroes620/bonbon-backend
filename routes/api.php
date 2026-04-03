<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PushTokensController;
use App\Http\Controllers\Api\VendorsController;
use App\Http\Controllers\Api\VouchersController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\UserInterestListController;
use App\Http\Controllers\Api\UserPetsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        Route::get('/merchant-vouchers', [VouchersController::class, 'merchantVouchers']);

        // My Vouchers
        Route::get('/my-vouchers', [VouchersController::class, 'myVouchers']);

        // Vendors
        Route::get('/vendors', [VendorsController::class, 'vendors']);
        Route::get('/getVendor/{vendor_id}', [VendorsController::class, 'vendor']);
        Route::get('/vendor-categories', [VendorsController::class, 'vendorCategories']);

        // notifications
        Route::get('/notifications', [NotificationsController::class, 'notifications']);
        Route::post('/notifications/mark-all-read', [NotificationsController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationsController::class, 'markAsRead']);

        // Account
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/account', [AuthController::class, 'destroy']);
        Route::post('/referral-gifts/claim', [AuthController::class, 'claimReferralGift']);
        Route::post('/push-tokens/register', [PushTokensController::class, 'register']);

        // Membership
        Route::get('/memberships', [MembershipController::class, 'membership']);

        // Payment
        Route::post('/payments/create', [PaymentController::class, 'createPayment']);


        // discount
        Route::post('/discounts/validate', [DiscountController::class, 'validateDiscount']);

        // User Pets
        Route::get('/user-pets', [UserPetsController::class, 'index']);
        Route::get('/user-pets/{id}', [UserPetsController::class, 'show']);
        Route::post('/user-pets', [UserPetsController::class, 'store']);
        Route::put('/user-pets/{id}', [UserPetsController::class, 'update']);
        Route::delete('/user-pets/{id}', [UserPetsController::class, 'destroy']);

        // Vendor Profile
        Route::get('/merchant/profile', [VendorsController::class, 'merchantProfile']);

        // Vendor Vouchers Redeem
        Route::get('/user-vouchers/{voucher_id}/{user_id}', [VouchersController::class, 'userVoucher']);
        Route::post('/user-vouchers/{voucher_id}/{user_id}/redeem', [VouchersController::class, 'redeem']);
    }
);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/merchant-login', [AuthController::class, 'merchantLogin'])->middleware('throttle:10,1');

Route::post('/auth/google', [SocialAuthController::class, 'google']);
Route::post('/auth/apple', [SocialAuthController::class, 'apple']);

// User Interest List
// Route::post('/user-interest-list/register', [UserInterestListController::class, 'registerInterestList']);
Route::post('/user-interest-list/register', [UserInterestListController::class, 'registerInterestList'])
    ->middleware('throttle:5, 60');

Route::get('/user-interest-list/count', [UserInterestListController::class, 'getListCount']);

Route::post('/payments/backend-callback', [PaymentController::class, 'backendCallback']);
Route::post('/payments/frontend-callback', function (Request $request) {
    $status = $request->Status; // 1 = Success, 0 = Fail
    $userAgent = $request->header('User-Agent');
    Log::info('User-Agent: ' . $userAgent);
    $appUrl = ($status == "1")
        ? "bonbon://payment-success?refNo=" . urlencode($request->RefNo)
        : "bonbon://payment-failed?refNo=" . urlencode($request->RefNo);

    // Return a view instead of a redirect
    return view('payment_redirect', ['appUrl' => $appUrl]);
    // return redirect()->away('https://bonbon.com.my/payment/result');
});
