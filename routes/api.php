<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\EventCheckInController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\LDAuthController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PushTokensController;
use App\Http\Controllers\Api\VendorsController;
use App\Http\Controllers\Api\VouchersController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\UserInterestListController;
use App\Http\Controllers\Api\UserPetsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RegisterLuckyDrawController;
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
        Route::post('/vouchers/{voucher_id}/claim/{points}', [VouchersController::class, 'claim']);
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

        // Referral
        Route::get('/referrals/{referral_code}', [ReferralController::class, 'referral']);
        Route::post('/referral-gifts/claim', [ReferralController::class, 'claimReferralGift']);
        Route::post('/push-tokens/register', [PushTokensController::class, 'register']);

        // Membership
        Route::get('/memberships', [MembershipController::class, 'membership']);

        // Order Detail
        Route::get('/orders/{refNo}', [PaymentController::class, 'orderDetail']);

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

        // user
        Route::get('/user/{user_id}', [UserController::class, 'show']);
        Route::get('/users/me/points', [UserController::class, 'mePoints']);
        Route::get('/users/me/points/transactions', [UserController::class, 'mePointsTransactions']);

        // Vendor Profile
        Route::get('/merchant/profile', [VendorsController::class, 'merchantProfile']);

        // Vendor Vouchers Redeem
        Route::get('/user-vouchers/{voucher_id}/{user_id}', [VouchersController::class, 'userVoucher']);
        Route::post('/user-vouchers/{voucher_id}/{user_id}/redeem', [VouchersController::class, 'redeem']);

        Route::post('/lucky-draw/register-ticket', [RegisterLuckyDrawController::class, 'registerUser']);
        Route::post('/event-check-in', [EventCheckInController::class, 'checkIn']);
    }
);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/merchant-login', [AuthController::class, 'merchantLogin'])->middleware('throttle:10,1');

Route::post('/auth/google', [SocialAuthController::class, 'google']);
Route::post('/auth/apple', [SocialAuthController::class, 'apple']);

Route::post('/lucky-draw/login', [LDAuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/event-check-in/login', [EventCheckInController::class, 'login'])->middleware('throttle:10,1');


// User Interest List
// Route::post('/user-interest-list/register', [UserInterestListController::class, 'registerInterestList']);
Route::post('/user-interest-list/register', [UserInterestListController::class, 'registerInterestList'])
    ->middleware('throttle:5, 60');

Route::get('/user-interest-list/count', [UserInterestListController::class, 'getListCount']);

Route::post('/payments/backend-callback', [PaymentController::class, 'backendCallback']);
Route::post('/payments/frontend-callback', function (Request $request) {
    $status = $request->Status; // 1 = Success, 0 = Fail
    $userAgent = $request->header('User-Agent');

    if ($request->has('Xfield1') && $request->Xfield1 === 'Events') {
        return redirect()->away("https://bonbon.com.my/api/payments/" . $request->RefNo);
    }

    Log::info('User-Agent: ' . $userAgent);
    $appUrl = ($status == "1")
        ? "bonbon://payment-success/" . urlencode($request->RefNo)
        : "bonbon://payment-failed/" . urlencode($request->RefNo);

    // Return a view instead of a redirect
    return view('payment_redirect', ['appUrl' => $appUrl]);
});

// Route::get('/payments/result', function () {
//     return view('payment_redirect', ['appUrl' => 'bonbon://payment-success/123-45']);
// });


Route::get('/orders/{refNo}', [PaymentController::class, 'orderDetail']);
