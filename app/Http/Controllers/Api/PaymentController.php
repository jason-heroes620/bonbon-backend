<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CompartmentStockProductTransaction;
use App\Models\DiscountProducts;
use App\Models\Discounts;
use App\Models\EventRegistration;
use App\Models\Memberships;
use App\Models\OrderItems;
use App\Models\OrderPickup;
use App\Models\OrderPickupItem;
use App\Models\Orders;
use App\Models\Payments;
use App\Models\Products;
use App\Models\ReferralEarnings;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\TenderCompartments;
use App\Models\Taxes;
use App\Models\TransactionTypes;
use App\Models\User;
use App\Models\UserReferralGifts;
use App\Models\UserMemberships;
use App\Services\CreditService;
use App\Services\ProductPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class PaymentController extends Controller
{
    protected $tier_one  = 5;
    protected $tier_two  = 10;

    protected CreditService $creditService;
    protected ProductPricingService $pricingService;

    public function __construct(CreditService $creditService, ProductPricingService $pricingService)
    {
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    public function quotePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_charges' => ['nullable', 'numeric', 'min:0'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'promo_discount' => ['nullable', 'numeric', 'min:0'],
            'wallet_credit_used' => ['nullable', 'numeric', 'min:0'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.uom' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pricing = $this->calculateOrderPricing($request);
        if (!$pricing['ok']) {
            return response()->json([
                'message' => $pricing['message'] ?? 'Unable to calculate pricing.',
                'errors' => $pricing['errors'] ?? null,
            ], 422);
        }

        $amountRaw = number_format((float) $pricing['totals']['total_payment'], 2, '.', '');

        return response()->json([
            'data' => [
                'items' => $pricing['items'],
                'totals' => $pricing['totals'],
                'amount' => $amountRaw,
                'currency' => 'MYR',
            ],
        ]);
    }

    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['nullable', 'numeric', 'min:0'],
            'product' => ['nullable', 'string', 'max:255'],
            'total_charges' => ['nullable', 'numeric', 'min:0'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'promo_discount' => ['nullable', 'numeric', 'min:0'],
            'total_payment' => ['nullable', 'numeric', 'min:0'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'wallet_credit_used' => ['nullable', 'numeric', 'min:0'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.uom' => ['required', 'string', 'max:50'],
            'products.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.tax' => ['nullable', 'numeric', 'min:0'],
            'products.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'products.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            Log::info('Payment validation failed');
            Log::info($validator->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchantCode = config('services.ipay88.code');
        $merchantKey = config('services.ipay88.key');

        $refNo = $this->generateOrderNo();
        $currency = "MYR";

        $pricingBaseRequest = $request->duplicate();
        $pricingBaseRequest->merge([
            'discount_value' => 0,
            'promo_discount' => 0,
        ]);

        $pricingBase = $this->calculateOrderPricing($pricingBaseRequest);
        if (!$pricingBase['ok']) {
            return response()->json([
                'message' => $pricingBase['message'] ?? 'Unable to calculate pricing.',
                'errors' => $pricingBase['errors'] ?? null,
            ], 422);
        }

        $promoDiscount = 0.0;
        $discountCode = trim((string) ($request->input('discount_code') ?? ''));
        if ($discountCode !== '') {
            $discountResult = $this->resolvePromoDiscountFromCode(
                $discountCode,
                (float) $pricingBase['totals']['total_payment'],
                $request->input('products', []),
            );
            if (!$discountResult['ok']) {
                return response()->json([
                    'message' => $discountResult['message'] ?? 'Invalid discount code.',
                ], 422);
            }
            $promoDiscount = (float) ($discountResult['discount_value'] ?? 0);
        }

        $pricingRequest = $request->duplicate();
        $pricingRequest->merge([
            'discount_value' => 0,
            'promo_discount' => $promoDiscount,
        ]);

        $pricing = $this->calculateOrderPricing($pricingRequest);
        if (!$pricing['ok']) {
            return response()->json([
                'message' => $pricing['message'] ?? 'Unable to calculate pricing.',
                'errors' => $pricing['errors'] ?? null,
            ], 422);
        }

        if (((float) $pricing['totals']['total_payment']) <= 0) {
            return response()->json([
                'message' => 'Total payment must be greater than 0.',
            ], 422);
        }

        $amountRaw = number_format((float) $pricing['totals']['total_payment'], 2, '.', '');
        $amount = str_replace(['.', ','], '', $amountRaw);

        $payload = $merchantKey . $merchantCode . $refNo . $amount . $currency;

        // cannot change this algorithm, important
        $signature = hash_hmac('sha512', $payload, $merchantKey);

        DB::transaction(function () use ($request, $refNo, $pricing, $discountCode) {
            $totalPayment = (float) $pricing['totals']['total_payment'];
            $totalPrice = (float) $pricing['totals']['subtotal'];
            $totalCharges = (float) $pricing['totals']['total_charges'];
            $totalDiscount = (float) $pricing['totals']['total_discount'];
            $walletCreditUsed = (float) $pricing['totals']['wallet_credit_used'];

            $order = Orders::create([
                'user_id' => $request->user()->user_id,
                'order_no' => $refNo,
                'order_date' => now()->toDateString(),
                'order_description' => $request->input('product') ?? '',
                'total_price' => $totalPrice,
                'total_charges' => $totalCharges,
                'total_discount' => $totalDiscount,
                'total_payment' => $totalPayment,
                'shipping_method' => $request->input('shipping_method') ?? null,
                'shipping_address' => $request->input('shipping_address') ?? null,
                'billing_address' => $request->input('billing_address') ?? null,
                'discount_code' => $discountCode !== '' ? $discountCode : null,
                'wallet_credit_used' => $walletCreditUsed,
                'order_status' => 'pending',
            ]);

            foreach ($pricing['items'] as $item) {
                OrderItems::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'line_type' => 'product',
                    'source_id' => $item['product_id'],
                    'line_name' => (string) ($item['product_name'] ?? ''),
                    'line_description' => null,
                    'quantity' => (int) $item['quantity'],
                    'uom' => $item['uom'],
                    'unit_price' => (float) $item['unit_price'],
                    'tax' => (float) $item['tax'],
                    'discount' => (float) $item['discount'],
                    'total_price' => (float) $item['total_price'],
                ]);
            }
        });

        $prodDesc = trim((string) ($request->input('product') ?? ''));
        if ($prodDesc === '') {
            $prodDesc = 'BonBon Checkout';
        }

        return response()->json([
            'merchantCode' => $merchantCode,
            'refNo'        => $refNo,
            'amount'       => $amountRaw,
            'currency'     => $currency,
            'signature'    => $signature,
            'prodDesc'     => $prodDesc,
            'userName'     => $request->user()->last_name . " " . $request->user()->first_name,
            'userEmail'    => $request->user()->email,
            'responseUrl'  => url('/api/payments/frontend-callback'),
            'backendUrl'   => url('/api/payments/backend-callback'),
            // 'responseUrl'  => 'https://generically-mediatorial-sharen.ngrok-free.dev/api/payments/frontend-callback',
            // 'backendUrl'   => 'https://generically-mediatorial-sharen.ngrok-free.dev/api/payments/backend-callback',
        ]);
    }

    private function resolvePromoDiscountFromCode(string $code, float $amount, array $products): array
    {
        $code = trim($code);
        if ($code === '') {
            return [
                'ok' => true,
                'discount_value' => 0.0,
            ];
        }

        $discount = Discounts::query()
            ->where('discount_code', $code)
            ->first();

        if (!$discount) {
            return [
                'ok' => false,
                'message' => 'Discount code not found',
            ];
        }

        if (!$discount->is_active) {
            return [
                'ok' => false,
                'message' => 'Discount code is inactive',
            ];
        }

        $today = now()->toDateString();
        if ((string) $discount->discount_start_date > $today || (string) $discount->discount_end_date < $today) {
            return [
                'ok' => false,
                'message' => 'Discount code is not valid for this date',
            ];
        }

        if (!$discount->is_unlimited) {
            $usageLimit = (int) ($discount->discount_usage_limit ?? 0);
            if ($usageLimit > 0) {
                $completedUsages = Orders::query()
                    ->where('discount_code', $discount->discount_code)
                    ->where('order_status', 'completed')
                    ->count();

                if ($completedUsages >= $usageLimit) {
                    return [
                        'ok' => false,
                        'message' => 'Usage limit reached',
                    ];
                }
            }
        }

        $productIds = collect($products)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (($discount->applies_to ?? 'all') !== 'all') {
            $eligible = DiscountProducts::query()
                ->where('discount_id', $discount->discount_id)
                ->whereIn('product_id', $productIds)
                ->exists();

            if (!$eligible) {
                return [
                    'ok' => true,
                    'discount_value' => 0.0,
                ];
            }
        }

        $originalAmount = max(0.0, round($amount, 2));
        $discountAmount = (float) ($discount->discount_amount ?? 0);

        if ((string) $discount->discount_type === 'P') {
            $finalAmount = $originalAmount - ($originalAmount * ($discountAmount / 100));
        } else {
            $finalAmount = $originalAmount - $discountAmount;
        }

        if ($finalAmount < 0) {
            $finalAmount = 0;
        }

        return [
            'ok' => true,
            'discount_value' => round($originalAmount - $finalAmount, 2),
        ];
    }

    private function calculateOrderPricing(Request $request): array
    {
        $items = $request->input('products', []);
        if (!is_array($items) || count($items) === 0) {
            return [
                'ok' => false,
                'message' => 'Products are required.',
            ];
        }

        $totalCharges = (float) ($request->input('total_charges') ?? 0);
        $walletCreditUsed = (float) ($request->input('wallet_credit_used') ?? 0);
        $orderLevelDiscount = (float) (($request->input('discount_value') ?? 0) + ($request->input('promo_discount') ?? 0));

        $subtotal = 0.0;
        $taxTotal = 0.0;
        $productDiscountTotal = 0.0;
        $resolvedItems = [];
        $errors = [];

        foreach ($items as $index => $line) {
            $productId = (string) ($line['product_id'] ?? '');
            $quantity = (int) ($line['quantity'] ?? 0);
            $uom = (string) ($line['uom'] ?? 'unit');

            if ($productId === '' || $quantity <= 0) {
                $errors["products.$index"] = ['Invalid product line.'];
                continue;
            }

            $product = Products::query()->where('product_id', $productId)->first();
            if (!$product || !$product->is_active) {
                $errors["products.$index.product_id"] = ['Product is inactive or not found.'];
                continue;
            }

            $pricing = $this->pricingService->resolvePricing($product, $quantity);
            $unitPrice = (float) $pricing['final_unit_price'];
            $lineDiscount = (float) $pricing['discount_total'];

            $lineSubtotal = round($unitPrice * $quantity, 2);

            $taxRate = 0.0;
            if ($product->is_taxable) {
                $taxRow = Taxes::query()->where('tax_rate_id', $product->tax_rate_id)->first();
                $taxRate = $taxRow ? (float) $taxRow->tax_rate : 0.0;
            }

            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $productDiscountTotal += $lineDiscount;

            $resolvedItems[] = [
                'product_id' => $productId,
                'product_name' => (string) $product->product_name,
                'quantity' => $quantity,
                'uom' => $uom,
                'unit_price' => round($unitPrice, 2),
                'discount' => round($lineDiscount, 2),
                'tax' => round($lineTax, 2),
                'total_price' => round($lineTotal, 2),
            ];
        }

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => 'Pricing validation failed.',
                'errors' => $errors,
            ];
        }

        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);
        $productDiscountTotal = round($productDiscountTotal, 2);
        $orderLevelDiscount = round($orderLevelDiscount, 2);
        $totalCharges = round($totalCharges, 2);
        $walletCreditUsed = round($walletCreditUsed, 2);

        $totalDiscount = round($productDiscountTotal + $orderLevelDiscount, 2);
        $preWalletTotal = round($subtotal + $taxTotal + $totalCharges - $orderLevelDiscount, 2);
        if ($preWalletTotal < 0) {
            $preWalletTotal = 0;
        }

        $totalPayment = round($preWalletTotal - $walletCreditUsed, 2);
        if ($totalPayment < 0) {
            $totalPayment = 0;
        }

        return [
            'ok' => true,
            'items' => $resolvedItems,
            'totals' => [
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'product_discount_total' => $productDiscountTotal,
                'order_level_discount' => $orderLevelDiscount,
                'total_discount' => $totalDiscount,
                'total_charges' => $totalCharges,
                'wallet_credit_used' => $walletCreditUsed,
                'total_payment' => $totalPayment,
            ],
        ];
    }

    // This handles the Server-to-Server notification
    public function backendCallback(Request $request)
    {
        if ($request->has('Xfield1') && $request->Xfield1 === 'Events') {

            // 1. Silent API-to-API POST request
            $apiResponse = Http::post('https://events.bonbon.com.my/api/payments/backend', $request->all());
            Log::info('post to events');

            // 2. Check if the second server accepted the data successfully
            if ($apiResponse->successful()) {
                Log::info('return success');
                return response("RECEIVEOK")->header('Content-Type', 'text/plain');
            }

            // Handle API failures gracefully
            return response("FAILED")->header('Content-Type', 'text/plain');
        }

        if ($request->has('Xfield1') && $request->Xfield1 === 'Contracts') {
            return $this->handleContractsCallback($request);
        }

        try {
            if (!$this->verifySignature($request) || (string) $request->Status !== "1") {
                return response("FAILED")->header('Content-Type', 'text/plain');
            }

            $order = Orders::query()->where('order_no', $request->RefNo)->first();
            if (!$order) {
                return response("FAILED")->header('Content-Type', 'text/plain');
            }

            $pendingCart = Cart::query()
                ->where('user_id', $order->user_id)
                ->where('cart_status', 'pending_payment')
                ->orderByDesc('created_at')
                ->first();

            if ($pendingCart) {
                $pendingCart->update([
                    'cart_status' => 'checked_out',
                ]);
            }

            $payment = Payments::updateOrCreate(
                [
                    'order_no' => $request->RefNo,
                ],
                [
                    'order_no' => $request->RefNo,
                    'ref_no' => $request->RefNo,
                    'transaction_id' => $request->TransId,
                    'payment_amount' => (float) $request->Amount,
                    'payment_date' => $request->TranDate,
                    'issuing_bank' => $request->S_bankname,
                    'cc_name' => $request->CCName,
                    'cc_number' => $request->CCNo,
                    'payment_status' => $request->Status,
                ]
            );

            $pickupCreated = false;
            if ($pendingCart) {
                $pickupCreated = $this->fulfillProductPickupOrder($order, $pendingCart, (string) $order->user_id);
            }

            $order->update([
                'order_status' => $pickupCreated ? 'processing' : 'completed',
            ]);

            EventRegistration::query()
                ->where('order_id', $order->order_id)
                ->whereIn('registration_status', ['pending_payment'])
                ->update([
                    'registration_status' => 'confirmed',
                    'confirmed_at' => now(),
                    'payment_id' => $payment->payment_id ?? null,
                ]);

            $orderItems = OrderItems::query()
                ->where('order_id', $order->order_id)
                ->get();

            foreach ($orderItems as $item) {
                if (empty($item->product_id)) {
                    continue;
                }
                $product = Products::query()->where('product_id', $item->product_id)->first();
                if (!$product) {
                    continue;
                }

                $membership = Memberships::query()
                    ->where('membership_code', $product->product_code)
                    ->first();

                if (!$membership) {
                    continue;
                }

                $user = User::query()->where('user_id', $order->user_id)->first();
                if (!$user) {
                    continue;
                }

                if (empty($user->referral_code)) {
                    $referralCode = $this->generateReferralCode();
                    ReferralCodes::firstOrCreate([
                        'user_id' => $user->user_id,
                    ], [
                        'campaign_name' => 'Default',
                        'referral_code' => $referralCode,
                        'code_effective_date' => now()->toDateString(),
                        'code_expiry_date' => now()->addYear()->toDateString(),
                        'usage_count' => 0,
                        'max_usage' => 0,
                        'is_active' => true,
                    ]);

                    User::query()
                        ->where('user_id', $user->user_id)->update([
                            'referral_code' => $referralCode,
                        ]);
                }

                // update user membership to inactive before creating new one
                UserMemberships::query()
                    ->where('user_id', $user->user_id)
                    ->where('membership_status', 'active')
                    ->update([
                        'membership_status' => 'inactive',
                        'membership_end_date' => now()->subDay(1)->toDateString(),
                    ]);
                UserMemberships::create([
                    'user_id' => $user->user_id,
                    'membership_id' => $membership->membership_id,
                    'membership_status' => 'active',
                    'membership_start_date' => now()->toDateString(),
                    'membership_end_date' => now()->addYear()->subDay(1)->toDateString(),
                ]);

                // add credits to user
                $credit = TransactionTypes::query()
                    ->where('transaction_type', 'standard_membership')->first();
                $this->creditService
                    ->addCredits($user, $credit->credit_amount, $credit->transaction_name, null, 'Purchased membership:' . $membership->membership_description);

                $referral = Referrals::query()
                    ->where('referee_id', $user->user_id)
                    ->where('referral_status', 'pending')
                    ->first();

                if (!$referral) {
                    continue;
                }

                $referral->update([
                    'referral_status' => 'qualified',
                    'qualified_at' => now()->toDateString(),
                    'qualifying_order_no' => $request->RefNo,
                ]);

                $referrerUserId = $referral->user_id;
                $referrerMembershipType = $this->getUserMembershipType($referrerUserId);
                $isUnlimitedReferrer = in_array(
                    strtoupper((string) $referrerMembershipType),
                    ['KOL', 'FOBB'],
                    true
                );

                if ($isUnlimitedReferrer) {
                    DB::transaction(function () use ($referrerUserId, $referral) {
                        $existing = ReferralEarnings::query()
                            ->where('referral_id', $referral->referral_id)
                            ->lockForUpdate()
                            ->first();
                        if ($existing) {
                            return;
                        }

                        $now = now();
                        $month = (int) $now->format('n');
                        $year = (int) $now->format('Y');

                        $currentCount = (int) ReferralEarnings::query()
                            ->where('user_id', $referrerUserId)
                            ->where('month', $month)
                            ->where('year', $year)
                            ->lockForUpdate()
                            ->count();

                        $nextCount = $currentCount + 1;
                        $amount = $nextCount <= 50 ? $this->tier_one : $this->tier_two;

                        ReferralEarnings::create([
                            'user_id' => $referrerUserId,
                            'referral_id' => $referral->referral_id,
                            'month' => $month,
                            'year' => $year,
                            'amount' => $amount,
                        ]);
                    });
                }

                $qualifiedCount = Referrals::query()
                    ->where('user_id', $referrerUserId)
                    ->whereIn('referral_status', ['qualified', 'rewarded'])
                    ->count();

                $targetGiftsEarned = intdiv($qualifiedCount, 5);
                if (!$isUnlimitedReferrer) {
                    $targetGiftsEarned = min($targetGiftsEarned, 2);
                }

                $currentGiftsEarned = (int) UserReferralGifts::query()
                    ->where('user_id', $referrerUserId)
                    ->count();

                if ($targetGiftsEarned > $currentGiftsEarned) {
                    $delta = $targetGiftsEarned - $currentGiftsEarned;
                    for ($i = 0; $i < $delta; $i++) {
                        UserReferralGifts::create([
                            'user_id' => $referrerUserId,
                            'earned_at' => now(),
                            'claimed_at' => null,
                        ]);
                    }
                }
            }

            return response("RECEIVEOK")->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response("FAILED")->header('Content-Type', 'text/plain');
        }
    }

    private function handleContractsCallback(Request $request)
    {
        try {
            if (!$this->verifySignature($request) || (string) $request->Status !== "1") {
                return response("FAILED")->header('Content-Type', 'text/plain');
            }

            $order = Orders::query()->where('order_no', $request->RefNo)->first();
            $contractId = (string) ($request->Xfield2 ?? '');

            if (!$order || $contractId === '') {
                return response("FAILED")->header('Content-Type', 'text/plain');
            }

            $contract = TenderCompartments::query()
                ->where('tender_compartment_id', $contractId)
                ->first();

            if (!$contract) {
                return response("FAILED")->header('Content-Type', 'text/plain');
            }

            DB::transaction(function () use ($request, $order, $contract) {
                $order->update([
                    'order_status' => 'completed',
                ]);

                Payments::updateOrCreate(
                    [
                        'order_no' => $request->RefNo,
                    ],
                    [
                        'order_no' => $request->RefNo,
                        'ref_no' => $request->RefNo,
                        'transaction_id' => $request->TransId,
                        'payment_amount' => (float) $request->Amount,
                        'payment_date' => $request->TranDate,
                        'issuing_bank' => $request->S_bankname,
                        'cc_name' => $request->CCName,
                        'cc_number' => $request->CCNo,
                        'payment_status' => $request->Status,
                        'payment_description' => 'Contract payment: ' . (string) $contract->tender_compartment_id,
                    ]
                );

                $startDate = now();
                $endDate = now()->copy()->addMonthsNoOverflow((int) $contract->durations);

                $contract->update([
                    'tender_status' => 'paid',
                    'tender_start_date' => $startDate,
                    'tender_end_date' => $endDate,
                ]);
            });

            return response("RECEIVEOK")->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response("FAILED")->header('Content-Type', 'text/plain');
        }
    }

    private function fulfillProductPickupOrder(Orders $order, Cart $pendingCart, string $actorUserId): bool
    {
        $cartItems = CartItem::query()
            ->where('cart_id', $pendingCart->cart_id)
            ->where('line_type', 'product')
            ->get()
            ->keyBy('source_id');

        if ($cartItems->isEmpty()) {
            return false;
        }

        $orderItems = OrderItems::query()
            ->where('order_id', $order->order_id)
            ->where('line_type', 'product')
            ->get();

        if ($orderItems->isEmpty()) {
            return false;
        }

        $firstPickupMeta = null;
        foreach ($orderItems as $orderItem) {
            $cartItem = $cartItems->get((string) $orderItem->source_id);
            $meta = (array) ($cartItem?->metadata_json ?? []);
            if (!empty($meta['compartment_stock_product_id']) && !empty($meta['vendor_location_id'])) {
                $firstPickupMeta = $meta;
                break;
            }
        }

        if (!$firstPickupMeta) {
            return false;
        }

        $pickup = OrderPickup::query()->firstOrCreate(
            ['order_id' => $order->order_id],
            [
                'user_id' => (string) $order->user_id,
                'vendor_id' => (string) ($firstPickupMeta['vendor_id'] ?? ''),
                'vendor_location_id' => (int) $firstPickupMeta['vendor_location_id'],
                'fulfillment_method' => 'pickup',
                'pickup_status' => 'pending_pickup',
                'pickup_code' => strtoupper(Str::random(12)),
                'pickup_payload_json' => null,
                'pickup_signature_hash' => null,
                'qr_issued_at' => now(),
            ]
        );

        foreach ($orderItems as $orderItem) {
            $cartItem = $cartItems->get((string) $orderItem->source_id);
            if (!$cartItem) {
                continue;
            }

            $meta = (array) ($cartItem->metadata_json ?? []);
            $compartmentStockProductId = (string) ($meta['compartment_stock_product_id'] ?? '');
            $compartmentStockId = (string) ($meta['compartment_stock_id'] ?? '');
            $vendorLocationId = (int) ($meta['vendor_location_id'] ?? 0);

            if ($compartmentStockProductId === '' || $compartmentStockId === '' || $vendorLocationId <= 0) {
                continue;
            }

            $stockRow = DB::table('compartment_stock_products as csp')
                ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
                ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
                ->join('compartments as compartments', 'compartments.compartment_id', '=', 'tc.compartment_id')
                ->join('racks as racks', 'racks.rack_id', '=', 'compartments.rack_id')
                ->join('vendor_locations as vl', 'vl.id', '=', 'racks.vendor_location_id')
                ->join('vendors as vendors', 'vendors.vendor_id', '=', 'vl.vendor_id')
                ->where('csp.compartment_stock_product_id', $compartmentStockProductId)
                ->where('csp.compartment_stock_id', $compartmentStockId)
                ->where('csp.product_id', (string) $orderItem->product_id)
                ->where('vl.id', $vendorLocationId)
                ->lockForUpdate()
                ->select([
                    'csp.compartment_stock_product_id',
                    'csp.compartment_stock_id',
                    'csp.quantity',
                    'compartments.compartment_id',
                    'compartments.label as compartment_name',
                    'racks.rack_id',
                    'racks.rack_name',
                    'vl.id as vendor_location_id',
                    'vl.location_name as vendor_location_name',
                    'vendors.vendor_id',
                    'vendors.vendor_name',
                ])
                ->first();

            if (!$stockRow) {
                throw new \RuntimeException('Pickup stock row not found for order ' . $order->order_no);
            }

            $orderedQty = (int) $orderItem->quantity;
            $currentQty = (int) $stockRow->quantity;
            if ($currentQty < $orderedQty) {
                throw new \RuntimeException('Insufficient pickup stock for order ' . $order->order_no);
            }

            DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', $compartmentStockProductId)
                ->update([
                    'quantity' => $currentQty - $orderedQty,
                    'updated_at' => now(),
                ]);

            OrderPickupItem::query()->updateOrCreate(
                [
                    'order_pickup_id' => $pickup->order_pickup_id,
                    'order_item_id' => $orderItem->order_item_id,
                ],
                [
                    'product_id' => (string) $orderItem->product_id,
                    'compartment_stock_id' => $compartmentStockId,
                    'compartment_stock_product_id' => $compartmentStockProductId,
                    'rack_id' => (string) ($meta['rack_id'] ?? $stockRow->rack_id ?? ''),
                    'compartment_id' => (string) ($meta['compartment_id'] ?? $stockRow->compartment_id ?? ''),
                    'ordered_quantity' => $orderedQty,
                    'picked_up_quantity' => 0,
                    'product_name' => (string) ($orderItem->line_name ?? 'Product'),
                    'vendor_name' => (string) ($meta['vendor_name'] ?? $stockRow->vendor_name ?? ''),
                    'vendor_location_name' => (string) ($meta['pickup_location_name'] ?? $stockRow->vendor_location_name ?? ''),
                    'rack_name' => (string) ($meta['rack_name'] ?? $stockRow->rack_name ?? ''),
                    'compartment_name' => (string) ($meta['compartment_name'] ?? $stockRow->compartment_name ?? ''),
                ]
            );

            CompartmentStockProductTransaction::query()->create([
                'compartment_stock_product_transaction_id' => (string) Str::uuid(),
                'transaction_quantity' => $orderedQty,
                'compartment_stock_qr_session_id' => null,
                'compartment_stock_id' => $compartmentStockId,
                'compartment_stock_product_id' => $compartmentStockProductId,
                'vendor_id' => (string) ($stockRow->vendor_id ?? ''),
                'rack_owner_vendor_id' => (string) ($stockRow->vendor_id ?? ''),
                'generated_by_user_id' => $actorUserId,
                'received_by_user_id' => null,
                'transaction_type' => 'purchase_pickup',
                'transaction_status' => 'confirmed',
                'prepared_quantity' => $currentQty,
                'received_quantity' => null,
                'quantity_delta' => -1 * $orderedQty,
                'actor_user_id' => $actorUserId,
                'actor_vendor_id' => (string) ($stockRow->vendor_id ?? ''),
                'event_source' => 'order_pickup',
                'event_source_id' => (string) $pickup->order_pickup_id,
                'vendor_location_id' => $vendorLocationId,
                'rack_id' => (string) ($meta['rack_id'] ?? $stockRow->rack_id ?? ''),
                'compartment_id' => (string) ($meta['compartment_id'] ?? $stockRow->compartment_id ?? ''),
                'product_id' => (string) $orderItem->product_id,
                'description' => 'Purchase pickup allocation for order ' . (string) $order->order_no,
                'confirmed_at' => now(),
            ]);
        }

        $payload = $this->buildPickupPayload($pickup);
        $signature = $this->signPickupPayload($payload);
        $pickup->update([
            'pickup_payload_json' => array_merge($payload, ['signature' => $signature]),
            'pickup_signature_hash' => hash('sha256', $signature),
            'qr_issued_at' => now(),
        ]);

        return true;
    }

    private function buildPickupPayload(OrderPickup $pickup): array
    {
        return [
            'order_pickup_id' => (string) $pickup->order_pickup_id,
            'order_id' => (string) $pickup->order_id,
            'user_id' => (string) $pickup->user_id,
            'vendor_id' => (string) $pickup->vendor_id,
            'vendor_location_id' => (int) $pickup->vendor_location_id,
            'pickup_code' => (string) $pickup->pickup_code,
            'ts' => now()->timestamp,
        ];
    }

    private function signPickupPayload(array $payload): string
    {
        $canonical = $payload;
        unset($canonical['signature']);
        ksort($canonical);

        return hash_hmac('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES), $this->pickupQrSecret());
    }

    private function pickupQrSecret(): string
    {
        return (string) (env('QR_SECRET') ?: config('app.key'));
    }

    public function verifySignature(Request $request)
    {
        $merchantKey = config('services.ipay88.key');
        $merchantCode = $request->MerchantCode;
        $paymentId = $request->PaymentId;
        $refNo = $request->RefNo;
        $amount = str_replace(['.', ','], '', $request->Amount);
        $currency = $request->Currency;
        $status = $request->Status; // 1 for success

        // The specific order for Response Signature:
        // MerchantKey + MerchantCode + PaymentId + RefNo + Amount + Currency + Status
        $rawString = $merchantKey . $merchantCode . $paymentId . $refNo . $amount . $currency . $status;

        // cannot change this algorithm, important
        $computedSignature = hash_hmac('sha512', $rawString, $merchantKey);

        if (hash_equals((string) $computedSignature, (string) $request->Signature)) {
            return true;
        }

        return false;
    }

    private function generateOrderNo()
    {
        $orderNo = date('ymd') . '-' . strtoupper(Str::random(6));
        return $orderNo;
    }

    private function generateReferralCode()
    {
        $referralCode = strtoupper(Str::random(8));
        return $referralCode;
    }

    private function getUserMembershipType($user_id): ?string
    {
        return UserMemberships::query()
            ->where('user_id', $user_id)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->value('memberships.membership_type');
    }

    public function orderDetail(Request $request, $refNo)
    {
        $order = Orders::query()
            ->select('total_payment', 'total_discount', 'order_id')
            ->where('order_no', $refNo)
            ->first();
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order->payment = Payments::query()
            ->select('payment_method', 'created_at', 'transaction_id')
            ->where('order_no', $refNo)
            ->where('payment_status', 1)
            ->first();

        $order->products = OrderItems::query()
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->selectRaw("
                CASE
                    WHEN order_items.line_name IS NOT NULL AND order_items.line_name <> '' THEN order_items.line_name
                    ELSE products.product_name
                END as product_name
            ")
            ->addSelect('order_items.quantity', 'order_items.line_type')
            ->where('order_id', $order->order_id)
            ->get();

        $order->event_registrations = EventRegistration::query()
            ->join('events', 'event_registrations.event_id', '=', 'events.event_id')
            ->where('event_registrations.order_id', $order->order_id)
            ->orderBy('events.event_start_date')
            ->orderBy('events.event_start_time')
            ->get([
                'event_registrations.event_registration_id',
                'event_registrations.event_id',
                'event_registrations.registration_status',
                'event_registrations.quantity',
                'event_registrations.price_paid',
                'event_registrations.confirmed_at',
                'events.event_name',
                'events.event_start_date',
                'events.event_start_time',
                'events.event_end_time',
                'events.event_location',
                'events.location_name',
            ])
            ->map(function ($registration) {
                return [
                    'event_registration_id' => (string) $registration->event_registration_id,
                    'event_id' => (string) $registration->event_id,
                    'registration_status' => (string) $registration->registration_status,
                    'quantity' => (int) ($registration->quantity ?? 1),
                    'price_paid' => (float) ($registration->price_paid ?? 0),
                    'confirmed_at' => $registration->confirmed_at ? (string) $registration->confirmed_at : null,
                    'event_name' => (string) $registration->event_name,
                    'event_start_date' => $registration->event_start_date ? (string) $registration->event_start_date : null,
                    'event_start_time' => $registration->event_start_time ? (string) $registration->event_start_time : null,
                    'event_end_time' => $registration->event_end_time ? (string) $registration->event_end_time : null,
                    'event_location' => $registration->event_location ? (string) $registration->event_location : null,
                    'location_name' => $registration->location_name ? (string) $registration->location_name : null,
                ];
            })
            ->values();

        $pickup = OrderPickup::query()
            ->where('order_id', $order->order_id)
            ->first([
                'order_pickup_id',
                'pickup_status',
                'vendor_location_id',
                'picked_up_at',
            ]);

        if ($pickup) {
            $locationName = DB::table('vendor_locations')
                ->where('id', $pickup->vendor_location_id)
                ->value('location_name');

            $order->pickup = [
                'order_pickup_id' => (string) $pickup->order_pickup_id,
                'pickup_status' => (string) $pickup->pickup_status,
                'vendor_location_id' => (int) $pickup->vendor_location_id,
                'vendor_location_name' => $locationName ? (string) $locationName : null,
                'picked_up_at' => $pickup->picked_up_at ? (string) $pickup->picked_up_at : null,
            ];
        }

        return response()->json($order);
    }
}
