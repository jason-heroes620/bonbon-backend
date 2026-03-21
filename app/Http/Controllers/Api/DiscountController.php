<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountProducts;
use App\Models\Discounts;
use App\Models\Orders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscountController extends Controller
{
    public function validateDiscount(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:0'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'uuid'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $discount = Discounts::query()
            ->where('discount_code', $request->code)
            ->first();

        if (!$discount) {
            return response()->json([
                'message' => 'Discount code not found',
            ], 404);
        }

        if (!$discount->is_active) {
            return response()->json([
                'message' => 'Discount code is inactive',
            ], 400);
        }

        $today = now()->toDateString();
        if (
            (string) $discount->discount_start_date > $today
            || (string) $discount->discount_end_date < $today
        ) {
            return response()->json([
                'message' => 'Discount code is not valid for this date',
            ], 400);
        }

        if (!$discount->is_unlimited) {
            $usageLimit = (int) ($discount->discount_usage_limit ?? 0);
            if ($usageLimit > 0) {

                $completedUsages = Orders::query()
                    ->where('discount_code', $discount->discount_code)
                    ->where('order_status', 'completed')
                    ->count();

                if ($completedUsages >= $usageLimit) {
                    return response()->json([
                        'message' => 'Usage limit reached',
                    ], 400);
                }
            }
        }

        $originalAmount = (float) $request->amount;
        $finalAmount = $originalAmount;
        $applied = false;

        $productIds = collect($request->input('products', []))
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
                return response()->json([
                    'data' => $discount,
                    'original_amount' => round($originalAmount, 2),
                    'final_amount' => round($originalAmount, 2),
                    'discount_value' => 0,
                    'applied' => false,
                ]);
            }
        }

        $discountAmount = (float) ($discount->discount_amount ?? 0);
        if ($discount->discount_type === 'P') {
            $finalAmount = $originalAmount - ($originalAmount * ($discountAmount / 100));
        } else {
            $finalAmount = $originalAmount - $discountAmount;
        }

        if ($finalAmount < 0) {
            $finalAmount = 0;
        }

        $applied = true;
        return response()->json([
            'data' => $discount,
            'original_amount' => round($originalAmount, 2),
            'final_amount' => round($finalAmount, 2),
            'discount_value' => round($originalAmount - $finalAmount, 2),
            'applied' => $applied,
        ]);
    }
}
