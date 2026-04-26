<?php

namespace App\Http\Controllers;

use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\Products;
use App\Models\Taxes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MembershipsController extends Controller
{
    public function index()
    {
        return Inertia::render('memberships/memberships');
    }

    public function showAll(Request $request)
    {
        $query = Memberships::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('membership_name', 'like', "%{$search}%")
                    ->orWhere('membership_type', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('memberships.sort_order', 'asc');
        }

        $perPage = $request->per_page ?? 10;
        $memberships = $query->paginate($perPage);

        return response()->json([
            'data' => $memberships->items(),
            'meta' => [
                'current_page' => $memberships->currentPage(),
                'last_page' => $memberships->lastPage(),
                'per_page' => $memberships->perPage(),
                'total' => $memberships->total(),
                'from' => $memberships->firstItem(),
                'to' => $memberships->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        $membershipTypes = MembershipTypes::query()
            ->where('is_active', true)
            ->orderBy('membership_type')
            ->get(['membership_type_id', 'membership_type']);

        return Inertia::render('memberships/create', [
            'membershipTypes' => $membershipTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'membership_code' => ['required', 'string', 'max:100', 'unique:memberships,membership_code'],
            'membership_name' => ['required', 'string', 'max:100'],
            'membership_description' => ['nullable', 'string', 'max:255'],
            'membership_type_id' => ['required', 'uuid', 'exists:membership_types,membership_type_id'],
            'membership_price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:1', 'max:9999'],
            'duration_unit' => ['required', Rule::in(['days', 'months', 'years'])],
            'membership_start_date' => ['required', 'date'],
            'membership_end_date' => ['nullable', 'date', 'after_or_equal:membership_start_date'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'best_value' => ['required', 'boolean'],
        ]);

        $membershipType = MembershipTypes::query()->find($validated['membership_type_id']);
        if (!$membershipType) {
            return back()->with([
                'error' => 'Selected membership type does not exist.',
            ]);
        }

        $validated['membership_type'] = $membershipType->membership_type;

        $membership_id = Memberships::create($validated);
        $defaultTaxRateId = Taxes::where('is_active', true)->value('tax_rate_id');

        if (!$defaultTaxRateId) {
            return back()->with([
                'error' => 'No active tax rate found. Please create one before creating memberships.',
            ]);
        }

        Products::create([
            'product_code' => $membership_id->membership_code,
            'product_name' => $validated['membership_name'],
            'product_sku' => null,
            'product_description' => $validated['membership_description'] ?? $validated['membership_name'],
            'stock_quantity' => 0,
            'product_weight' => null,
            'product_dimensions' => null,
            'is_featured' => false,
            'is_visible' => false,
            'is_taxable' => false,
            'tax_rate_id' => $defaultTaxRateId,
            'retail_price' => $validated['membership_price'],
            'sale_price' => $validated['membership_price'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('memberships.index')->with([
            'success' => 'Membership created successfully',
        ]);
    }

    public function edit(Memberships $membership)
    {
        $membershipTypes = MembershipTypes::query()
            ->where('is_active', true)
            ->orderBy('membership_type')
            ->get(['membership_type_id', 'membership_type']);

        return Inertia::render('memberships/edit', [
            'membership' => $membership,
            'membershipTypes' => $membershipTypes,
        ]);
    }

    public function update(Request $request, Memberships $membership)
    {
        $validated = $request->validate([
            'membership_code' => ['required', 'string', 'max:20'],
            'membership_name' => ['required', 'string', 'max:100'],
            'membership_description' => ['nullable', 'string', 'max:255'],
            'membership_type_id' => ['required', 'uuid', 'exists:membership_types,membership_type_id'],
            'membership_price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:1', 'max:9999'],
            'duration_unit' => ['required', Rule::in(['days', 'months', 'years'])],
            'membership_start_date' => ['required', 'date'],
            'membership_end_date' => ['nullable', 'date', 'after_or_equal:membership_start_date'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'best_value' => ['required', 'boolean'],
        ]);

        $membershipType = MembershipTypes::query()->find($validated['membership_type_id']);
        if (!$membershipType) {
            return back()->with([
                'error' => 'Selected membership type does not exist.',
            ]);
        }

        $validated['membership_type'] = $membershipType->membership_type;

        $oldMembershipCode = $membership->membership_code;
        $membership->update($validated);
        $defaultTaxRateId = Taxes::where('is_active', true)->value('tax_rate_id');

        if (!$defaultTaxRateId) {
            return back()->with([
                'error' => 'No active tax rate found. Please create one before updating memberships.',
            ]);
        }

        Products::where('product_code', $oldMembershipCode)
            ->update([
                'product_name' => $validated['membership_name'],
                'product_code' => $validated['membership_code'],
                'product_description' => $validated['membership_description'] ?? $validated['membership_name'],
                'stock_quantity' => 0,
                'is_visible' => false,
                'is_taxable' => false,
                'tax_rate_id' => $defaultTaxRateId,
                'retail_price' => $validated['membership_price'],
                'sale_price' => $validated['membership_price'],
                'is_active' => $validated['is_active'],
            ]);

        return redirect()->route('memberships.index')->with([
            'success' => 'Membership updated successfully',
        ]);
    }

    public function destroy(Memberships $membership)
    {
        $membership->delete();

        return redirect()->route('memberships.index')->with([
            'success' => 'Membership deleted successfully',
        ]);
    }

    public function getMembershipList()
    {
        $memberships = Memberships::query()
            ->select([
                'membership_id as value',
                'membership_name',
                'membership_type',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('membership_name', 'asc')
            ->get()
            ->map(fn ($m) => [
                'value' => $m->value,
                'label' => trim($m->membership_name . (empty($m->membership_type) ? '' : " ({$m->membership_type})")),
            ]);

        return response()->json($memberships);
    }
}
