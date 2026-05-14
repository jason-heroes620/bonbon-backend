<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discounts;
use App\Models\Memberships;
use App\Models\Referrals;
use App\Models\UserInterestList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    //
    public function membership(Request $request)
    {
        $today = now()->toDateString();
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($this->inInterestList($user->email)) {
            $memberships = Memberships::query()
                ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.membership_type_id')
                ->leftJoin('products', 'memberships.membership_code', '=', 'products.product_code')
                ->select([
                    'memberships.*',
                    'products.product_id',
                    'products.uom',
                    'products.sale_price',
                ])
                ->where('memberships.is_active', 1)
                ->where('membership_code',  'FOUNDING1000')
                ->orderBy('memberships.sort_order', 'asc')
                ->get();
        } else {
            $memberships = Memberships::query()
                ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.membership_type_id')
                ->leftJoin('products', 'memberships.membership_code', '=', 'products.product_code')
                ->leftJoin('product_discounts', function ($join) use ($today) {
                    $join->on('products.product_id', '=', 'product_discounts.product_id')
                        ->where('product_discounts.discount_start_date', '<=', $today)
                        ->where('product_discounts.discount_end_date', '>=', $today)
                        ->where('product_discounts.is_active', 1)
                    ;
                })
                ->select([
                    'memberships.*',
                    'products.product_id',
                    'products.uom',
                    'products.sale_price',
                    'product_discounts.discount_type',
                    'product_discounts.discount_amount',
                ])
                ->selectRaw(
                    "CASE
                    WHEN product_discounts.product_discount_id IS NULL THEN products.sale_price
                    WHEN product_discounts.discount_type = 'P' THEN GREATEST(products.sale_price - (products.sale_price * (product_discounts.discount_amount / 100)), 0)
                    ELSE GREATEST(products.sale_price - product_discounts.discount_amount, 0)
                END AS discounted_sale_price"
                )
                ->where('memberships.is_active', 1)
                ->where('products.is_active', 1)
                ->where('products.is_visible', 1)
                ->where('membership_types.membership_type', '!=', 'Free')
                ->orderBy('memberships.sort_order', 'asc')
                ->get();

            $refereeDiscount = null;
            $isReferee = Referrals::query()
                ->where('referee_id', $user->user_id)
                ->exists();

            if ($isReferee) {
                $refereeDiscount = Discounts::query()
                    ->where('discount_code', 'MEMREF2026')
                    ->where('is_active', 1)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('discount_start_date')
                            ->orWhere('discount_start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('discount_end_date')
                            ->orWhere('discount_end_date', '>=', $today);
                    })
                    ->first(['discount_type', 'discount_amount']);
            }

            $memberships = $memberships->map(function ($membership) use ($refereeDiscount) {
                $basePrice = (float) ($membership->discounted_sale_price ?? $membership->sale_price ?? $membership->membership_price ?? 0);
                $finalPrice = $basePrice;

                if ($refereeDiscount) {
                    $discountAmount = (float) ($refereeDiscount->discount_amount ?? 0);
                    if ($refereeDiscount->discount_type === 'P') {
                        $finalPrice = $basePrice - ($basePrice * ($discountAmount / 100));
                    } else {
                        $finalPrice = $basePrice - $discountAmount;
                    }

                    if ($finalPrice < 0) {
                        $finalPrice = 0;
                    }
                }

                $membership->membership_price = number_format($finalPrice, 2);
                return $membership;
            });
        }


        return response()->json([
            'data' => $memberships,
        ]);
    }

    private function inInterestList($email)
    {
        // check if user email is in interest list

        $user = UserInterestList::query()->where('email', $email)->first();
        return $user ? true : false;
    }
}
