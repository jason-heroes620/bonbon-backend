<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrdersController extends Controller
{
    public function index()
    {
        return Inertia::render('orders/orders');
    }

    public function showAll(Request $request)
    {
        $query = Orders::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', "%{$search}%")
                    ->orWhere('order_status', 'like', "%{$search}%")
                    ->orWhere('user_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('orders.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $orders = $query->paginate($perPage);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('orders/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid'],
            'order_no' => ['nullable', 'string', 'max:20'],
            'order_date' => ['required', 'date'],
            'order_description' => ['nullable', 'string', 'max:255'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'total_charges' => ['required', 'numeric', 'min:0'],
            'total_discount' => ['required', 'numeric', 'min:0'],
            'total_payment' => ['required', 'numeric', 'min:0'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'wallet_credit_used' => ['nullable', 'numeric', 'min:0'],
            'order_status' => [
                'required',
                Rule::in(['pending', 'processing', 'shipped', 'completed', 'refunded']),
            ],
            'order_items' => ['nullable', 'array'],
            'order_items.*.product_id' => ['required_with:order_items', 'uuid', 'exists:products,product_id'],
            'order_items.*.quantity' => ['required_with:order_items', 'numeric', 'min:1'],
            'order_items.*.uom' => ['required_with:order_items', 'string', 'max:50'],
            'order_items.*.unit_price' => ['required_with:order_items', 'numeric', 'min:0'],
            'order_items.*.tax' => ['nullable', 'numeric', 'min:0'],
            'order_items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'order_items.*.total_price' => ['required_with:order_items', 'numeric', 'min:0'],
        ]);

        $validated['order_no'] = $validated['order_no'] ?? $this->generateOrderNo();
        $order = Orders::create($validated);

        if (!empty($validated['order_items'])) {
            foreach ($validated['order_items'] as $item) {
                OrderItems::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'uom' => $item['uom'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => $item['total_price'],
                ]);
            }
        }

        return redirect()->route('orders.index')->with([
            'success' => 'Order created successfully',
        ]);
    }

    public function edit(Orders $order)
    {

        $order->load([
            'orderItems.product:product_id,product_name,uom',
        ]);
        $order->email = User::query()->where('user_id', $order->user_id)->pluck('email')->first();
        return Inertia::render('orders/edit', [
            'order' => $order,
        ]);
    }

    public function update(Request $request, Orders $order)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid'],
            'order_no' => ['nullable', 'string', 'max:20'],
            'order_date' => ['required', 'date'],
            'order_description' => ['nullable', 'string', 'max:255'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'total_charges' => ['required', 'numeric', 'min:0'],
            'total_discount' => ['required', 'numeric', 'min:0'],
            'total_payment' => ['required', 'numeric', 'min:0'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'wallet_credit_used' => ['nullable', 'numeric', 'min:0'],
            'order_status' => [
                'required',
                Rule::in(['pending', 'processing', 'shipped', 'completed', 'refunded']),
            ],
            'order_items' => ['nullable', 'array'],
            'order_items.*.product_id' => ['required_with:order_items', 'uuid', 'exists:products,product_id'],
            'order_items.*.quantity' => ['required_with:order_items', 'numeric', 'min:1'],
            'order_items.*.uom' => ['required_with:order_items', 'string', 'max:50'],
            'order_items.*.unit_price' => ['required_with:order_items', 'numeric', 'min:0'],
            'order_items.*.tax' => ['nullable', 'numeric', 'min:0'],
            'order_items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'order_items.*.total_price' => ['required_with:order_items', 'numeric', 'min:0'],
        ]);

        $order->update($validated);

        OrderItems::query()->where('order_id', $order->getKey())->delete();
        if (!empty($validated['order_items'])) {
            foreach ($validated['order_items'] as $item) {
                OrderItems::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'uom' => $item['uom'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => $item['total_price'],
                ]);
            }
        }

        return redirect()->route('orders.index')->with([
            'success' => 'Order updated successfully',
        ]);
    }

    private function generateOrderNo()
    {
        $orderNo = date('Ymd') . '-' . strtoupper(Str::random(6));
        return $orderNo;
    }
}
