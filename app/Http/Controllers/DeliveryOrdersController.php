<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\User;
use App\Models\Vendors;
use App\Models\VendorLocation;
use App\Services\DelyvaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DeliveryOrdersController extends Controller
{
    protected DelyvaService $delyvaService;

    public function __construct(DelyvaService $delyvaService)
    {
        $this->delyvaService = $delyvaService;
    }

    public static function pendingTrackingCount(?\App\Models\User $user): int
    {
        if (!$user) {
            return 0;
        }

        try {
            $query = Orders::query()
                ->whereNotNull('delivery_order_id')
                ->whereNotNull('delivery_order_no')
                ->whereNull('delivery_tracking_no');

            if ($user->role === 'vendor') {
                $vendorId = Vendors::query()
                    ->where('user_id', $user->user_id)
                    ->value('vendor_id');
                if (!$vendorId) {
                    return 0;
                }
                $locationIds = VendorLocation::query()
                    ->where('vendor_id', $vendorId)
                    ->pluck('id')
                    ->all();
                if (count($locationIds) === 0) {
                    return 0;
                }
                $query->whereIn('fulfillment_vendor_location_id', $locationIds);
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            Log::warning('[DeliveryOrders] pendingTrackingCount failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function applyRoleScope($query, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if ($user->role === 'vendor') {
            $vendorId = Vendors::query()
                ->where('user_id', $user->user_id)
                ->value('vendor_id');
            if (!$vendorId) {
                $query->whereRaw('1 = 0');
                return;
            }
            $locationIds = VendorLocation::query()
                ->where('vendor_id', $vendorId)
                ->pluck('id')
                ->all();
            if (count($locationIds) === 0) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->whereIn('fulfillment_vendor_location_id', $locationIds);
        }
    }

    public function index()
    {
        return Inertia::render('delivery-orders/delivery-orders');
    }

    public function showAll(Request $request)
    {
        $query = Orders::query()
            ->where('shipping_method', 'delivery')
            ->where('order_status', 'completed');
        // ->whereNotNull('delivery_order_id')
        // ->whereNotNull('delivery_order_no')
        // ->whereNull('delivery_tracking_no');

        $this->applyRoleScope($query, $request);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'like', "%{$search}%")
                    ->orWhere('user_id', 'like', "%{$search}%")
                    ->orWhere('delivery_order_no', 'like', "%{$search}%")
                    ->orWhere('shipping_service_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('sort')) {
            $field = $request->sort['field'];
            $allowed = ['order_no', 'order_date', 'created_at', 'total_payment', 'updated_at'];
            if (in_array($field, $allowed, true)) {
                $direction = strtolower((string) ($request->sort['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
                $query->orderBy($field, $direction);
            } else {
                $query->orderBy('orders.created_at', 'desc');
            }
        } else {
            $query->orderBy('orders.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $orders = $query->paginate($perPage);

        $items = [];
        foreach ($orders->items() as $order) {
            $customer = User::query()
                ->where('user_id', $order->user_id)
                ->first(['user_id', 'first_name', 'last_name', 'email', 'contact_no']);
            $items[] = [
                'order_id' => $order->order_id,
                'order_no' => $order->order_no,
                'order_date' => $order->order_date,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'total_payment' => $order->total_payment,
                'order_status' => $order->order_status,
                'shipping_provider' => $order->shipping_provider,
                'shipping_service_code' => $order->shipping_service_code,
                'shipping_service_name' => $order->shipping_service_name,
                'delivery_order_id' => $order->delivery_order_id,
                'delivery_order_no' => $order->delivery_order_no,
                'delivery_status' => $order->delivery_status,
                'fulfillment_vendor_location_id' => $order->fulfillment_vendor_location_id,
                'customer' => $customer ? [
                    'user_id' => $customer->user_id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'contact_no' => $customer->contact_no,
                ] : null,
            ];
        }

        return response()->json([
            'data' => $items,
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

    public function show(Request $request, Orders $deliveryOrder)
    {
        $checkQuery = Orders::query()->where('order_id', $deliveryOrder->order_id);
        $this->applyRoleScope($checkQuery, $request);
        $visible = $checkQuery->exists();
        if (!$visible) {
            abort(403);
        }

        $deliveryOrder->load([
            'orderItems.product:product_id,product_name,uom,product_sku',
        ]);

        $customer = User::query()
            ->where('user_id', $deliveryOrder->user_id)
            ->first(['user_id', 'first_name', 'last_name', 'email', 'contact_no']);

        $branch = null;
        if ($deliveryOrder->fulfillment_vendor_location_id) {
            $branch = VendorLocation::query()
                ->where('id', $deliveryOrder->fulfillment_vendor_location_id)
                ->first(['id', 'vendor_id', 'location_name', 'address', 'contact_no', 'is_primary']);
            if ($branch) {
                $vendor = Vendors::query()
                    ->where('vendor_id', $branch->vendor_id)
                    ->first(['vendor_id', 'vendor_name']);
                $branch = [
                    'id' => $branch->id,
                    'vendor_id' => $branch->vendor_id,
                    'vendor_name' => $vendor?->vendor_name,
                    'location_name' => $branch->location_name,
                    'address' => $branch->address,
                    'contact_no' => $branch->contact_no,
                    'is_primary' => (bool) $branch->is_primary,
                ];
            }
        }

        $shippingAddress = $deliveryOrder->shipping_address_json;

        return Inertia::render('delivery-orders/show', [
            'order' => [
                'order_id' => $deliveryOrder->order_id,
                'order_no' => $deliveryOrder->order_no,
                'order_date' => $deliveryOrder->order_date,
                'order_description' => $deliveryOrder->order_description,
                'total_price' => $deliveryOrder->total_price,
                'total_charges' => $deliveryOrder->total_charges,
                'total_discount' => $deliveryOrder->total_discount,
                'total_payment' => $deliveryOrder->total_payment,
                'shipping_method' => $deliveryOrder->shipping_method,
                'shipping_provider' => $deliveryOrder->shipping_provider,
                'shipping_service_code' => $deliveryOrder->shipping_service_code,
                'shipping_service_name' => $deliveryOrder->shipping_service_name,
                'shipping_fee' => $deliveryOrder->total_charges,
                'shipping_address_json' => $shippingAddress,
                'fulfillment_vendor_location_id' => $deliveryOrder->fulfillment_vendor_location_id,
                'fulfillment_branch' => $branch,
                'order_status' => $deliveryOrder->order_status,
                'discount_code' => $deliveryOrder->discount_code,
                'applied_voucher_discount' => $deliveryOrder->applied_voucher_discount,
                'wallet_credit_used' => $deliveryOrder->wallet_credit_used,
                'delivery_order_id' => $deliveryOrder->delivery_order_id,
                'delivery_order_no' => $deliveryOrder->delivery_order_no,
                'delivery_tracking_no' => $deliveryOrder->delivery_tracking_no,
                'delivery_status' => $deliveryOrder->delivery_status,
                'created_at' => $deliveryOrder->created_at,
                'updated_at' => $deliveryOrder->updated_at,
                'customer' => $customer ? [
                    'user_id' => $customer->user_id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'contact_no' => $customer->contact_no,
                ] : null,
                'order_items' => $deliveryOrder->orderItems->map(fn(OrderItems $item) => [
                    'order_item_id' => $item->order_item_id,
                    'product_id' => $item->product_id,
                    'line_type' => $item->line_type ?? 'product',
                    'source_id' => $item->source_id ?? null,
                    'line_name' => $item->line_name ?? $item->product?->product_name ?? 'Item',
                    'quantity' => (int) $item->quantity,
                    'uom' => $item->uom,
                    'unit_price' => $item->unit_price,
                    'tax' => $item->tax,
                    'discount' => $item->discount,
                    'total_price' => $item->total_price,
                    'product' => $item->product ? [
                        'product_id' => $item->product->product_id,
                        'product_name' => $item->product->product_name,
                        'uom' => $item->product->uom ?? '',
                        'product_sku' => $item->product->product_sku ?? null,
                    ] : null,
                ])->all(),
            ],
            'delyva_configured' => $this->delyvaService->isConfigured(),
        ]);
    }

    public function getConsignmentNo(string $deliveryOrderId)
    {
        $result = $this->delyvaService->getOrderDetails($deliveryOrderId);
        Log::info('Delyva getOrderDetails result:', $result);

        try {
            $consignmentNo = (string) (
                data_get($result, 'data.consignmentNo')
                ?? data_get($result, 'consignmentNo')
                ?? data_get($result, 'data.consignment_no')
                ?? data_get($result, 'data.orderNo')
                ?? data_get($result, 'orderNo')
                ?? ''
            );

            Orders::where('delivery_order_id', $deliveryOrderId)->update([
                'delivery_tracking_no' => $consignmentNo,
                'delivery_status' => 'Prepared',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating consignment number:', $e);
            return back()->with('error', 'Error updating consignment number.');
        }

        return back()->with('success', 'Consignment number updated successfully.');
    }

    public function confirmDelivery(Request $request, Orders $deliveryOrder)
    {
        $checkQuery = Orders::query()->where('order_id', $deliveryOrder->order_id);
        $this->applyRoleScope($checkQuery, $request);
        if (!$checkQuery->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'originScheduledAt' => ['required', 'date'],
        ]);

        if (!$this->delyvaService->isConfigured()) {
            return back()->with('error', 'Delyva is not configured.');
        }

        if (empty($deliveryOrder->delivery_order_id)) {
            return back()->with('error', 'Order has no Delyva delivery_order_id.');
        }

        $serviceCode = (string) ($deliveryOrder->shipping_service_code ?? '');
        if ($serviceCode === '') {
            return back()->with('error', 'Order has no shipping_service_code.');
        }

        try {
            $scheduled = new \DateTimeImmutable((string) $validated['originScheduledAt']);
            $originScheduledAt = $scheduled->format('c');

            $result = $this->delyvaService->processOrder(
                (string) $deliveryOrder->delivery_order_id,
                [
                    'serviceCode' => $serviceCode,
                    'originScheduledAt' => $originScheduledAt,
                    'destinationScheduledAt' => $originScheduledAt,
                ]
            );

            // if result is successful, run another delyva servie to get order details

            $result = $this->delyvaService->getOrderDetails((string) $deliveryOrder->delivery_order_id);
            Log::info('Delyva getOrderDetails result:', $result);
            $consignmentNo = (string) (
                data_get($result, 'data.consignmentNo')
                ?? data_get($result, 'consignmentNo')
                ?? data_get($result, 'data.consignment_no')
                ?? data_get($result, 'data.orderNo')
                ?? data_get($result, 'orderNo')
                ?? ''
            );

            // $consignmentNo = (string) (
            //     data_get($result, 'data.consignmentNo')
            //     ?? data_get($result, 'consignmentNo')
            //     ?? data_get($result, 'data.consignment_no')
            //     ?? data_get($result, 'data.orderNo')
            //     ?? data_get($result, 'orderNo')
            //     ?? ''
            // );
            // $trackingNo = (string) (
            //     data_get($result, 'data.trackingNo')
            //     ?? data_get($result, 'trackingNo')
            //     ?? data_get($result, 'data.tracking_no')
            //     ?? $consignmentNo
            // );

            DB::transaction(function () use ($deliveryOrder, $consignmentNo) {
                $updates = [];
                if ($consignmentNo !== '' && empty($deliveryOrder->delivery_order_no)) {
                    $updates['delivery_tracking_no'] = $consignmentNo;
                    $updates['delivery_status'] = 'prepared';
                }
                // if ($trackingNo !== '') {
                //     $updates['delivery_tracking_no'] = $trackingNo;
                //     if (empty($deliveryOrder->delivery_order_tracking_no)) {
                //         $updates['delivery_order_tracking_no'] = $trackingNo;
                //     }
                // }
                if (count($updates) > 0) {
                    $deliveryOrder->update($updates);
                }
                if ($deliveryOrder->order_status === 'pending' || $deliveryOrder->order_status === 'processing') {
                    $deliveryOrder->update(['order_status' => 'shipped']);
                }
            });

            return back()->with('success', 'Delivery confirmed successfully.');
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            Log::error('[DeliveryOrders] confirmDelivery failed: ', [
                'order_id' => $deliveryOrder->order_id,
                'delyva_order_id' => $deliveryOrder->delivery_order_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to confirm delivery.');
        }
    }

    public function printLabel(Request $request, Orders $deliveryOrder)
    {
        $checkQuery = Orders::query()->where('order_id', $deliveryOrder->order_id);
        $this->applyRoleScope($checkQuery, $request);
        if (!$checkQuery->exists()) {
            abort(403);
        }

        if (!$this->delyvaService->isConfigured()) {
            abort(400, 'Delyva is not configured.');
        }

        if (empty($deliveryOrder->delivery_order_id)) {
            abort(400, 'Order has no Delyva delivery_order_id.');
        }

        try {
            $labelBody = $this->delyvaService->getLabel((string) $deliveryOrder->delivery_order_id);
        } catch (\Throwable $e) {
            Log::error('[DeliveryOrders] printLabel failed: ' . $e->getMessage(), [
                'order_id' => $deliveryOrder->order_id,
                'delyva_order_id' => $deliveryOrder->delivery_order_id,
            ]);
            return back()->with('error', 'Failed to fetch label: ' . $e->getMessage());
        }

        $fallbackType = 'application/pdf';
        $sniff = substr($labelBody, 0, 4);
        if ($sniff === '%PDF') {
            $contentType = 'application/pdf';
        } elseif (str_starts_with($labelBody, '<!DOCTYP') || str_starts_with($labelBody, '<html') || str_starts_with($labelBody, '<HTML')) {
            $contentType = 'text/html';
        } else {
            $contentType = $fallbackType;
        }

        $filename = 'label-' . $deliveryOrder->order_no . '.' . ($contentType === 'application/pdf' ? 'pdf' : ($contentType === 'text/html' ? 'html' : 'bin'));

        return response()->stream(function () use ($labelBody) {
            echo $labelBody;
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($labelBody),
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function trackingHistory(Request $request, Orders $deliveryOrder)
    {
        $checkQuery = Orders::query()->where('order_id', $deliveryOrder->order_id);
        $this->applyRoleScope($checkQuery, $request);
        if (!$checkQuery->exists()) {
            return response()->json(['events' => []], 403);
        }

        if (!$this->delyvaService->isConfigured()) {
            return response()->json(['events' => [], 'error' => 'Delyva is not configured.']);
        }

        $consignmentNo = (string) (
            $deliveryOrder->delivery_tracking_no
            ?? $deliveryOrder->delivery_order_no
            ?? $deliveryOrder->delivery_order_tracking_no
            ?? ''
        );
        if ($consignmentNo === '') {
            return response()->json(['events' => [], 'error' => 'No consignment number available.']);
        }

        $companyId = (string) config('services.delyva.company_id');
        if ($companyId === '') {
            $companyId = (string) config('services.delyva.company_id');
        }
        if ($companyId === '') {
            return response()->json(['events' => [], 'error' => 'Delyva company/customer ID is not configured.']);
        }

        try {
            $resultType = $request->input('resultType', 'latestFirst');
            $result = $this->delyvaService->trackOrder($companyId, $consignmentNo, $resultType);

            $rawEvents = data_get($result, 'data.histories')
                ?? data_get($result, 'events')
                ?? data_get($result, 'data.trackingDetails')
                ?? data_get($result, 'trackingDetails')
                ?? [];
            if (!is_array($rawEvents)) {
                $rawEvents = [];
            }

            $events = [];
            foreach ($rawEvents as $idx => $evt) {
                $code = (string) data_get($evt, 'code', data_get($evt, 'eventCode', data_get($evt, 'status_code', '')));
                $location = (string) data_get($evt, 'location', data_get($evt, 'locationName', data_get($evt, 'location_name', '')));
                $status = (string) data_get($evt, 'statusText', data_get($evt, 'description', data_get($evt, 'event_description', '')));
                $description = (string) data_get($evt, 'description', data_get($evt, 'event_description', data_get($evt, 'message', $status)));
                $rawTime = data_get($evt, 'created_at', data_get($evt, 'eventDateTime', data_get($evt, 'createdAt', data_get($evt, 'date_time', null))));
                $parsedAt = null;
                if ($rawTime) {
                    try {
                        $dt = new \DateTimeImmutable(is_string($rawTime) ? $rawTime : (string) $rawTime);
                        $parsedAt = $dt->format('c');
                    } catch (\Throwable) {
                        $parsedAt = null;
                    }
                }
                $events[] = [
                    'index' => $idx,
                    'code' => $code,
                    'location' => $location,
                    'status' => $status,
                    'description' => $description,
                    'occurred_at' => $parsedAt,
                    'raw' => $evt,
                ];
            }

            $chronological = strtolower((string) $resultType) === 'chronological' || strtolower((string) $resultType) === 'oldestfirst';
            if (!$chronological) {
                usort($events, function ($a, $b) {
                    if ($a['occurred_at'] === null && $b['occurred_at'] === null) {
                        return $b['index'] <=> $a['index'];
                    }
                    if ($a['occurred_at'] === null) {
                        return 1;
                    }
                    if ($b['occurred_at'] === null) {
                        return -1;
                    }
                    return $b['occurred_at'] <=> $a['occurred_at'];
                });
            } else {
                usort($events, function ($a, $b) {
                    if ($a['occurred_at'] === null && $b['occurred_at'] === null) {
                        return $a['index'] <=> $b['index'];
                    }
                    if ($a['occurred_at'] === null) {
                        return 1;
                    }
                    if ($b['occurred_at'] === null) {
                        return -1;
                    }
                    return $a['occurred_at'] <=> $b['occurred_at'];
                });
            }

            return response()->json([
                'events' => $events,
                'consignmentNo' => $consignmentNo,
                'companyId' => $companyId,
                'raw' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DeliveryOrders] trackingHistory failed: ' . $e->getMessage(), [
                'order_id' => $deliveryOrder->order_id,
                'consignmentNo' => $consignmentNo,
            ]);
            return response()->json([
                'events' => [],
                'consignmentNo' => $consignmentNo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
