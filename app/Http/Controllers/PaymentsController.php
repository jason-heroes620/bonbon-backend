<?php

namespace App\Http\Controllers;

use App\Models\Payments;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    public function index()
    {
        return Inertia::render('payments/payments');
    }

    public function showAll(Request $request)
    {
        $query = Payments::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('payments.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $payments = $query->paginate($perPage);

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('payments/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'uuid'],
            'order_no' => ['required', 'string', 'max:50'],
            'transaction_id' => ['required', 'string', 'max:50'],
            'ref_no' => ['required', 'string', 'max:50'],
            'payment_description' => ['required', 'string'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_amount' => ['required', 'numeric'],
            'payment_date' => ['required', 'date'],
            'issuing_bank' => ['required', 'string', 'max:150'],
            'payment_ref' => ['required', 'string', 'max:50'],
            'bank_ref' => ['required', 'string', 'max:50'],
            'cc_name' => ['required', 'string', 'max:200'],
            'cc_number' => ['required', 'string', 'max:50'],
            'payment_status' => ['required', 'integer'],
        ]);

        Payments::create($validated);

        return redirect()->route('payments.create')->with([
            'success' => 'Payment created successfully',
        ]);
    }

    public function edit(Payments $payment)
    {
        return Inertia::render('payments/edit', [
            'payment' => $payment,
        ]);
    }

    public function update(Request $request, Payments $payment)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'uuid'],
            'order_no' => ['required', 'string', 'max:50'],
            'transaction_id' => ['required', 'string', 'max:50'],
            'ref_no' => ['required', 'string', 'max:50'],
            'payment_description' => ['required', 'string'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_amount' => ['required', 'numeric'],
            'payment_date' => ['required', 'date'],
            'issuing_bank' => ['required', 'string', 'max:150'],
            'payment_ref' => ['required', 'string', 'max:50'],
            'bank_ref' => ['required', 'string', 'max:50'],
            'cc_name' => ['required', 'string', 'max:200'],
            'cc_number' => ['required', 'string', 'max:50'],
            'payment_status' => ['required', 'integer'],
        ]);

        $payment->update($validated);

        return redirect()->route('payments.edit', $payment->payment_id)->with([
            'success' => 'Payment updated successfully',
        ]);
    }
}
