<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ChargesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountsController;
use App\Http\Controllers\EvCategoriesController;
use App\Http\Controllers\EventAnalyticsController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventPricingRulesController;
use App\Http\Controllers\EventQuestionnairesController;
use App\Http\Controllers\EventRegistrationsController;
use App\Http\Controllers\MembershipsController;
use App\Http\Controllers\MembershipTypesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\CompartmentController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\DeliveryOrdersController;
use App\Http\Controllers\ProductDiscountsController;
use App\Http\Controllers\ProductPricingTiersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\QuestionTemplatesController;
use App\Http\Controllers\ReferralsController;
use App\Http\Controllers\ReferralReportsController;
use App\Http\Controllers\KolController;
use App\Http\Controllers\RacksController;
use App\Http\Controllers\TenderSummaryReportsController;
use App\Http\Controllers\TendersController;
use App\Http\Controllers\TenderCompartmentsController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\TransactionTypesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInterestListController;
use App\Http\Controllers\VendorsController;
use App\Http\Controllers\VouchersController;
use App\Http\Controllers\LucyDrawEntriesController;
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
Route::match(['get', 'post'], '/contracts/payment-return/{refNo}', [ContractsController::class, 'paymentReturn'])->name('contracts.payment-return');
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verify-email.verify');
Route::get('/forgot-password', function () {
    return Inertia::render('ForgotPassword');
})->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'resetPasswordLink'])->name('password.email');
Route::get('/reset-password/{token}', function (string $token) {
    return Inertia::render('ResetPassword', [
        'token' => $token,
        'email' => request()->query('email'),
    ]);
})->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Vendors
    Route::get('/vendors', [VendorsController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/all', [VendorsController::class, 'showAll'])->name('vendors.all');
    Route::get('/vendors/create', [VendorsController::class, 'create'])->name('vendors.create');
    Route::post('/vendors/create', [VendorsController::class, 'store'])->name('vendors.store');
    Route::get('/vendors/{vendor}', [VendorsController::class, 'edit'])->name('vendors.edit');
    Route::post('/vendors/{vendor}', [VendorsController::class, 'update'])->name('vendors.update');
    Route::get('/getVendorlist', [VendorsController::class, 'getVendorList'])->name('vendors.list');

    // Vouchers
    Route::get('/vouchers', [VouchersController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/all', [VouchersController::class, 'showAll'])->name('vouchers.all');
    Route::get('/vouchers/export', [VouchersController::class, 'export'])->name('vouchers.export');
    Route::get('/vouchers/create', [VouchersController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers/create', [VouchersController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/{voucher}', [VouchersController::class, 'edit'])->name('vouchers.edit');
    Route::put('/vouchers/{voucher}', [VouchersController::class, 'update'])->name('vouchers.update');

    // Lucky Draw
    Route::get('/lucky-draw/sessions', [LucyDrawEntriesController::class, 'sessionsPage'])->name('lucky_draw.sessions');
    Route::get('/lucky-draw/sessions/all', [LucyDrawEntriesController::class, 'sessionsAll'])->name('lucky_draw.sessions_all');
    Route::get('/lucky-draw/sessions/create', [LucyDrawEntriesController::class, 'createSessionPage'])->name('lucky_draw.sessions.create');
    Route::post('/lucky-draw/sessions', [LucyDrawEntriesController::class, 'storeSession'])->name('lucky_draw.sessions.store');
    Route::get('/lucky-draw', [LucyDrawEntriesController::class, 'page'])->name('lucky_draw.page');
    Route::post('/lucky-draw/{sessionId}/prepare', [LucyDrawEntriesController::class, 'prepareEntries'])->name('lucky_draw.prepare');
    Route::post('/lucky-draw/{sessionId}/draw', [LucyDrawEntriesController::class, 'runDraw'])->name('lucky_draw.draw');
    Route::post('/lucky-draw/{sessionId}/complete', [LucyDrawEntriesController::class, 'completeSession'])->name('lucky_draw.complete');
    Route::get('/lucky-draw/{sessionId}/winners', [LucyDrawEntriesController::class, 'winners'])->name('lucky_draw.winners');

    // Events
    Route::get('/events', [EventsController::class, 'index'])->name('events.index');
    Route::get('/events/all', [EventsController::class, 'showAll'])->name('events.all');
    Route::get('/events/create', [EventsController::class, 'create'])->name('events.create');
    Route::post('/events/create', [EventsController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventsController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventsController::class, 'update'])->name('events.update');
    Route::get('/events/{event}/pricing-rules', [EventPricingRulesController::class, 'index'])->name('events.pricing_rules.index');
    Route::post('/events/{event}/pricing-rules', [EventPricingRulesController::class, 'store'])->name('events.pricing_rules.store');
    Route::put('/events/{event}/pricing-rules/{rule}', [EventPricingRulesController::class, 'update'])->name('events.pricing_rules.update');
    Route::delete('/events/{event}/pricing-rules/{rule}', [EventPricingRulesController::class, 'destroy'])->name('events.pricing_rules.destroy');
    Route::get('/events/{event}/questionnaires', [EventQuestionnairesController::class, 'index'])->name('events.questionnaires.index');
    Route::post('/events/{event}/questionnaires/custom', [EventQuestionnairesController::class, 'storeCustom'])->name('events.questionnaires.custom.store');
    Route::post('/events/{event}/questionnaires/attach-templates', [EventQuestionnairesController::class, 'attachTemplates'])->name('events.questionnaires.attach_templates');
    Route::put('/events/{event}/questionnaires/{question}', [EventQuestionnairesController::class, 'update'])->name('events.questionnaires.update');
    Route::delete('/events/{event}/questionnaires/{question}', [EventQuestionnairesController::class, 'destroy'])->name('events.questionnaires.destroy');
    Route::post('/events/{event}/questionnaires/{question}/options', [EventQuestionnairesController::class, 'storeOption'])->name('events.questionnaires.options.store');
    Route::put('/events/{event}/questionnaires/{question}/options/{option}', [EventQuestionnairesController::class, 'updateOption'])->name('events.questionnaires.options.update');
    Route::delete('/events/{event}/questionnaires/{question}/options/{option}', [EventQuestionnairesController::class, 'destroyOption'])->name('events.questionnaires.options.destroy');
    Route::get('/events/{event}/registrations', [EventRegistrationsController::class, 'index'])->name('events.registrations.index');
    Route::get('/events/{event}/analytics', [EventAnalyticsController::class, 'summary'])->name('events.analytics.summary');
    Route::get('/events/{event}/analytics/export', [EventAnalyticsController::class, 'exportAnswers'])->name('events.analytics.export');

    // Question Templates
    Route::get('/question-templates', [QuestionTemplatesController::class, 'index'])->name('question_templates.index');
    Route::get('/question-templates/all', [QuestionTemplatesController::class, 'showAll'])->name('question_templates.all');
    Route::get('/question-templates/list', [QuestionTemplatesController::class, 'list'])->name('question_templates.list');
    Route::get('/question-templates/create', [QuestionTemplatesController::class, 'create'])->name('question_templates.create');
    Route::post('/question-templates/create', [QuestionTemplatesController::class, 'store'])->name('question_templates.store');
    Route::get('/question-templates/{questionTemplate}', [QuestionTemplatesController::class, 'edit'])->name('question_templates.edit');
    Route::put('/question-templates/{questionTemplate}', [QuestionTemplatesController::class, 'update'])->name('question_templates.update');
    Route::delete('/question-templates/{questionTemplate}', [QuestionTemplatesController::class, 'destroy'])->name('question_templates.destroy');

    // Products
    Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
    Route::get('/products/all', [ProductsController::class, 'showAll'])->name('products.all');
    Route::get('/products/create', [ProductsController::class, 'create'])->name('products.create');
    Route::post('/products/create', [ProductsController::class, 'store'])->name('products.store');
    Route::get('/products/{product}', [ProductsController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
    Route::get('/getProductList', [ProductsController::class, 'getProductList'])->name('products.list');
    Route::get('/products/{product}/pricing-tiers', [ProductPricingTiersController::class, 'index'])->name('products.pricing_tiers.index');
    Route::post('/products/{product}/pricing-tiers', [ProductPricingTiersController::class, 'store'])->name('products.pricing_tiers.store');
    Route::put('/products/{product}/pricing-tiers/{tier}', [ProductPricingTiersController::class, 'update'])->name('products.pricing_tiers.update');
    Route::delete('/products/{product}/pricing-tiers/{tier}', [ProductPricingTiersController::class, 'destroy'])->name('products.pricing_tiers.destroy');

    // Product Discounts
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

    // Memberships
    Route::get('/memberships', [MembershipsController::class, 'index'])->name('memberships.index');
    Route::get('/memberships/all', [MembershipsController::class, 'showAll'])->name('memberships.all');
    Route::get('/memberships/create', [MembershipsController::class, 'create'])->name('memberships.create');
    Route::post('/memberships/create', [MembershipsController::class, 'store'])->name('memberships.store');
    Route::get('/memberships/{membership}', [MembershipsController::class, 'edit'])->name('memberships.edit');
    Route::put('/memberships/{membership}', [MembershipsController::class, 'update'])->name('memberships.update');
    Route::delete('/memberships/{membership}', [MembershipsController::class, 'destroy'])->name('memberships.destroy');
    Route::get('/getMembershipList', [MembershipsController::class, 'getMembershipList'])->name('memberships.list');

    // Membership Types
    Route::get('/membership-types', [MembershipTypesController::class, 'index'])->name('membership_types.index');
    Route::get('/membership-types/all', [MembershipTypesController::class, 'showAll'])->name('membership_types.all');
    Route::get('/membership-types/create', [MembershipTypesController::class, 'create'])->name('membership_types.create');
    Route::post('/membership-types/create', [MembershipTypesController::class, 'store'])->name('membership_types.store');
    Route::get('/membership-types/{membershipType}', [MembershipTypesController::class, 'edit'])->name('membership_types.edit');
    Route::put('/membership-types/{membershipType}', [MembershipTypesController::class, 'update'])->name('membership_types.update');

    // Transaction Types
    Route::get('/transaction-types', [TransactionTypesController::class, 'index'])->name('transaction_types.index');
    Route::get('/transaction-types/all', [TransactionTypesController::class, 'showAll'])->name('transaction_types.all');
    Route::get('/transaction-types/create', [TransactionTypesController::class, 'create'])->name('transaction_types.create');
    Route::post('/transaction-types/create', [TransactionTypesController::class, 'store'])->name('transaction_types.store');
    Route::get('/transaction-types/{transactionType}', [TransactionTypesController::class, 'edit'])->name('transaction_types.edit');
    Route::put('/transaction-types/{transactionType}', [TransactionTypesController::class, 'update'])->name('transaction_types.update');

    // Categories
    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::get('/categories/all', [CategoriesController::class, 'showAll'])->name('categories.all');
    Route::get('/categories/create', [CategoriesController::class, 'create'])->name('categories.create');
    Route::post('/categories/create', [CategoriesController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}', [CategoriesController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');
    Route::get('/getCategoryList', [CategoriesController::class, 'getCategoryList'])->name('categories.list');

    // EV Categories
    Route::get('/ev-categories', [EvCategoriesController::class, 'index'])->name('ev_categories.index');
    Route::get('/ev-categories/all', [EvCategoriesController::class, 'showAll'])->name('ev_categories.all');
    Route::get('/ev-categories/create', [EvCategoriesController::class, 'create'])->name('ev_categories.create');
    Route::post('/ev-categories/create', [EvCategoriesController::class, 'store'])->name('ev_categories.store');
    Route::get('/ev-categories/{evCategory}', [EvCategoriesController::class, 'edit'])->name('ev_categories.edit');
    Route::put('/ev-categories/{evCategory}', [EvCategoriesController::class, 'update'])->name('ev_categories.update');
    Route::delete('/ev-categories/{evCategory}', [EvCategoriesController::class, 'destroy'])->name('ev_categories.destroy');
    Route::get('/getEvCategoryList', [EvCategoriesController::class, 'getEvCategoryList'])->name('ev_categories.list');

    // Taxes
    Route::get('/taxes', [TaxesController::class, 'index'])->name('taxes.index');
    Route::get('/taxes/all', [TaxesController::class, 'showAll'])->name('taxes.all');
    Route::get('/taxes/create', [TaxesController::class, 'create'])->name('taxes.create');
    Route::post('/taxes/create', [TaxesController::class, 'store'])->name('taxes.store');
    Route::get('/taxes/{tax}', [TaxesController::class, 'edit'])->name('taxes.edit');
    Route::put('/taxes/{tax}', [TaxesController::class, 'update'])->name('taxes.update');

    // Charges
    Route::get('/charges', [ChargesController::class, 'index'])->name('charges.index');
    Route::get('/charges/all', [ChargesController::class, 'showAll'])->name('charges.all');
    Route::get('/charges/create', [ChargesController::class, 'create'])->name('charges.create');
    Route::post('/charges/create', [ChargesController::class, 'store'])->name('charges.store');
    Route::get('/charges/{charge}', [ChargesController::class, 'edit'])->name('charges.edit');
    Route::put('/charges/{charge}', [ChargesController::class, 'update'])->name('charges.update');
    Route::delete('/charges/{charge}', [ChargesController::class, 'destroy'])->name('charges.destroy');
    Route::delete('/taxes/{tax}', [TaxesController::class, 'destroy'])->name('taxes.destroy');

    // Orders
    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::get('/orders/all', [OrdersController::class, 'showAll'])->name('orders.all');
    Route::get('/orders/create', [OrdersController::class, 'create'])->name('orders.create');
    Route::post('/orders/create', [OrdersController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrdersController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{order}', [OrdersController::class, 'update'])->name('orders.update');

    // Payments
    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
    Route::get('/payments/all', [PaymentsController::class, 'showAll'])->name('payments.all');
    Route::get('/payments/create', [PaymentsController::class, 'create'])->name('payments.create');
    Route::post('/payments/create', [PaymentsController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentsController::class, 'edit'])->name('payments.edit');
    Route::put('/payments/{payment}', [PaymentsController::class, 'update'])->name('payments.update');

    // Delivery Orders
    Route::get('/delivery-orders', [DeliveryOrdersController::class, 'index'])->name('delivery-orders.index');
    Route::get('/delivery-orders/all', [DeliveryOrdersController::class, 'showAll'])->name('delivery-orders.all');
    Route::get('/delivery-orders/{deliveryOrder}', [DeliveryOrdersController::class, 'show'])->name('delivery-orders.show');
    Route::post('/delivery-orders/{deliveryOrder}/confirm', [DeliveryOrdersController::class, 'confirmDelivery'])->name('delivery-orders.confirm');
    Route::get('/delivery-orders/{deliveryOrder}/label', [DeliveryOrdersController::class, 'printLabel'])->name('delivery-orders.label');
    Route::get('/delivery-orders/{deliveryOrder}/tracking', [DeliveryOrdersController::class, 'trackingHistory'])->name('delivery-orders.tracking');
    Route::post('/delivery-orders/{deliveryOrderId}/consignment-no', [DeliveryOrdersController::class, 'getConsignmentNo'])->name('delivery-orders.consignment-no');

    // Racks
    Route::get('/racks', [RacksController::class, 'index'])->name('racks.index');
    Route::get('/racks/all', [RacksController::class, 'showAll'])->name('racks.all');
    Route::get('/racks/create', [RacksController::class, 'create'])->name('racks.create');
    Route::post('/racks/create', [RacksController::class, 'store'])->name('racks.store');
    Route::get('/racks/{rack}', [RacksController::class, 'edit'])->name('racks.edit');
    Route::put('/racks/{rack}', [RacksController::class, 'update'])->name('racks.update');
    Route::delete('/racks/{rack}', [RacksController::class, 'destroy'])->name('racks.destroy');

    // Compartments (per rack)
    Route::get('/racks/{rack}/compartments', [CompartmentController::class, 'edit'])->name('racks.compartments.edit');
    Route::post('/racks/{rack}/compartments', [CompartmentController::class, 'update'])->name('racks.compartments.update');

    // Tenders
    Route::get('/tenders', [TendersController::class, 'index'])->name('tenders.index');
    Route::get('/tenders/all', [TendersController::class, 'showAll'])->name('tenders.all');
    Route::get('/available-racks', [TendersController::class, 'availabilityIndex'])->name('available-racks.index');
    Route::get('/available-racks/all', [TendersController::class, 'availabilityAll'])->name('available-racks.all');
    Route::get('/available-racks/{rack}', [TendersController::class, 'availabilityShow'])->name('available-racks.show');
    Route::post('/available-racks/{rack}/bid', [TendersController::class, 'availabilityBid'])->name('available-racks.bid');
    Route::get('/tenders-summary', [TendersController::class, 'summaryIndex'])->name('tenders-summary.index');
    Route::get('/tenders-summary/all', [TendersController::class, 'summaryAll'])->name('tenders-summary.all');
    Route::get('/tenders-summary/{rack}', [TendersController::class, 'summaryShow'])->name('tenders-summary.show');
    Route::post('/tenders-summary/{rack}/select', [TendersController::class, 'summarySelect'])->name('tenders-summary.select');
    Route::post('/tenders-summary/{rack}/unallocate', [TendersController::class, 'summaryUnallocate'])->name('tenders-summary.unallocate');
    Route::post('/tenders-summary/{rack}/assign-vendor', [TendersController::class, 'summaryAssignVendor'])->name('tenders-summary.assign-vendor');
    Route::post('/tenders-summary/{rack}/manual-allocate', [TendersController::class, 'summaryManualAllocate'])->name('tenders-summary.manual-allocate');
    Route::get('/tenders/create', [TendersController::class, 'create'])->name('tenders.create');
    Route::post('/tenders/create', [TendersController::class, 'store'])->name('tenders.store');
    Route::get('/tenders/{tender}', [TendersController::class, 'edit'])->name('tenders.edit');
    Route::put('/tenders/{tender}', [TendersController::class, 'update'])->name('tenders.update');
    Route::delete('/tenders/{tender}', [TendersController::class, 'destroy'])->name('tenders.destroy');

    // Tender Compartments
    Route::get('/tender-compartments', [TenderCompartmentsController::class, 'index'])->name('tender_compartments.index');
    Route::get('/tender-compartments/all', [TenderCompartmentsController::class, 'showAll'])->name('tender_compartments.all');
    Route::get('/tender-compartments/create', [TenderCompartmentsController::class, 'create'])->name('tender_compartments.create');
    Route::post('/tender-compartments/create', [TenderCompartmentsController::class, 'store'])->name('tender_compartments.store');
    Route::get('/tender-compartments/{tenderCompartment}', [TenderCompartmentsController::class, 'edit'])->name('tender_compartments.edit');
    Route::put('/tender-compartments/{tenderCompartment}', [TenderCompartmentsController::class, 'update'])->name('tender_compartments.update');
    Route::delete('/tender-compartments/{tenderCompartment}', [TenderCompartmentsController::class, 'destroy'])->name('tender_compartments.destroy');

    // Contracts
    Route::get('/contracts', [ContractsController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/all', [ContractsController::class, 'showAll'])->name('contracts.all');
    Route::get('/contracts/{contract}', [ContractsController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{contract}/stocks', [ContractsController::class, 'storeStock'])->name('contracts.stocks.store');
    Route::delete('/contracts/{contract}/stocks/{stock}', [ContractsController::class, 'destroyStock'])->name('contracts.stocks.destroy');
    Route::put('/contracts/{contract}/stocks/{stock}/products/{stockProduct}', [ContractsController::class, 'updateStockProduct'])->name('contracts.stocks.products.update');
    Route::delete('/contracts/{contract}/stocks/{stock}/products/{stockProduct}', [ContractsController::class, 'destroyStockProduct'])->name('contracts.stocks.products.destroy');
    Route::post('/contracts/{contract}/pay', [ContractsController::class, 'pay'])->name('contracts.pay');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/all', [UserController::class, 'showAll'])->name('users.all');
    Route::get('/users/options', [UserController::class, 'options'])->name('users.options');
    Route::get('/getUserList', [UserController::class, 'getUserList'])->name('users.list');
    Route::get('/users/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/referral-code', [UserController::class, 'updateReferralCode'])->name('users.referral_code.update');
    Route::post('/users/{user}/resend-verification', [UserController::class, 'resendVerificationEmail'])->name('users.resend_verification');

    // User Interest List
    Route::get('/user-interest-list', [UserInterestListController::class, 'index'])->name('user_interest_list.index');
    Route::get('/user-interest-list/all', [UserInterestListController::class, 'showAll'])->name('user_interest_list.all');

    // Kol
    Route::get('/kol', [KolController::class, 'index'])->name('kol.index');
    Route::get('/kol/all', [KolController::class, 'showAll'])->name('kol.all');
    Route::get('/kol/{user}', [KolController::class, 'show'])->name('kol.show');
    Route::get('/kol/{user}/referrals/all', [KolController::class, 'referrals'])->name('kol.referrals.all');

    // Notifications
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/all', [NotificationsController::class, 'showAll'])->name('notifications.all');
    Route::get('/notifications/create', [NotificationsController::class, 'create'])->name('notifications.create');
    Route::post('/notifications/create', [NotificationsController::class, 'store'])->name('notifications.store');
    Route::get('/notifications/{notification}', [NotificationsController::class, 'edit'])->name('notifications.edit');
    Route::put('/notifications/{notification}', [NotificationsController::class, 'update'])->name('notifications.update');
    Route::post('/notifications/{notification}/send', [NotificationsController::class, 'send'])->name('notifications.send');
    Route::delete('/notifications/{notification}', [NotificationsController::class, 'destroy'])->name('notifications.destroy');

    // Referrals
    Route::get('/referrals', [ReferralsController::class, 'index'])->name('referrals.index');
    Route::get('/referrals/all', [ReferralsController::class, 'showAll'])->name('referrals.all');

    // Reports
    Route::get('/reports/referral-report', [ReferralReportsController::class, 'index'])->name('reports.referral.index');
    Route::get('/reports/referral-report/users', [ReferralReportsController::class, 'users'])->name('reports.referral.users');
    Route::get('/reports/referral-report/data', [ReferralReportsController::class, 'data'])->name('reports.referral.data');
    Route::get('/reports/tender-summary-report', [TenderSummaryReportsController::class, 'index'])->name('reports.tender-summary.index');
    Route::get('/reports/tender-summary-report/data', [TenderSummaryReportsController::class, 'data'])->name('reports.tender-summary.data');
});
