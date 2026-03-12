<?php

namespace App\Http\Controllers;

use App\Models\ProductDiscounts;
use App\Models\Products;
use Illuminate\Http\Request;
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
            ->select('product_id', 'product_name', 'product_code', 'product_sku')
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

        return response()->json([
            'data' => $products,
        ]);
    }
}
