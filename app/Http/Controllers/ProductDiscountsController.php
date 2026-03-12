<?php

namespace App\Http\Controllers;

use App\Models\ProductDiscounts;
use App\Models\Products;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ProductDiscountsController extends Controller
{
    public function index()
    {
        return Inertia::render('product-discounts/product-discounts');
    }

    public function showAll(Request $request)
    {
        $query = ProductDiscounts::query()->with('product:product_id,product_name');

        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('product_sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $field = $request->sort['field'];
            $direction = $request->sort['direction'];

            if ($field === 'product_name') {
                $query->join('products', 'products.product_id', '=', 'product_discounts.product_id')
                    ->orderBy('products.product_name', $direction)
                    ->select('product_discounts.*');
            } else {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->orderBy('product_discounts.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $discounts = $query->paginate($perPage);

        return response()->json([
            'data' => $discounts->items(),
            'meta' => [
                'current_page' => $discounts->currentPage(),
                'last_page' => $discounts->lastPage(),
                'per_page' => $discounts->perPage(),
                'total' => $discounts->total(),
                'from' => $discounts->firstItem(),
                'to' => $discounts->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('product-discounts/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'discount_type' => ['required', 'in:P,F'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'discount_start_date' => ['required', 'date'],
            'discount_end_date' => ['required', 'date', 'after_or_equal:discount_start_date'],
            'is_active' => ['required', 'boolean'],
        ]);

        ProductDiscounts::create($validated);

        return redirect()->route('product_discounts.index')->with([
            'success' => 'Product discount created successfully',
        ]);
    }

    public function edit(ProductDiscounts $productDiscount)
    {
        $productDiscount->load('product:product_id,product_name');

        return Inertia::render('product-discounts/edit', [
            'productDiscount' => $productDiscount,
        ]);
    }

    public function update(Request $request, ProductDiscounts $productDiscount)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'discount_type' => ['required', 'in:P,F'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'discount_start_date' => ['required', 'date'],
            'discount_end_date' => ['required', 'date', 'after_or_equal:discount_start_date'],
            'is_active' => ['required', 'boolean'],
        ]);

        $productDiscount->update($validated);

        return redirect()->route('product_discounts.index')->with([
            'success' => 'Product discount updated successfully',
        ]);
    }

    public function destroy(ProductDiscounts $productDiscount)
    {
        $productDiscount->delete();

        return redirect()->route('product_discounts.index')->with([
            'success' => 'Product discount deleted successfully',
        ]);
    }

    public function searchProducts(Request $request)
    {
        $q = (string) $request->input('q', '');

        $products = Products::query()
            ->select(
                'product_id',
                'product_name',
                'product_code',
                'product_sku',
                'uom',
                'is_taxable',
                'tax_rate_id',
                'retail_price',
                'sale_price',
            )
            ->where('products.is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('product_name', 'like', "%{$q}%")
                        ->orWhere('product_code', 'like', "%{$q}%")
                        ->orWhere('product_sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('product_name', 'asc')
            ->limit(20)
            ->get();

        $taxRates = Taxes::query()
            ->select('tax_rate_id', 'tax_rate')
            ->whereIn('tax_rate_id', $products->pluck('tax_rate_id')->filter())
            ->where('is_active', true)
            ->get()
            ->keyBy('tax_rate_id');

        $today = Carbon::today()->toDateString();
        $discounts = ProductDiscounts::query()
            ->select('product_id', 'discount_type', 'discount_amount', 'discount_start_date')
            ->whereIn('product_id', $products->pluck('product_id'))
            ->where('is_active', true)
            ->whereDate('discount_start_date', '<=', $today)
            ->whereDate('discount_end_date', '>=', $today)
            ->orderBy('discount_start_date', 'desc')
            ->get()
            ->groupBy('product_id')
            ->map(fn($group) => $group->first());

        $data = $products->map(function ($product) use ($taxRates, $discounts) {
            $unitPrice = (float) $product->sale_price > 0
                ? (float) $product->sale_price
                : (float) $product->retail_price;

            $taxRate = 0.0;
            if ($product->is_taxable && $product->tax_rate_id) {
                $taxRate = isset($taxRates[$product->tax_rate_id])
                    ? (float) $taxRates[$product->tax_rate_id]->tax_rate
                    : 0.0;
            }
            $taxAmount = round($unitPrice * ($taxRate / 100), 2);

            $discountType = null;
            $discountValue = null;
            $discountAmount = 0.0;
            $discount = $discounts->get($product->product_id);
            if ($discount) {
                $discountType = $discount->discount_type;
                $discountValue = (float) $discount->discount_amount;
                $discountAmount = $discountType === 'P'
                    ? ($unitPrice * ($discountValue / 100))
                    : $discountValue;
                $discountAmount = round(min(max($discountAmount, 0), $unitPrice), 2);
            }

            return [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'product_sku' => $product->product_sku,
                'uom' => $product->uom,
                'unit_price' => round($unitPrice, 2),
                'tax' => $taxAmount,
                'discount' => $discountAmount,
                'tax_rate' => $taxRate,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
