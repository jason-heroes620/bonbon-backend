<?php

namespace App\Http\Controllers;

use App\Models\Charges;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChargesController extends Controller
{
    public function index()
    {
        return Inertia::render('charges/charges');
    }

    public function showAll(Request $request)
    {
        $query = Charges::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('charges_name', 'like', "%{$search}%")
                    ->orWhere('charges_description', 'like', "%{$search}%")
                    ->orWhere('charges_type', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        $allowedSortFields = [
            'charges_type',
            'charges_name',
            'charges_rate',
            'charges_status',
            'charges_start_date',
            'charges_end_date',
            'sort_order',
            'created_at',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('charges.created_at', 'desc');
            }
        } else {
            $query->orderBy('charges.created_at', 'desc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $charges = $query->paginate($perPage);

        return response()->json([
            'data' => $charges->items(),
            'meta' => [
                'current_page' => $charges->currentPage(),
                'last_page' => $charges->lastPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
                'from' => $charges->firstItem(),
                'to' => $charges->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('charges/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'charges_type' => ['required', 'string', 'size:1'],
            'charges_name' => ['required', 'string', 'max:255'],
            'charges_rate' => ['required', 'numeric', 'min:0'],
            'charges_description' => ['required', 'string', 'max:255'],
            'charges_status' => ['required', 'boolean'],
            'charges_start_date' => ['required', 'date'],
            'charges_end_date' => ['nullable', 'date', 'after_or_equal:charges_start_date'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:127'],
        ]);

        Charges::create($validated);

        return redirect()->route('charges.index')->with([
            'success' => 'Charge created successfully',
        ]);
    }

    public function edit(Charges $charge)
    {
        return Inertia::render('charges/edit', [
            'charge' => $charge,
        ]);
    }

    public function update(Request $request, Charges $charge)
    {
        $validated = $request->validate([
            'charges_type' => ['required', 'string', 'size:1'],
            'charges_name' => ['required', 'string', 'max:255'],
            'charges_rate' => ['required', 'numeric', 'min:0'],
            'charges_description' => ['required', 'string', 'max:255'],
            'charges_status' => ['required', 'boolean'],
            'charges_start_date' => ['required', 'date'],
            'charges_end_date' => ['date', 'after_or_equal:charges_start_date'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:127'],
        ]);

        $charge->update($validated);

        return redirect()->route('charges.index')->with([
            'success' => 'Charge updated successfully',
        ]);
    }

    public function destroy(Charges $charge)
    {
        $charge->update([
            'charges_status' => false,
        ]);

        return redirect()->route('charges.index')->with([
            'success' => 'Charge deleted successfully',
        ]);
    }
}
