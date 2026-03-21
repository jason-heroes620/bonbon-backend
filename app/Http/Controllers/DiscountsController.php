<?php

namespace App\Http\Controllers;

use App\Models\DiscountProducts;
use App\Models\Discounts;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DiscountsController extends Controller
{
    public function index()
    {
        return Inertia::render('discounts/discounts');
    }

    public function showAll(Request $request)
    {
        $query = Discounts::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('discount_code', 'like', "%{$search}%")
                    ->orWhere('discount_name', 'like', "%{$search}%")
                    ->orWhere('discount_description', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('discounts.created_at', 'desc');
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
        return Inertia::render('discounts/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'discount_code' => ['nullable', 'string', 'max:10'],
            'user_id' => ['nullable', 'uuid', 'exists:users,user_id'],
            'discount_name' => ['required', 'string', 'max:150'],
            'discount_description' => ['required', 'string', 'max:250'],
            'discount_type' => ['required', Rule::in(['P', 'F'])],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'discount_start_date' => ['required', 'date'],
            'discount_end_date' => ['required', 'date', 'after_or_equal:discount_start_date'],
            'is_active' => ['required', 'boolean'],
            'applies_to' => ['required', Rule::in(['all', 'specific'])],
            'discount_usage_limit' => ['nullable', 'integer', 'min:0'],
            'is_unlimited' => ['required', 'boolean'],
            'products' => ['required_if:applies_to,specific', 'array'],
            'products.*' => ['uuid', 'exists:products,product_id'],
        ]);

        if (($validated['user_id'] ?? null) === '') {
            $validated['user_id'] = null;
        }

        if (empty($validated['discount_code'])) {
            $validated['discount_code'] = strtoupper(Str::random(10));
        }

        if (($validated['is_unlimited'] ?? false) === true) {
            $validated['discount_usage_limit'] = 0;
        }

        $products = $validated['products'] ?? [];
        unset($validated['products']);

        $discount = Discounts::create($validated);

        if (($validated['applies_to'] ?? 'all') === 'specific') {
            foreach ($products as $productId) {
                DiscountProducts::create([
                    'discount_id' => $discount->discount_id,
                    'product_id' => $productId,
                ]);
            }
        }

        return redirect()->route('discounts.create')->with([
            'success' => 'Discount created successfully',
        ]);
    }

    public function edit(Discounts $discount)
    {
        $productIds = DiscountProducts::query()
            ->where('discount_id', $discount->discount_id)
            ->pluck('product_id')
            ->toArray();

        $discount->setAttribute('products', $productIds);

        return Inertia::render('discounts/edit', [
            'discount' => $discount,
        ]);
    }

    public function update(Request $request, Discounts $discount)
    {
        $validated = $request->validate([
            'discount_code' => ['nullable', 'string', 'max:10'],
            'user_id' => ['nullable', 'uuid', 'exists:users,user_id'],
            'discount_name' => ['required', 'string', 'max:150'],
            'discount_description' => ['required', 'string', 'max:250'],
            'discount_type' => ['required', Rule::in(['P', 'F'])],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'discount_start_date' => ['required', 'date'],
            'discount_end_date' => ['required', 'date', 'after_or_equal:discount_start_date'],
            'is_active' => ['required', 'boolean'],
            'applies_to' => ['required', Rule::in(['all', 'specific'])],
            'discount_usage_limit' => ['nullable', 'integer', 'min:0'],
            'is_unlimited' => ['required', 'boolean'],
            'products' => ['required_if:applies_to,specific', 'array'],
            'products.*' => ['uuid', 'exists:products,product_id'],
        ]);

        if (($validated['user_id'] ?? null) === '') {
            $validated['user_id'] = null;
        }

        if (empty($validated['discount_code'])) {
            $validated['discount_code'] = $discount->discount_code;
        }

        if (($validated['is_unlimited'] ?? false) === true) {
            $validated['discount_usage_limit'] = 0;
        }

        $products = $validated['products'] ?? [];
        unset($validated['products']);

        $discount->update($validated);

        DiscountProducts::where('discount_id', $discount->discount_id)->delete();

        if (($validated['applies_to'] ?? 'all') === 'specific') {
            foreach ($products as $productId) {
                DiscountProducts::create([
                    'discount_id' => $discount->discount_id,
                    'product_id' => $productId,
                ]);
            }
        }

        return redirect()->route('discounts.edit', $discount->discount_id)->with([
            'success' => 'Discount updated successfully',
        ]);
    }
}
