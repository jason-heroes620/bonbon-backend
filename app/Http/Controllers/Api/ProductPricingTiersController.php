<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPricingTier;
use App\Models\Products;
use App\Models\Vendors;
use Illuminate\Http\Request;

class ProductPricingTiersController extends Controller
{
    public function index(Request $request, string $product_id)
    {
        $product = Products::query()->whereKey($product_id)->first();
        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        if (!$this->canManageProduct($request, $product)) {
            return response()->json([
                'message' => 'You are not allowed to view pricing tiers for this product.',
            ], 403);
        }

        $tiers = ProductPricingTier::query()
            ->where('product_id', $product_id)
            ->orderByDesc('is_active')
            ->orderBy('min_qty')
            ->get();

        return response()->json([
            'data' => $tiers,
        ]);
    }

    public function store(Request $request, string $product_id)
    {
        $product = Products::query()->whereKey($product_id)->first();
        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        if (!$this->canManageProduct($request, $product)) {
            return response()->json([
                'message' => 'You are not allowed to manage pricing tiers for this product.',
            ], 403);
        }

        $validated = $request->validate([
            'pricing_mode' => ['required', 'in:unit_price,percentage_discount'],
            'min_qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pricingMode = (string) $validated['pricing_mode'];

        if ($pricingMode === 'unit_price' && !isset($validated['unit_price'])) {
            return response()->json([
                'message' => 'unit_price is required when pricing_mode is unit_price.',
            ], 422);
        }

        if ($pricingMode === 'percentage_discount' && !isset($validated['discount_percent'])) {
            return response()->json([
                'message' => 'discount_percent is required when pricing_mode is percentage_discount.',
            ], 422);
        }

        $existingMode = ProductPricingTier::query()
            ->where('product_id', $product_id)
            ->where('is_active', true)
            ->value('pricing_mode');

        if ($existingMode && (string) $existingMode !== $pricingMode) {
            return response()->json([
                'message' => 'Cannot mix pricing modes. Deactivate existing tiers before switching mode.',
            ], 422);
        }

        $existingMinQty = ProductPricingTier::query()
            ->where('product_id', $product_id)
            ->where('min_qty', (int) $validated['min_qty'])
            ->exists();

        if ($existingMinQty) {
            return response()->json([
                'message' => 'A pricing tier already exists for this min quantity.',
            ], 422);
        }

        $tier = ProductPricingTier::query()->create([
            'product_id' => $product_id,
            'pricing_mode' => $pricingMode,
            'min_qty' => (int) $validated['min_qty'],
            'unit_price' => $pricingMode === 'unit_price' ? (float) $validated['unit_price'] : null,
            'discount_percent' => $pricingMode === 'percentage_discount' ? (float) $validated['discount_percent'] : null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
        ]);

        return response()->json([
            'data' => $tier,
        ], 201);
    }

    public function update(Request $request, string $product_id, string $product_pricing_tier_id)
    {
        $product = Products::query()->whereKey($product_id)->first();
        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        if (!$this->canManageProduct($request, $product)) {
            return response()->json([
                'message' => 'You are not allowed to manage pricing tiers for this product.',
            ], 403);
        }

        $tier = ProductPricingTier::query()
            ->whereKey($product_pricing_tier_id)
            ->where('product_id', $product_id)
            ->first();

        if (!$tier) {
            return response()->json([
                'message' => 'Pricing tier not found.',
            ], 404);
        }

        $validated = $request->validate([
            'pricing_mode' => ['required', 'in:unit_price,percentage_discount'],
            'min_qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pricingMode = (string) $validated['pricing_mode'];

        if ($pricingMode === 'unit_price' && !isset($validated['unit_price'])) {
            return response()->json([
                'message' => 'unit_price is required when pricing_mode is unit_price.',
            ], 422);
        }

        if ($pricingMode === 'percentage_discount' && !isset($validated['discount_percent'])) {
            return response()->json([
                'message' => 'discount_percent is required when pricing_mode is percentage_discount.',
            ], 422);
        }

        $existingMode = ProductPricingTier::query()
            ->where('product_id', $product_id)
            ->where('is_active', true)
            ->where('product_pricing_tier_id', '!=', $product_pricing_tier_id)
            ->value('pricing_mode');

        if ($existingMode && (string) $existingMode !== $pricingMode) {
            return response()->json([
                'message' => 'Cannot mix pricing modes. Deactivate existing tiers before switching mode.',
            ], 422);
        }

        $existingMinQty = ProductPricingTier::query()
            ->where('product_id', $product_id)
            ->where('min_qty', (int) $validated['min_qty'])
            ->where('product_pricing_tier_id', '!=', $product_pricing_tier_id)
            ->exists();

        if ($existingMinQty) {
            return response()->json([
                'message' => 'A pricing tier already exists for this min quantity.',
            ], 422);
        }

        $tier->update([
            'pricing_mode' => $pricingMode,
            'min_qty' => (int) $validated['min_qty'],
            'unit_price' => $pricingMode === 'unit_price' ? (float) $validated['unit_price'] : null,
            'discount_percent' => $pricingMode === 'percentage_discount' ? (float) $validated['discount_percent'] : null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $tier->is_active,
        ]);

        return response()->json([
            'data' => $tier->fresh(),
        ]);
    }

    public function destroy(Request $request, string $product_id, string $product_pricing_tier_id)
    {
        $product = Products::query()->whereKey($product_id)->first();
        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        if (!$this->canManageProduct($request, $product)) {
            return response()->json([
                'message' => 'You are not allowed to manage pricing tiers for this product.',
            ], 403);
        }

        $tier = ProductPricingTier::query()
            ->whereKey($product_pricing_tier_id)
            ->where('product_id', $product_id)
            ->first();

        if (!$tier) {
            return response()->json([
                'message' => 'Pricing tier not found.',
            ], 404);
        }

        $tier->update([
            'is_active' => false,
        ]);

        return response()->json([
            'data' => $tier->fresh(),
        ]);
    }

    private function canManageProduct(Request $request, Products $product): bool
    {
        if ((string) ($request->user()?->role ?? '') === 'admin') {
            return true;
        }

        if ((string) ($request->user()?->role ?? '') !== 'vendor') {
            return false;
        }

        if (!$product->vendor_id) {
            return false;
        }

        $vendorId = Vendors::query()
            ->where('user_id', $request->user()?->user_id)
            ->value('vendor_id');

        return $vendorId && (string) $vendorId === (string) $product->vendor_id;
    }
}
