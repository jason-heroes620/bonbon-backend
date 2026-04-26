<?php

namespace App\Http\Controllers;

use App\Models\TransactionTypes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionTypesController extends Controller
{
    public function index()
    {
        return Inertia::render('transaction-types/transaction-types');
    }

    public function showAll(Request $request)
    {
        $query = TransactionTypes::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_type', 'like', "%{$search}%")
                    ->orWhere('transaction_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('transaction_types.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('transaction-types/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_type' => ['required', 'string', 'max:100'],
            'transaction_name' => ['required', 'string', 'max:100'],
            'credit_amount' => ['required', 'integer', 'min:0'],
            'effective_date' => ['required', 'date'],
            'expire_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        TransactionTypes::create($validated);

        return redirect()->route('transaction_types.index')->with([
            'success' => 'Transaction type created successfully',
        ]);
    }

    public function edit(TransactionTypes $transactionType)
    {
        return Inertia::render('transaction-types/edit', [
            'transactionType' => $transactionType,
        ]);
    }

    public function update(Request $request, TransactionTypes $transactionType)
    {
        $validated = $request->validate([
            'transaction_type' => ['required', 'string', 'max:100'],
            'transaction_name' => ['required', 'string', 'max:100'],
            'credit_amount' => ['required', 'integer', 'min:0'],
            'effective_date' => ['required', 'date'],
            'expire_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['required', 'boolean'],
        ]);

        $transactionType->update($validated);

        return redirect()->route('transaction_types.index')->with([
            'success' => 'Transaction type updated successfully',
        ]);
    }
}
