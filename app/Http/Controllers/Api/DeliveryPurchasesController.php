<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Services\DelyvaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryPurchasesController extends Controller
{
    protected DelyvaService $delyvaService;

    public function __construct(DelyvaService $delyvaService)
    {
        $this->delyvaService = $delyvaService;
    }

    private function applyOwnershipScope($query, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        $query->where('orders.user_id', (string) $user->user_id)
            ->where('orders.shipping_method', 'delivery');
    }

    private function resolveFirstThumbnail($row)
    {
        return $row->first_mobile_image_url ?: $row->first_image_url;
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->input('per_page', 10));
        if ($perPage < 1 || $perPage > 50) {
            $perPage = 10;
        }

        $rawQuery = Orders::query()
            ->selectRaw('
                orders.order_id,
                orders.order_no,
                orders.order_status,
                orders.delivery_status,
                orders.delivery_tracking_no,
                orders.delivery_order_no,
                orders.created_at,
                orders.updated_at,
                orders.total_payment,
                MIN(order_items.order_item_id) as first_order_item_id,
                MIN(order_items.line_name) as first_item_name,
                MIN(primary_image.mobile_image_url) as first_mobile_image_url,
                MIN(primary_image.image_url) as first_image_url,
                COUNT(DISTINCT order_items.order_item_id) as item_count
            ')
            ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.order_id')
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'order_items.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->groupBy([
                'orders.order_id',
                'orders.order_no',
                'orders.order_status',
                'orders.delivery_status',
                'orders.delivery_tracking_no',
                'orders.delivery_order_no',
                'orders.created_at',
                'orders.updated_at',
                'orders.total_payment',
            ]);

        $this->applyOwnershipScope($rawQuery, $request);
        $rawQuery->orderByDesc('orders.created_at');
        $paginator = $rawQuery->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($row) {
                $thumb = $this->resolveFirstThumbnail($row);
                return [
                    'order_id' => (string) $row->order_id,
                    'order_no' => (string) $row->order_no,
                    'order_status' => (string) $row->order_status,
                    'delivery_status' => $row->delivery_status ? (string) $row->delivery_status : null,
                    'delivery_tracking_no' => $row->delivery_tracking_no ? (string) $row->delivery_tracking_no : null,
                    'delivery_order_no' => $row->delivery_order_no ? (string) $row->delivery_order_no : null,
                    'created_at' => $row->created_at ? (string) $row->created_at : null,
                    'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
                    'total_payment' => $row->total_payment !== null ? (float) $row->total_payment : 0.0,
                    'item_count' => (int) ($row->item_count ?? 0),
                    'first_item_name' => $row->first_item_name ? (string) $row->first_item_name : null,
                    'first_item_thumbnail_url' => $thumb ? (string) $thumb : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, string $orderId)
    {
        $base = Orders::query()
            ->leftJoin('users', 'users.user_id', '=', 'orders.user_id')
            ->leftJoin('vendor_locations', 'vendor_locations.id', '=', 'orders.fulfillment_vendor_location_id')
            ->leftJoin('vendors', 'vendors.vendor_id', '=', 'vendor_locations.vendor_id')
            ->where('orders.order_id', $orderId);

        $this->applyOwnershipScope($base, $request);

        $order = $base->first([
            'orders.*',
            'users.first_name as user_first_name',
            'users.last_name as user_last_name',
            'users.email as user_email',
            'users.contact_no as user_contact_no',
            'vendor_locations.location_name as fulfillment_location_name',
            'vendor_locations.address as fulfillment_address',
            'vendor_locations.contact_no as fulfillment_contact_no',
            'vendors.vendor_name as fulfillment_vendor_name',
        ]);

        if (!$order) {
            return response()->json([
                'message' => 'Delivery order not found.',
            ], 404);
        }

        $itemRows = OrderItems::query()
            ->leftJoin('products', 'products.product_id', '=', 'order_items.product_id')
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'order_items.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->where('order_items.order_id', $order->order_id)
            ->orderBy('order_items.created_at')
            ->get([
                'order_items.order_item_id',
                'order_items.product_id',
                'order_items.line_name as product_name',
                'order_items.quantity',
                'order_items.uom',
                'order_items.unit_price',
                'order_items.discount',
                'order_items.total_price',
                'products.product_sku',
                'primary_image.mobile_image_url',
                'primary_image.image_url',
            ])
            ->map(function ($item) {
                $thumb = $item->mobile_image_url ?: $item->image_url;
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discount = (float) ($item->discount ?? 0);
                $qty = (int) ($item->quantity ?? 0);
                $subtotal = (float) ($item->total_price ?? ($unitPrice * $qty - $discount));
                return [
                    'order_item_id' => (string) $item->order_item_id,
                    'product_id' => $item->product_id ? (string) $item->product_id : null,
                    'product_name' => (string) $item->product_name,
                    'product_sku' => $item->product_sku ? (string) $item->product_sku : null,
                    'uom' => $item->uom ? (string) $item->uom : null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discount,
                    'subtotal' => $subtotal,
                    'thumbnail_url' => $thumb ? (string) $thumb : null,
                ];
            })
            ->values();

        $shippingAddress = $order->shipping_address_json ? (array) $order->shipping_address_json : [];

        $itemsSubtotal = 0.0;
        foreach ($itemRows as $i) {
            $itemsSubtotal += (float) ($i['subtotal'] ?? 0);
        }

        $shippingFee = (float) ($order->total_charges ?? 0);
        $voucherDiscount = (float) ($order->applied_voucher_discount ?? 0);
        $totalDiscount = (float) ($order->total_discount ?? 0);
        $walletCredit = (float) ($order->wallet_credit_used ?? 0);

        return response()->json([
            'data' => [
                'order_id' => (string) $order->order_id,
                'order_no' => (string) $order->order_no,
                'order_date' => (string) $order->order_date,
                'order_description' => $order->order_description ? (string) $order->order_description : null,
                'order_status' => (string) $order->order_status,
                'delivery_status' => $order->delivery_status ? (string) $order->delivery_status : null,
                'delivery_received_at' => $order->delivery_received_at ? (string) $order->delivery_received_at : null,
                'created_at' => $order->created_at ? (string) $order->created_at : null,
                'updated_at' => $order->updated_at ? (string) $order->updated_at : null,
                'customer' => [
                    'user_id' => (string) $order->user_id,
                    'first_name' => $order->user_first_name ? (string) $order->user_first_name : null,
                    'last_name' => $order->user_last_name ? (string) $order->user_last_name : null,
                    'email' => $order->user_email ? (string) $order->user_email : null,
                    'contact_no' => $order->user_contact_no ? (string) $order->user_contact_no : null,
                ],
                'shipping' => [
                    'shipping_provider' => $order->shipping_provider ? (string) $order->shipping_provider : null,
                    'shipping_service_code' => $order->shipping_service_code ? (string) $order->shipping_service_code : null,
                    'shipping_service_name' => $order->shipping_service_name ? (string) $order->shipping_service_name : null,
                    'shipping_fee' => $shippingFee,
                    'delivery_order_id' => $order->delivery_order_id ? (string) $order->delivery_order_id : null,
                    'delivery_order_no' => $order->delivery_order_no ? (string) $order->delivery_order_no : null,
                    'delivery_tracking_no' => $order->delivery_tracking_no ? (string) $order->delivery_tracking_no : null,
                    'delivery_order_tracking_no' => $order->delivery_order_tracking_no ? (string) $order->delivery_order_tracking_no : null,
                    'fulfillment_branch' => $order->fulfillment_vendor_location_id ? [
                        'id' => (int) $order->fulfillment_vendor_location_id,
                        'vendor_name' => $order->fulfillment_vendor_name ? (string) $order->fulfillment_vendor_name : null,
                        'location_name' => $order->fulfillment_location_name ? (string) $order->fulfillment_location_name : null,
                        'address' => $order->fulfillment_address ? (string) $order->fulfillment_address : null,
                        'contact_no' => $order->fulfillment_contact_no ? (string) $order->fulfillment_contact_no : null,
                    ] : null,
                    'shipping_address' => [
                        'name' => (string) ($shippingAddress['name'] ?? $shippingAddress['full_name'] ?? ''),
                        'contact_no' => (string) ($shippingAddress['contact_no'] ?? $shippingAddress['phone'] ?? ''),
                        'line1' => (string) ($shippingAddress['line1'] ?? $shippingAddress['address_1'] ?? ''),
                        'line2' => (string) ($shippingAddress['line2'] ?? $shippingAddress['address_2'] ?? ''),
                        'city' => (string) ($shippingAddress['city'] ?? ''),
                        'state' => (string) ($shippingAddress['state'] ?? ''),
                        'postcode' => (string) ($shippingAddress['postcode'] ?? $shippingAddress['zip'] ?? ''),
                        'country' => (string) ($shippingAddress['country'] ?? ''),
                        'latitude' => isset($shippingAddress['latitude']) ? (string) $shippingAddress['latitude'] : null,
                        'longitude' => isset($shippingAddress['longitude']) ? (string) $shippingAddress['longitude'] : null,
                    ],
                ],
                'items' => $itemRows,
                'totals' => [
                    'items_subtotal' => $itemsSubtotal,
                    'subtotal' => (float) ($order->total_price ?? 0),
                    'shipping_fee' => $shippingFee,
                    'total_discount' => $totalDiscount,
                    'voucher_discount' => $voucherDiscount,
                    'voucher_code' => $order->discount_code ? (string) $order->discount_code : null,
                    'wallet_credit_used' => $walletCredit,
                    'total_payment' => (float) ($order->total_payment ?? 0),
                ],
            ],
        ]);
    }

    public function markReceived(Request $request, string $orderId)
    {
        $order = Orders::query()
            ->where('order_id', $orderId)
            ->lockForUpdate()
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Delivery order not found.',
            ], 404);
        }

        if ((string) $order->user_id !== (string) $request->user()->user_id) {
            return response()->json([
                'message' => 'You are not allowed to update this order.',
            ], 403);
        }

        if ((string) $order->shipping_method !== 'delivery') {
            return response()->json([
                'message' => 'This order is not a delivery order.',
            ], 400);
        }

        if ((string) ($order->delivery_status ?? 'pending') === 'received') {
            return response()->json([
                'message' => 'This delivery has already been marked as received.',
                'data' => [
                    'delivery_status' => (string) $order->delivery_status,
                    'received_at' => $order->delivery_received_at ? (string) $order->delivery_received_at : null,
                ],
            ], 409);
        }

        try {
            DB::transaction(function () use ($order) {
                $now = now();
                $update = [
                    'delivery_status' => 'received',
                    'delivery_received_at' => $now,
                ];
                $orderStatusFlip = in_array((string) $order->order_status, ['pending', 'processing', 'shipped'], true);
                if ($orderStatusFlip) {
                    $update['order_status'] = 'completed';
                }
                $order->update($update);
            });

            $fresh = Orders::query()->where('order_id', $order->order_id)->first();

            return response()->json([
                'message' => 'Order marked as received.',
                'data' => [
                    'delivery_status' => (string) $fresh->delivery_status,
                    'order_status' => (string) $fresh->order_status,
                    'received_at' => $fresh->delivery_received_at ? (string) $fresh->delivery_received_at : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[DeliveryPurchases] markReceived failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to mark order as received. Please try again.',
            ], 500);
        }
    }

    public function tracking(Request $request, string $orderId)
    {
        $order = Orders::query()
            ->where('order_id', $orderId);

        $this->applyOwnershipScope($order, $request);

        $found = $order->first();
        if (!$found) {
            return response()->json([
                'events' => [],
                'message' => 'Delivery order not found.',
            ], 404);
        }

        $consignmentNo = (string) (
            $found->delivery_tracking_no
            ?? $found->delivery_order_no
            ?? $found->delivery_order_tracking_no
            ?? ''
        );
        if ($consignmentNo === '') {
            return response()->json([
                'events' => [],
                'error' => 'No consignment or tracking number available for this delivery yet.',
                'delivery_order_no' => $found->delivery_order_no ? (string) $found->delivery_order_no : null,
                'delivery_tracking_no' => $found->delivery_tracking_no ? (string) $found->delivery_tracking_no : null,
            ]);
        }

        if (!$this->delyvaService->isConfigured()) {
            return response()->json([
                'events' => [],
                'error' => 'Tracking provider is not configured.',
                'consignmentNo' => $consignmentNo,
            ]);
        }

        $companyId = (string) config('services.delyva.company_id');
        if ($companyId === '') {
            return response()->json([
                'events' => [],
                'error' => 'Tracking company ID is not configured.',
                'consignmentNo' => $consignmentNo,
            ]);
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
                ];
            }

            $chronological = strtolower((string) $resultType) === 'chronological' || strtolower((string) $resultType) === 'oldestfirst';
            if ($chronological) {
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
            } else {
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
            }

            return response()->json([
                'events' => $events,
                'consignmentNo' => $consignmentNo,
                'companyId' => $companyId,
                'delivery_tracking_no' => $found->delivery_tracking_no ? (string) $found->delivery_tracking_no : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DeliveryPurchases] tracking failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'consignmentNo' => $consignmentNo,
            ]);
            return response()->json([
                'events' => [],
                'consignmentNo' => $consignmentNo,
                'error' => 'Unable to load tracking information at this time.',
            ], 500);
        }
    }
}
