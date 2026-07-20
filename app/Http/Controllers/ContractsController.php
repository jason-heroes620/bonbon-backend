<?php

namespace App\Http\Controllers;

use App\Models\Charges;
use App\Models\CompartmentStockProducts;
use App\Models\CompartmentStocks;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\Products;
use App\Models\TenderCompartments;
use App\Models\Vendors;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ContractsController extends Controller
{
    public function index()
    {
        return Inertia::render('contracts/contracts');
    }

    public function showAll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $query = TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->join('racks as racks', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as owners', 'vendor_locations.vendor_id', '=', 'owners.vendor_id', 'inner', false)
            ->leftJoin('vendors as assigned_vendors', 'tender_compartments.vendor_id', '=', 'assigned_vendors.vendor_id', 'left', false)
            ->whereIn('tender_compartments.tender_status', ['selected', 'paid'])
            ->select([
                'tender_compartments.tender_compartment_id',
                'tender_compartments.tender_status',
                'tender_compartments.tender_start_date',
                'tender_compartments.tender_end_date',
                'tender_compartments.vendor_id',
                'assigned_vendors.vendor_name as assigned_vendor_name',
                'compartments.label as compartment_label',
                'racks.rack_name',
                'vendor_locations.location_name',
                DB::raw("CONCAT(owners.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
            ]);

        if ((string) $user->role !== 'admin') {
            $vendorId = $this->currentVendorId($request);
            if (!$vendorId) {
                abort(403);
            }

            $query->where('tender_compartments.vendor_id', $vendorId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('racks.rack_name', 'like', "%{$search}%")
                    ->orWhere('vendor_locations.location_name', 'like', "%{$search}%")
                    ->orWhere('compartments.label', 'like', "%{$search}%")
                    ->orWhere('assigned_vendors.vendor_name', 'like', "%{$search}%")
                    ->orWhere('tender_compartments.tender_status', 'like', "%{$search}%");
            });
        }

        $allowedSortFields = [
            'rack_name',
            'location_name',
            'compartment_label',
            'tender_status',
            'tender_start_date',
            'tender_end_date',
        ];

        if ($request->has('sort')) {
            $field = (string) ($request->input('sort.field') ?? '');
            $direction = strtolower((string) ($request->input('sort.direction') ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $allowedSortFields, true)) {
                if ($field === 'rack_name') {
                    $query->orderBy('racks.rack_name', $direction);
                } elseif ($field === 'location_name') {
                    $query->orderBy('vendor_locations.location_name', $direction);
                } elseif ($field === 'compartment_label') {
                    $query->orderBy('compartments.label', $direction);
                } else {
                    $query->orderBy("tender_compartments.{$field}", $direction);
                }
            } else {
                $query->orderBy('tender_compartments.updated_at', 'desc');
            }
        } else {
            $query->orderBy('tender_compartments.updated_at', 'desc');
        }

        $perPage = (int) ($request->input('per_page') ?? 10);
        $perPage = max(1, min($perPage, 100));
        $rows = $query->paginate($perPage);

        return response()->json([
            'data' => collect($rows->items())->map(fn($row) => [
                'tender_compartment_id' => (string) $row->tender_compartment_id,
                'rack_name' => (string) $row->rack_name,
                'location_name' => (string) $row->location_name,
                'vendor_location_name' => (string) $row->vendor_location_name,
                'compartment_label' => (string) $row->compartment_label,
                'vendor_name' => $row->assigned_vendor_name ? (string) $row->assigned_vendor_name : null,
                'tender_status' => (string) $row->tender_status,
                'tender_start_date' => $row->tender_start_date ? (string) $row->tender_start_date : null,
                'tender_end_date' => $row->tender_end_date ? (string) $row->tender_end_date : null,
            ])->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, TenderCompartments $contract)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $this->authorizeContractAccess($request, (string) $contractRecord->vendor_id);

        $subtotal = $this->contractSubtotal((float) $contractRecord->bid_price, (int) $contractRecord->durations);
        $charges = $this->activeChargesBreakdown($subtotal);
        $totalCharges = collect($charges)->sum('amount');
        $totalPayment = $subtotal + $totalCharges;

        return Inertia::render('contracts/show', [
            'contract' => [
                'tender_compartment_id' => (string) $contractRecord->tender_compartment_id,
                'compartment_id' => (string) $contractRecord->compartment_id,
                'rack_id' => (string) $contractRecord->rack_id,
                'rack_name' => (string) $contractRecord->rack_name,
                'vendor_location_name' => (string) $contractRecord->vendor_location_name,
                'compartment_label' => (string) $contractRecord->compartment_label,
                'vendor_name' => $contractRecord->assigned_vendor_name ? (string) $contractRecord->assigned_vendor_name : null,
                'owner_vendor_name' => (string) $contractRecord->owner_vendor_name,
                'tender_status' => (string) $contractRecord->tender_status,
                'bid_price' => (string) $contractRecord->bid_price,
                'durations' => (int) $contractRecord->durations,
                'product_description' => $contractRecord->product_description ? (string) $contractRecord->product_description : null,
                'tender_start_date' => $contractRecord->tender_start_date ? (string) $contractRecord->tender_start_date : null,
                'tender_end_date' => $contractRecord->tender_end_date ? (string) $contractRecord->tender_end_date : null,
            ],
            'charges' => $charges,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'total_charges' => round($totalCharges, 2),
                'total_payment' => round($totalPayment, 2),
            ],
            'stocks' => (string) $contractRecord->tender_status === 'paid'
                ? $this->contractStocks((string) $contractRecord->tender_compartment_id)
                : [],
            'productOptions' => (string) $contractRecord->tender_status === 'paid'
                ? $this->contractProductOptions($contractRecord->vendor_id ? (string) $contractRecord->vendor_id : null)
                : [],
            'canPay' => (string) $user->role !== 'admin'
                && (string) $contractRecord->tender_status === 'selected'
                && (string) $contractRecord->vendor_id === (string) $this->currentVendorId($request),
            'canManageStocks' => (string) $contractRecord->tender_status === 'paid'
                && (
                    (string) $user->role === 'admin'
                    || (string) $contractRecord->vendor_id === (string) $this->currentVendorId($request)
                ),
            'paymentGatewayUrl' => config('services.ipay88.entry_url', 'https://payment.ipay88.com.my/epayment/entry.asp'),
        ]);
    }

    public function storeStock(Request $request, TenderCompartments $contract)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $this->authorizeContractAccess($request, (string) $contractRecord->vendor_id);

        if (
            (string) $user->role !== 'admin'
            && (string) $contractRecord->vendor_id !== (string) $this->currentVendorId($request)
        ) {
            abort(403);
        }

        if ((string) $contractRecord->tender_status !== 'paid') {
            return back()->withErrors([
                'stock' => 'Compartment stock can only be added for paid contracts.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:prepared,completed,remove'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($contractRecord, $validated) {
            $stock = CompartmentStocks::query()->create([
                'tender_compartment_id' => (string) $contractRecord->tender_compartment_id,
                'status' => (string) $validated['status'],
            ]);

            foreach ($validated['items'] as $item) {
                CompartmentStockProducts::query()->create([
                    'compartment_stock_id' => (string) $stock->compartment_stock_id,
                    'product_id' => (string) $item['product_id'],
                    'expiry_date' => $item['expiry_date'] ?: null,
                    'quantity' => (int) $item['quantity'],
                ]);
            }
        });

        return redirect()->route('contracts.show', $contract->tender_compartment_id)->with([
            'success' => 'Compartment stock created successfully.',
        ]);
    }

    public function destroyStock(Request $request, TenderCompartments $contract, CompartmentStocks $stock)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $this->authorizeContractAccess($request, (string) $contractRecord->vendor_id);

        if (
            (string) $user->role !== 'admin'
            && (string) $contractRecord->vendor_id !== (string) $this->currentVendorId($request)
        ) {
            abort(403);
        }

        if ((string) $contractRecord->tender_status !== 'paid') {
            return back()->withErrors([
                'stock' => 'Compartment stock can only be deleted for paid contracts.',
            ]);
        }

        if ((string) $stock->tender_compartment_id !== (string) $contract->tender_compartment_id) {
            abort(404);
        }

        if ((string) $stock->status !== 'prepared') {
            return back()->withErrors([
                'stock' => 'Only prepared stock records can be deleted.',
            ]);
        }

        DB::transaction(function () use ($stock) {
            CompartmentStockProducts::query()
                ->where('compartment_stock_id', (string) $stock->compartment_stock_id)
                ->delete();

            CompartmentStocks::query()
                ->where('compartment_stock_id', (string) $stock->compartment_stock_id)
                ->delete();
        });

        return redirect()->route('contracts.show', $contract->tender_compartment_id)->with([
            'success' => 'Compartment stock deleted successfully.',
        ]);
    }

    public function updateStockProduct(
        Request $request,
        TenderCompartments $contract,
        CompartmentStocks $stock,
        CompartmentStockProducts $stockProduct
    ) {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $this->authorizeContractAccess($request, (string) $contractRecord->vendor_id);

        if (
            (string) $user->role !== 'admin'
            && (string) $contractRecord->vendor_id !== (string) $this->currentVendorId($request)
        ) {
            abort(403);
        }

        if ((string) $contractRecord->tender_status !== 'paid') {
            return back()->withErrors([
                'stock_product' => 'Compartment stock products can only be edited for paid contracts.',
            ]);
        }

        if ((string) $stock->tender_compartment_id !== (string) $contract->tender_compartment_id) {
            abort(404);
        }

        if ((string) $stockProduct->compartment_stock_id !== (string) $stock->compartment_stock_id) {
            abort(404);
        }

        if (!in_array((string) $stock->status, $this->editableStockStatuses(), true)) {
            return back()->withErrors([
                'stock_product' => 'Only prepared or remove stock records can be edited.',
            ]);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,product_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $stockProduct->update([
            'product_id' => (string) $validated['product_id'],
            'quantity' => (int) $validated['quantity'],
            'expiry_date' => $validated['expiry_date'] ?: null,
        ]);

        return redirect()->route('contracts.show', $contract->tender_compartment_id)->with([
            'success' => 'Compartment stock product updated successfully.',
        ]);
    }

    public function destroyStockProduct(
        Request $request,
        TenderCompartments $contract,
        CompartmentStocks $stock,
        CompartmentStockProducts $stockProduct
    ) {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $this->authorizeContractAccess($request, (string) $contractRecord->vendor_id);

        if (
            (string) $user->role !== 'admin'
            && (string) $contractRecord->vendor_id !== (string) $this->currentVendorId($request)
        ) {
            abort(403);
        }

        if ((string) $contractRecord->tender_status !== 'paid') {
            return back()->withErrors([
                'stock_product' => 'Compartment stock products can only be deleted for paid contracts.',
            ]);
        }

        if ((string) $stock->tender_compartment_id !== (string) $contract->tender_compartment_id) {
            abort(404);
        }

        if ((string) $stockProduct->compartment_stock_id !== (string) $stock->compartment_stock_id) {
            abort(404);
        }

        if (!in_array((string) $stock->status, $this->editableStockStatuses(), true)) {
            return back()->withErrors([
                'stock_product' => 'Only prepared or remove stock records can be deleted.',
            ]);
        }

        CompartmentStockProducts::query()
            ->where('compartment_stock_product_id', (string) $stockProduct->compartment_stock_product_id)
            ->delete();

        return redirect()->route('contracts.show', $contract->tender_compartment_id)->with([
            'success' => 'Compartment stock product deleted successfully.',
        ]);
    }

    public function pay(Request $request, TenderCompartments $contract)
    {
        $user = $request->user();
        if (!$user || (string) $user->role === 'admin') {
            abort(403);
        }

        $contractRecord = $this->contractQuery()
            ->where('tender_compartments.tender_compartment_id', $contract->tender_compartment_id)
            ->first();

        if (!$contractRecord) {
            abort(404);
        }

        $vendorId = $this->currentVendorId($request);
        if (!$vendorId || (string) $contractRecord->vendor_id !== (string) $vendorId) {
            abort(403);
        }

        if ((string) $contractRecord->tender_status !== 'selected') {
            return back()->withErrors([
                'payment' => 'Only selected contracts can be paid.',
            ]);
        }

        $subtotal = $this->contractSubtotal((float) $contractRecord->bid_price, (int) $contractRecord->durations);
        $charges = $this->activeChargesBreakdown($subtotal);
        $totalCharges = collect($charges)->sum('amount');
        $totalPayment = round($subtotal + $totalCharges, 2);
        $refNo = $this->generateOrderNo();

        $orders = Orders::query()->create([
            'user_id' => $user->user_id,
            'order_no' => $refNo,
            'order_date' => now()->toDateString(),
            'order_description' => $contractRecord->product_description . ' - ' . (string) $contractRecord->rack_name . ' / ' . (string) $contractRecord->compartment_label . ' ( ' . Carbon::parse($contractRecord->tender_start_date)->format('d M Y') . ' - ' . Carbon::parse($contractRecord->tender_end_date)->format('d M Y') . ' )',
            'total_price' => round($subtotal, 2),
            'total_charges' => round($totalCharges, 2),
            'total_discount' => 0,
            'total_payment' => $totalPayment,
            'shipping_method' => null,
            'shipping_address' => null,
            'billing_address' => null,
            'discount_code' => null,
            'wallet_credit_used' => 0,
            'order_status' => 'pending',
        ]);

        $orderProduct = Products::query()
            ->where('product_code', 'CONTRACT001')->first();

        OrderItems::query()->create([
            'order_id' => $orders->order_id,
            'product_id' => $orderProduct->product_id,
            'line_type' => 'contract',
            'line_name' => $contractRecord->product_description . ' - ' . (string) $contractRecord->rack_name . ' / ' . (string) $contractRecord->compartment_label . ' ( ' . Carbon::parse($contractRecord->tender_start_date)->format('d M Y') . ' - ' . Carbon::parse($contractRecord->tender_end_date)->format('d M Y') . ' )',
            'quantity' => $contractRecord->durations,
            'uom' => 'months',
            'unit_price' => $contractRecord->bid_price,
            'tax' => 0,
            'discount' => 0,
            'total_price' => $subtotal,
        ]);

        foreach ($charges as $charge) {
            OrderCharges::query()->create([
                'order_id' => $orders->order_id,
                'charge_id' => $charge['charges_id'],
                'charge_name' => $charge['charges_name'],
                'charge_type' => $charge['charges_type'],
                'charge_rate' => $charge['charges_rate'],
                'charge_amount' => $totalCharges,
                'sort_order' => $charge['sort_order'],
            ]);
        }

        $merchantCode = (string) config('services.ipay88.code');
        $merchantKey = (string) config('services.ipay88.key');
        $amount = number_format($totalPayment, 2, '.', '');
        $amountForSignature = str_replace(['.', ','], '', $amount);
        $currency = 'MYR';
        $signature = hash_hmac(
            'sha512',
            $merchantKey . $merchantCode . $refNo . $amountForSignature . $currency,
            $merchantKey
        );

        return Inertia::render('contracts/payment-redirect', [
            'gatewayUrl' => config('services.ipay88.entry_url', 'https://payment.ipay88.com.my/epayment/entry.asp'),
            'fields' => [
                'MerchantCode' => $merchantCode,
                'RefNo' => $refNo,
                'Amount' => $amount,
                'Currency' => $currency,
                'ProdDesc' => 'Contract payment - ' . (string) $contractRecord->rack_name . ' / ' . (string) $contractRecord->compartment_label,
                'UserName' => trim((string) $user->last_name . ' ' . (string) $user->first_name),
                'UserEmail' => (string) $user->email,
                'ResponseURL' => route('contracts.payment-return'),
                'BackendURL' => url('/api/payments/backend-callback'),
                'Signature' => $signature,
                'Xfield1' => 'Contracts',
            ],
        ]);
    }

    public function paymentReturn(Request $request)
    {
        $contractId = (string) ($request->input('Xfield2') ?? '');
        $status = (string) ($request->input('Status') ?? '');

        if ($contractId === '') {
            return redirect()->route('contracts.index')->with([
                'error' => 'Unable to locate the contract payment.',
            ]);
        }

        if ($status === '1') {
            return redirect()->route('contracts.show', $contractId)->with([
                'success' => 'Payment submitted successfully. Contract status will update once confirmation is received.',
            ]);
        }

        return redirect()->route('contracts.show', $contractId)->with([
            'error' => 'Payment was not successful.',
        ]);
    }

    private function contractQuery()
    {
        return TenderCompartments::query()
            ->join('compartments as compartments', 'tender_compartments.compartment_id', '=', 'compartments.compartment_id', 'inner', false)
            ->join('racks as racks', 'compartments.rack_id', '=', 'racks.rack_id', 'inner', false)
            ->join('vendor_locations as vendor_locations', 'racks.vendor_location_id', '=', 'vendor_locations.id', 'inner', false)
            ->join('vendors as owners', 'vendor_locations.vendor_id', '=', 'owners.vendor_id', 'inner', false)
            ->leftJoin('vendors as assigned_vendors', 'tender_compartments.vendor_id', '=', 'assigned_vendors.vendor_id', 'left', false)
            ->select([
                'tender_compartments.*',
                'compartments.rack_id',
                'compartments.label as compartment_label',
                'racks.rack_name',
                'owners.vendor_name as owner_vendor_name',
                'assigned_vendors.vendor_name as assigned_vendor_name',
                DB::raw("CONCAT(owners.vendor_name, ' - ', vendor_locations.location_name) as vendor_location_name"),
            ]);
    }

    private function authorizeContractAccess(Request $request, string $vendorId): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ((string) $user->role === 'admin') {
            return;
        }

        $currentVendorId = $this->currentVendorId($request);
        if (!$currentVendorId || $currentVendorId !== $vendorId) {
            abort(403);
        }
    }

    private function currentVendorId(Request $request): ?string
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        return Vendors::query()
            ->where('user_id', $user->user_id)
            ->pluck('vendor_id')
            ->map(fn($id) => (string) $id)
            ->first();
    }

    private function contractSubtotal(float $bidPrice, int $durations): float
    {
        return round($bidPrice * $durations, 2);
    }

    private function activeChargesBreakdown(float $subtotal): array
    {
        return Charges::query()
            ->where('charges_status', true)
            ->whereDate('charges_start_date', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->where('charges_end_date', '>=', now()->toDateString())
                    ->orWhereNull('charges_end_date');
            })
            ->orderBy('sort_order')
            ->get([
                'charges_id',
                'charges_type',
                'charges_name',
                'charges_rate',
                'charges_description',
                'sort_order',
            ])
            ->map(function ($charge) use ($subtotal) {
                $type = strtoupper((string) $charge->charges_type);
                $rate = (float) $charge->charges_rate;
                $amount = $type === 'P'
                    ? round($subtotal * ($rate / 100), 2)
                    : round($rate, 2);

                return [
                    'charges_id' => (string) $charge->charges_id,
                    'charges_type' => $type,
                    'charges_name' => (string) $charge->charges_name,
                    'charges_rate' => $rate,
                    'charges_description' => (string) $charge->charges_description,
                    'amount' => $amount,
                    'sort_order' => $charge->sort_order,
                ];
            })
            ->values()
            ->all();
    }

    private function contractStocks(string $tenderCompartmentId): array
    {
        $stocks = CompartmentStocks::query()
            ->where('tender_compartment_id', $tenderCompartmentId)
            ->orderByDesc('created_at')
            ->get([
                'compartment_stock_id',
                'status',
                'created_at',
            ]);

        if ($stocks->isEmpty()) {
            return [];
        }

        $stockIds = $stocks->pluck('compartment_stock_id')->map(fn($id) => (string) $id)->all();

        $productsByStockId = CompartmentStockProducts::query()
            ->join('products as products', 'compartment_stock_products.product_id', '=', 'products.product_id', 'inner', false)
            ->whereIn('compartment_stock_products.compartment_stock_id', $stockIds)
            ->orderBy('products.product_name')
            ->get([
                'compartment_stock_products.compartment_stock_product_id',
                'compartment_stock_products.compartment_stock_id',
                'compartment_stock_products.product_id',
                'compartment_stock_products.quantity',
                'compartment_stock_products.expiry_date',
                'products.product_name',
                'products.product_code',
                'products.product_sku',
            ])
            ->groupBy('compartment_stock_id');

        return $stocks->map(function ($stock) use ($productsByStockId) {
            return [
                'compartment_stock_id' => (string) $stock->compartment_stock_id,
                'status' => (string) $stock->status,
                'created_at' => $stock->created_at ? $stock->created_at->toDateTimeString() : null,
                'products' => collect($productsByStockId->get($stock->compartment_stock_id, []))
                    ->map(fn($product) => [
                        'compartment_stock_product_id' => (string) $product->compartment_stock_product_id,
                        'product_id' => (string) $product->product_id,
                        'product_name' => (string) $product->product_name,
                        'product_code' => $product->product_code ? (string) $product->product_code : null,
                        'product_sku' => $product->product_sku ? (string) $product->product_sku : null,
                        'quantity' => (int) $product->quantity,
                        'expiry_date' => $product->expiry_date ? (string) $product->expiry_date : null,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    private function contractProductOptions(?string $vendorId): array
    {
        $query = Products::query()
            ->where('is_active', true)
            ->orderBy('product_name');

        // Products currently do not store vendor ownership, so vendor filtering
        // can be added here once the schema supports it.
        if ($vendorId) {
            $query = $query->where('vendor_id', $vendorId);
        }

        return $query
            ->get([
                'product_id',
                'product_name',
                'product_code',
                'product_sku',
            ])
            ->map(function ($product) {
                $parts = [(string) $product->product_name];
                $meta = array_values(array_filter([
                    $product->product_code ? (string) $product->product_code : null,
                    $product->product_sku ? (string) $product->product_sku : null,
                ]));

                if (!empty($meta)) {
                    $parts[] = '(' . implode(' / ', $meta) . ')';
                }

                return [
                    'value' => (string) $product->product_id,
                    'label' => implode(' ', $parts),
                ];
            })
            ->values()
            ->all();
    }

    private function editableStockStatuses(): array
    {
        return ['prepared', 'remove', 'removed'];
    }

    private function generateOrderNo(): string
    {
        return date('ymd') . '-' . strtoupper(Str::random(6));
    }
}
