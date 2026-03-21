<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    //
    public function membership(Request $request)
    {
        $today = now()->toDateString();

        $memberships = Memberships::query()
            ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.membership_type_id')
            ->leftJoin('products', 'memberships.membership_code', '=', 'products.product_code')
            ->leftJoin('product_discounts', function ($join) use ($today) {
                $join->on('products.product_id', '=', 'product_discounts.product_id')
                    ->where('product_discounts.discount_start_date', '<=', $today)
                    ->where('product_discounts.discount_end_date', '>=', $today);
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
            ->where('membership_types.membership_type', '!=', 'Free')
            ->orderBy('memberships.sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => $memberships,
        ]);
    }
}
