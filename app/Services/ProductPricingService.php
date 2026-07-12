<?php

namespace App\Services;

use App\Models\ProductDiscounts;
use App\Models\ProductPricingTier;
use App\Models\Products;
use Carbon\Carbon;

class ProductPricingService
{
    public function resolvePricing(Products $product, int $quantity): array
    {
        $baseUnitPrice = (float) ($product->sale_price > 0 ? $product->sale_price : $product->retail_price);

        $tier = ProductPricingTier::query()
            ->where('product_id', $product->product_id)
            ->where('is_active', true)
            ->where('min_qty', '<=', $quantity)
            ->orderByDesc('min_qty')
            ->first();

        if ($tier) {
            if ((string) $tier->pricing_mode === 'unit_price') {
                $finalUnitPrice = (float) $tier->unit_price;
                $unitDiscount = max(0.0, $baseUnitPrice - $finalUnitPrice);

                return [
                    'pricing_mode' => 'tier_unit_price',
                    'base_unit_price' => round($baseUnitPrice, 2),
                    'final_unit_price' => round($finalUnitPrice, 2),
                    'unit_discount' => round($unitDiscount, 2),
                    'discount_total' => round($unitDiscount * $quantity, 2),
                    'tier' => $tier,
                    'product_discount' => null,
                ];
            }

            $percent = (float) $tier->discount_percent;
            $finalUnitPrice = $baseUnitPrice * (1 - ($percent / 100));
            if ($finalUnitPrice < 0) {
                $finalUnitPrice = 0;
            }
            $unitDiscount = max(0.0, $baseUnitPrice - $finalUnitPrice);

            return [
                'pricing_mode' => 'tier_percent_discount',
                'base_unit_price' => round($baseUnitPrice, 2),
                'final_unit_price' => round($finalUnitPrice, 2),
                'unit_discount' => round($unitDiscount, 2),
                'discount_total' => round($unitDiscount * $quantity, 2),
                'tier' => $tier,
                'product_discount' => null,
            ];
        }

        $now = Carbon::now()->toDateString();
        $productDiscount = ProductDiscounts::query()
            ->where('product_id', $product->product_id)
            ->where('is_active', true)
            ->whereDate('discount_start_date', '<=', $now)
            ->whereDate('discount_end_date', '>=', $now)
            ->orderByDesc('discount_start_date')
            ->first();

        if ($productDiscount) {
            $finalUnitPrice = $baseUnitPrice;
            $discountAmount = (float) $productDiscount->discount_amount;

            if ((string) $productDiscount->discount_type === 'P') {
                $finalUnitPrice = $baseUnitPrice * (1 - ($discountAmount / 100));
            } else {
                $finalUnitPrice = $baseUnitPrice - $discountAmount;
            }

            if ($finalUnitPrice < 0) {
                $finalUnitPrice = 0;
            }

            $unitDiscount = max(0.0, $baseUnitPrice - $finalUnitPrice);

            return [
                'pricing_mode' => 'product_discount',
                'base_unit_price' => round($baseUnitPrice, 2),
                'final_unit_price' => round($finalUnitPrice, 2),
                'unit_discount' => round($unitDiscount, 2),
                'discount_total' => round($unitDiscount * $quantity, 2),
                'tier' => null,
                'product_discount' => $productDiscount,
            ];
        }

        return [
            'pricing_mode' => 'base',
            'base_unit_price' => round($baseUnitPrice, 2),
            'final_unit_price' => round($baseUnitPrice, 2),
            'unit_discount' => 0.0,
            'discount_total' => 0.0,
            'tier' => null,
            'product_discount' => null,
        ];
    }
}

