<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\Payments;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserReferralGifts;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        Log::info('Create payment request');
        Log::info($request->all());

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0'],
            'product' => ['nullable', 'string', 'max:255'],
            'total_tax' => ['nullable', 'numeric', 'min:0'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
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
            'products.*.unit_price' => ['required', 'numeric', 'min:0'],
            'products.*.tax' => ['nullable', 'numeric', 'min:0'],
            'products.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'products.*.amount' => ['required', 'numeric', 'min:0'],
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
        $amountRaw = (string) $request->input('amount');
        $amount = str_replace(['.', ','], '', $amountRaw); // Format: 100.00 -> 10000
        $currency = "MYR";

        // Signature = MerchantKey + MerchantCode + RefNo + Amount + Currency
        $signature = base64_encode(hex2bin(hash('sha1', $merchantKey . $merchantCode . $refNo . $amount . $currency)));

        DB::transaction(function () use ($request, $refNo) {
            Log::info('Create payment request', $request->all());
            $totalPayment = $request->input('amount') !== null
                ? (float) $request->input('amount')
                : (float) $request->input('total_payment');

            $totalPrice = $request->input('subtotal') !== null
                ? (float) $request->input('subtotal')
                : $totalPayment;

            $order = Orders::create([
                'user_id' => $request->user()->user_id,
                'order_no' => $refNo,
                'order_date' => now()->toDateString(),
                'total_price' => $totalPrice,
                'total_tax' => (float) ($request->input('total_tax') ?? 0),
                'total_discount' => (float) (($request->input('discount_value') ?? 0) + ($request->input('promo_discount') ?? 0)),
                'total_payment' => $totalPayment,
                'shipping_method' => $request->input('shipping_method') ?? null,
                'shipping_address' => $request->input('shipping_address') ?? null,
                'billing_address' => $request->input('billing_address') ?? null,
                'discount_code' => $request->input('discount_code') ?? null,
                'wallet_credit_used' => (float) ($request->input('wallet_credit_used') ?? 0),
                'order_status' => 'pending',
            ]);

            foreach ($request->input('products') as $item) {
                OrderItems::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'uom' => $item['uom'],
                    'unit_price' => (float) $item['unit_price'],
                    'tax' => (float) ($item['tax'] ?? 0),
                    'discount' => (float) ($item['discount_value'] ?? 0),
                    'total_price' => (float) $item['amount'],
                ]);
            }
        });

        return response()->json([
            'merchantCode' => $merchantCode,
            'refNo'        => $refNo,
            'amount'       => $request->input('amount'),
            'currency'     => $currency,
            'signature'    => $signature,
            'prodDesc'     => $request->input('product') ?? '',
            'userName'     => $request->user()->last_name . " " . $request->user()->first_name,
            'userEmail'    => $request->user()->email,
            'responseUrl'  => url('/api/payments/frontend-callback'),
            'backendUrl'   => url('/api/payments/backend-callback'),
            // 'responseUrl'  => 'https://generically-mediatorial-sharen.ngrok-free.dev/api/payments/frontend-callback',
            // 'backendUrl'   => 'https://generically-mediatorial-sharen.ngrok-free.dev/api/payments/backend-callback',
        ]);
    }

    // This handles the Server-to-Server notification
    public function backendCallback(Request $request)
    {
        try {
            if ($this->verifySignature($request)) {
                if ($request->Status == "1") {
                    Orders::query()
                        ->where('order_no', $request->RefNo)
                        ->update([
                            'order_status' => 'completed',
                        ]);

                    Payments::firstOrCreate(
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
                    Log::info('Payment created');
                    Log::info($request->all());

                    $referralCode = $this->generateReferralCode();
                    ReferralCodes::create([
                        'user_id' => $request->user()->user_id,
                        'campaign_name' => 'Default',
                        'referral_code' => $referralCode,
                        'code_effective_date' => now()->toDateString(),
                        'code_expiry_date' => now()->addYear()->toDateString(),
                        'usage_count' => 0,
                        'max_usage' => 0,
                        'is_active' => true,
                    ]);
                    Log::info('Referral code created');
                    Log::info($referralCode);

                    $user = $request->user();
                    $user->update([
                        'referral_code' => $referralCode,
                    ]);

                    // update user membership to the membership id matching in product_code in order_items table
                    $order = OrderItems::query()
                        ->leftJoin('orders', 'order_items.order_id', '=', 'orders.order_id')
                        ->where('orders.order_no', $request->RefNo)
                        ->first();

                    $membership = Memberships::query()
                        ->where('membership_code', $order->product_code)
                        ->first();

                    if ($membership) {
                        UserMemberships::query()
                            ->where('user_id', $order->user_id)
                            ->update([
                                'membership_id' => $membership->membership_id,
                            ]);

                        Log::info('User membership updated');
                        Log::info($membership);

                        // check referee_id, if exists, update referral_status and qualifies order_id
                        $referral = Referrals::query()
                            ->where('referee_id', $request->user()->user_id)
                            ->where('referral_status', 'pending')
                            ->first();

                        if ($referral) {
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
                    }

                    return response("RECEIVEOK")->header('Content-Type', 'text/plain');
                }
            }
        } catch (\Exception $e) {
            return response("FAILED")->header('Content-Type', 'text/plain');
        }

        return response("FAILED")->header('Content-Type', 'text/plain');
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
        $computedSignature = base64_encode(hex2bin(hash('sha1', $rawString)));

        if ($computedSignature === $request->Signature) {
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
        $order->payment = Payments::query()
            ->select('payment_method', 'created_at', 'transaction_id')
            ->where('order_no', $refNo)
            ->where('payment_status', 1)
            ->first();

        $order->products = OrderItems::query()
            ->leftJoin('products', 'order_items.product_id', '=', 'products.product_id')
            ->select('products.product_name', 'order_items.quantity')
            ->where('order_id', $order->order_id)
            ->get();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json($order);
    }
}
