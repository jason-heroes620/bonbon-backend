<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\Products;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductsController extends Controller
{
    public function index()
    {
        return Inertia::render('products/products');
    }

    public function showAll(Request $request)
    {
        $query = Products::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
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
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('products.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        $taxRates = Taxes::select('tax_rate_id as value', 'tax_name as label')
            ->where('is_active', true)
            ->orderBy('tax_name', 'asc')
            ->get();

        return Inertia::render('products/create', [
            'categories' => $categories,
            'taxRates' => $taxRates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,category_id'],
            'product_name' => ['required', 'string', 'max:150'],
            'product_code' => ['nullable', 'string', 'max:50'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'product_description' => ['required', 'string'],
            'uom' => ['nullable', 'string', 'max:50'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_weight' => ['nullable', 'numeric', 'min:0'],
            'product_dimensions' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'is_taxable' => ['required', 'boolean'],
            'tax_rate_id' => ['required', 'uuid'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_unlimited' => ['required', 'boolean'],
        ]);

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);

        $primaryCategoryId = count($categoryIds) > 0 ? $categoryIds[0] : null;
        $validated['category_id'] = $primaryCategoryId;

        $product = Products::create($validated);
        $product->categories()->sync($categoryIds);

        return redirect()->route('products.index')->with([
            'success' => 'Product created successfully',
        ]);
    }

    public function edit(Products $product)
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        $taxRates = Taxes::select('tax_rate_id as value', 'tax_name as label')
            ->where('is_active', true)
            ->orderBy('tax_name', 'asc')
            ->get();

        return Inertia::render('products/edit', [
            'product' => $product,
            'categories' => $categories,
            'taxRates' => $taxRates,
            'selectedCategoryIds' => $product->categories()->pluck('categories.category_id')->toArray(),
        ]);
    }

    public function update(Request $request, Products $product)
    {
        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,category_id'],
            'product_name' => ['required', 'string', 'max:150'],
            'product_code' => ['nullable', 'string', 'max:50'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'uom' => ['nullable', 'string', 'max:50'],
            'product_description' => ['required', 'string'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_weight' => ['nullable', 'numeric', 'min:0'],
            'product_dimensions' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'is_taxable' => ['required', 'boolean'],
            'tax_rate_id' => ['required', 'uuid'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_unlimited' => ['required', 'boolean'],
        ]);

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);

        $primaryCategoryId = count($categoryIds) > 0 ? $categoryIds[0] : null;
        $validated['category_id'] = $primaryCategoryId;

        $product->update($validated);
        $product->categories()->sync($categoryIds);

        return redirect()->route('products.index')->with([
            'success' => 'Product updated successfully',
        ]);
    }

    public function destroy(Products $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with([
            'success' => 'Product deleted successfully',
        ]);
    }
}
