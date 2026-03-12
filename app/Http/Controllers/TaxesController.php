<?php

namespace App\Http\Controllers;

use App\Models\Taxes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxesController extends Controller
{
    public function index()
    {
        return Inertia::render('taxes/taxes');
    }

    public function showAll(Request $request)
    {
        $query = Taxes::query();

        if ($search = $request->input('search')) {
            $query->where('tax_name', 'like', "%{$search}%");
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
            $query->orderBy('taxes.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $taxes = $query->paginate($perPage);

        return response()->json([
            'data' => $taxes->items(),
            'meta' => [
                'current_page' => $taxes->currentPage(),
                'last_page' => $taxes->lastPage(),
                'per_page' => $taxes->perPage(),
                'total' => $taxes->total(),
                'from' => $taxes->firstItem(),
                'to' => $taxes->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('taxes/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_name' => ['required', 'string', 'max:150'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        Taxes::create($validated);

        return redirect()->route('taxes.index')->with([
            'success' => 'Tax created successfully',
        ]);
    }

    public function edit(Taxes $tax)
    {
        return Inertia::render('taxes/edit', [
            'tax' => $tax,
        ]);
    }

    public function update(Request $request, Taxes $tax)
    {
        $validated = $request->validate([
            'tax_name' => ['required', 'string', 'max:150'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $tax->update($validated);

        return redirect()->route('taxes.index')->with([
            'success' => 'Tax updated successfully',
        ]);
    }

    public function destroy(Taxes $tax)
    {
        $tax->update([
            'is_active' => false,
        ]);

        return redirect()->route('taxes.index')->with([
            'success' => 'Tax deleted successfully',
        ]);
    }
}
