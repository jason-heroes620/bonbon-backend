<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompartmentStocksController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\EventCheckInController;
use App\Http\Controllers\Api\EventRsvpController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\LDAuthController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchasePickupController;
use App\Http\Controllers\Api\ProductPricingTiersController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\PushTokensController;
use App\Http\Controllers\Api\RacksController;
use App\Http\Controllers\Api\VendorsController;
use App\Http\Controllers\Api\VouchersController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\UserInterestListController;
use App\Http\Controllers\Api\UserPetsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RegisterLuckyDrawController;
use App\Http\Controllers\UserAddressesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(
    function () {
        // Vouchers
        Route::post('/vouchers/{voucher_id}/claim/{points}', [VouchersController::class, 'claim']);
        Route::get('/vouchers/{voucher_id}/history', [VouchersController::class, 'history']);
        Route::get('/vouchers/{voucher_id}/check-validity', [VouchersController::class, 'checkIfVoucherIsValid']);

        Route::get('/merchant-vouchers', [VouchersController::class, 'merchantVouchers']);

        // My Vouchers
        Route::get('/my-vouchers', [VouchersController::class, 'myVouchers']);

        // notifications
        Route::get('/notifications', [NotificationsController::class, 'notifications']);
        Route::post('/notifications/mark-all-read', [NotificationsController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationsController::class, 'markAsRead']);

        // Account
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me/profile', [AuthController::class, 'updateProfile']);
        Route::put('/me/password', [AuthController::class, 'updatePassword']);
        Route::get('/me/addresses', [UserAddressesController::class, 'index']);
        Route::get('/me/addresses/{user_address_id}', [UserAddressesController::class, 'show']);
        Route::post('/me/addresses', [UserAddressesController::class, 'store']);
        Route::put('/me/addresses/{user_address_id}', [UserAddressesController::class, 'update']);
        Route::delete('/me/addresses/{user_address_id}', [UserAddressesController::class, 'destroy']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::delete('/account', [AuthController::class, 'destroy']);

        // Referral
        Route::get('/referrals/{referral_code}', [ReferralController::class, 'referral']);
        Route::post('/referral-gifts/claim', [ReferralController::class, 'claimReferralGift']);
        Route::post('/push-tokens/register', [PushTokensController::class, 'register'])->middleware('throttle:30,1');
        Route::post('/user/device-token', [PushTokensController::class, 'deviceToken'])->middleware('throttle:30,1');

        // Membership
        Route::get('/memberships', [MembershipController::class, 'membership']);

        // Order Detail
        Route::get('/orders/{refNo}', [PaymentController::class, 'orderDetail']);
        Route::get('/my-purchases', [PurchasePickupController::class, 'index']);
        Route::get('/my-purchases/{order_pickup_id}', [PurchasePickupController::class, 'show']);

        // Payment
        Route::post('/pricing/quote', [PaymentController::class, 'quotePricing']);
        Route::post('/payments/create', [PaymentController::class, 'createPayment']);

        // Cart / Mixed Checkout
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/voucher', [CartController::class, 'applyVoucher']);
        Route::post('/cart/items', [CartController::class, 'upsertItem']);
        Route::delete('/cart/items/{cart_item_id}', [CartController::class, 'removeItem']);
        Route::post('/cart/checkout', [CartController::class, 'checkout']);

        // Event RSVP (Questionnaire + Seat Hold)
        Route::get('/events/{event_id}/questionnaires', [EventRsvpController::class, 'questionnaires']);
        Route::get('/events/{event_id}/registration', [EventRsvpController::class, 'showRegistration']);
        Route::post('/events/{event_id}/rsvp/start', [EventRsvpController::class, 'start']);
        Route::post('/events/{event_id}/rsvp/answers', [EventRsvpController::class, 'submitAnswers']);
        Route::get('/events/{event_id}/check-in-qr', [EventCheckInController::class, 'showQr']);
        Route::get('/event-check-in/events', [EventCheckInController::class, 'listEvents']);

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

        Route::post('/user/trial', [UserController::class, 'trial']);

        // Vendor Profile
        Route::get('/merchant/profile', [VendorsController::class, 'merchantProfile']);

        Route::get('/products/{product_id}/pricing-tiers', [ProductPricingTiersController::class, 'index']);
        Route::post('/products/{product_id}/pricing-tiers', [ProductPricingTiersController::class, 'store']);
        Route::put('/products/{product_id}/pricing-tiers/{product_pricing_tier_id}', [ProductPricingTiersController::class, 'update']);
        Route::delete('/products/{product_id}/pricing-tiers/{product_pricing_tier_id}', [ProductPricingTiersController::class, 'destroy']);

        // Vendor Vouchers Redeem
        Route::get('/user-vouchers/{voucher_id}/{user_id}', [VouchersController::class, 'userVoucher']);
        Route::post('/user-vouchers/{voucher_id}/{user_id}/redeem', [VouchersController::class, 'redeem']);

        Route::post('/lucky-draw/register-ticket', [RegisterLuckyDrawController::class, 'registerUser']);
        Route::post('/event-check-in/validate', [EventCheckInController::class, 'validateQr']);
        Route::post('/event-check-in/{event_registration_id}/confirm', [EventCheckInController::class, 'confirm']);

        // vendor location racks
        Route::get('/vendors/{vendor_id}/racks', [RacksController::class, 'vendorRacks']);
        Route::get('/vendors/{vendor_id}/compartment-stocks/prepared', [RacksController::class, 'vendorPreparedCompartmentStocks']);
        Route::get('/racks/{rack_id}/stock-products', [RacksController::class, 'rackStockProducts']);
        Route::post(
            '/vendors/{vendor_id}/compartment-stocks/{compartment_stock_product_id}/qr-sessions',
            [CompartmentStocksController::class, 'storeQrSession']
        );
        Route::post(
            '/vendors/{vendor_id}/compartment-stocks/{compartment_stock_product_id}/remove',
            [CompartmentStocksController::class, 'removeStockProduct']
        );
        Route::get(
            '/compartment-stock-qr-sessions/{compartment_stock_qr_session_id}',
            [CompartmentStocksController::class, 'showQrSession']
        );
        Route::post(
            '/compartment-stock-qr-sessions/{compartment_stock_qr_session_id}/revoke',
            [CompartmentStocksController::class, 'revokeQrSession']
        );
        Route::post(
            '/rack-owner/compartment-stock-qr/validate',
            [CompartmentStocksController::class, 'validateQr']
        );
        Route::post(
            '/rack-owner/compartment-stock-qr/confirm',
            [CompartmentStocksController::class, 'confirmReceive']
        );
        Route::post('/merchant/pickups/validate', [PurchasePickupController::class, 'validateQr']);
        Route::post('/merchant/pickups/{order_pickup_id}/confirm', [PurchasePickupController::class, 'confirm']);
        Route::get(
            '/rack-owner/compartment-stock-transactions',
            [CompartmentStocksController::class, 'history']
        );
        Route::get(
            '/admin/compartment-stock-transactions',
            [CompartmentStocksController::class, 'adminIndex']
        );
        Route::get(
            '/admin/compartment-stock-transactions/{stock_product_transaction_id}',
            [CompartmentStocksController::class, 'adminShow']
        );
        Route::post(
            '/admin/compartment-stock-products/purchase',
            [CompartmentStocksController::class, 'adminRecordPurchase']
        );
    }
);

Route::middleware('guest')->group(
    function () {
        // Events
        Route::get('/events', [EventsController::class, 'events']);
        Route::get('/events/{event_id}', [EventsController::class, 'event']);

        Route::get('/vouchers', [VouchersController::class, 'vouchers']);
        Route::get('/vouchers/{voucher_id}', [VouchersController::class, 'voucher']);

        // Vendors
        Route::get('/vendors', [VendorsController::class, 'vendors']);
        Route::get('/getVendor/{vendor_id}', [VendorsController::class, 'vendor']);
        Route::get('/vendor-categories', [VendorsController::class, 'vendorCategories']);

        // Products
        Route::get('/products', [ProductsController::class, 'index']);
        Route::get('/products/{product_id}', [ProductsController::class, 'show']);
        Route::get('/product-categories', [ProductsController::class, 'categories']);
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
        return redirect()->away("https://events.bonbon.com.my/api/payments/" . $request->RefNo);
    }
    if ($request->has('Xfield1') && $request->Xfield1 === 'Contracts') {
        return redirect()->away("https://merchant.bonbon.com.my/contracts/payment-return/" . $request->RefNo . "?status=" . $status);
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
