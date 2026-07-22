<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderPickup;
use App\Models\OrderPickupItem;
use App\Models\Orders;
use App\Models\Vendors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchasePickupController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:pending_pickup,history'],
        ]);

        $tab = (string) ($validated['tab'] ?? 'pending_pickup');
        $status = $tab === 'history' ? 'picked_up' : 'pending_pickup';

        $rows = OrderPickup::query()
            ->join('orders', 'orders.order_id', '=', 'order_pickups.order_id')
            ->leftJoin('vendor_locations', 'vendor_locations.id', '=', 'order_pickups.vendor_location_id')
            ->leftJoin('vendors', 'vendors.vendor_id', '=', 'order_pickups.vendor_id')
            ->where('order_pickups.user_id', (string) $request->user()->user_id)
            ->where('order_pickups.pickup_status', $status)
            ->orderByDesc('order_pickups.created_at')
            ->get([
                'order_pickups.order_pickup_id',
                'order_pickups.pickup_status',
                'order_pickups.picked_up_at',
                'order_pickups.created_at',
                'order_pickups.vendor_location_id',
                'orders.order_no',
                'orders.total_payment',
                'vendors.vendor_name',
                'vendor_locations.location_name as vendor_location_name',
            ]);

        $pickupIds = $rows->pluck('order_pickup_id')->values();
        $itemRows = OrderPickupItem::query()
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'order_pickup_items.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->whereIn('order_pickup_items.order_pickup_id', $pickupIds)
            ->orderBy('order_pickup_items.created_at')
            ->get([
                'order_pickup_items.order_pickup_id',
                'order_pickup_items.product_name',
                'order_pickup_items.ordered_quantity',
                'primary_image.mobile_image_url',
                'primary_image.image_url',
            ])
            ->groupBy('order_pickup_id');

        return response()->json([
            'data' => $rows->map(function ($row) use ($itemRows) {
                $items = collect($itemRows->get((string) $row->order_pickup_id, []));
                $first = $items->first();
                $thumb = $first?->mobile_image_url ?: $first?->image_url;

                return [
                    'order_pickup_id' => (string) $row->order_pickup_id,
                    'order_no' => (string) $row->order_no,
                    'pickup_status' => (string) $row->pickup_status,
                    'vendor_name' => $row->vendor_name ? (string) $row->vendor_name : null,
                    'vendor_location_name' => $row->vendor_location_name ? (string) $row->vendor_location_name : null,
                    'picked_up_at' => $row->picked_up_at ? (string) $row->picked_up_at : null,
                    'created_at' => $row->created_at ? (string) $row->created_at : null,
                    'item_count' => $items->count(),
                    'first_item_name' => $first?->product_name ? (string) $first->product_name : null,
                    'first_item_thumbnail_url' => $thumb ? (string) $thumb : null,
                    'total_payment' => $row->total_payment !== null ? (float) $row->total_payment : 0.0,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, string $order_pickup_id)
    {
        $pickup = OrderPickup::query()
            ->leftJoin('orders', 'orders.order_id', '=', 'order_pickups.order_id')
            ->leftJoin('vendor_locations', 'vendor_locations.id', '=', 'order_pickups.vendor_location_id')
            ->leftJoin('vendors', 'vendors.vendor_id', '=', 'order_pickups.vendor_id')
            ->where('order_pickups.order_pickup_id', $order_pickup_id)
            ->where('order_pickups.user_id', (string) $request->user()->user_id)
            ->first([
                'order_pickups.*',
                'orders.order_no',
                'orders.total_payment',
                'vendors.vendor_name',
                'vendor_locations.location_name as vendor_location_name',
                'vendor_locations.address as vendor_location_address',
            ]);

        if (!$pickup) {
            return response()->json([
                'message' => 'Purchase not found.',
            ], 404);
        }

        $items = OrderPickupItem::query()
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'order_pickup_items.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->where('order_pickup_items.order_pickup_id', $order_pickup_id)
            ->get([
                'order_pickup_items.order_pickup_item_id',
                'order_pickup_items.product_name',
                'order_pickup_items.ordered_quantity',
                'order_pickup_items.picked_up_quantity',
                'order_pickup_items.vendor_location_name',
                'order_pickup_items.rack_name',
                'order_pickup_items.compartment_name',
                'primary_image.mobile_image_url',
                'primary_image.image_url',
            ])
            ->map(function ($item) {
                $thumb = $item->mobile_image_url ?: $item->image_url;
                return [
                    'order_pickup_item_id' => (string) $item->order_pickup_item_id,
                    'product_name' => (string) $item->product_name,
                    'ordered_quantity' => (int) $item->ordered_quantity,
                    'picked_up_quantity' => (int) $item->picked_up_quantity,
                    'vendor_location_name' => $item->vendor_location_name ? (string) $item->vendor_location_name : null,
                    'rack_name' => $item->rack_name ? (string) $item->rack_name : null,
                    'compartment_name' => $item->compartment_name ? (string) $item->compartment_name : null,
                    'thumbnail_url' => $thumb ? (string) $thumb : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'order_pickup_id' => (string) $pickup->order_pickup_id,
                'order_no' => (string) $pickup->order_no,
                'pickup_status' => (string) $pickup->pickup_status,
                'vendor_name' => $pickup->vendor_name ? (string) $pickup->vendor_name : null,
                'vendor_location_name' => $pickup->vendor_location_name ? (string) $pickup->vendor_location_name : null,
                'vendor_location_address' => $pickup->vendor_location_address ? (string) $pickup->vendor_location_address : null,
                'total_payment' => $pickup->total_payment !== null ? (float) $pickup->total_payment : 0.0,
                'picked_up_at' => $pickup->picked_up_at ? (string) $pickup->picked_up_at : null,
                'created_at' => $pickup->created_at ? (string) $pickup->created_at : null,
                'pickup_qr_value' => (string) json_encode($pickup->pickup_payload_json ?? []),
                'items' => $items,
            ],
        ]);
    }

    public function validateQr(Request $request)
    {
        $validated = $request->validate([
            'payload' => ['required'],
        ]);

        $vendorId = $this->currentVendorId($request);
        if (!$vendorId) {
            return response()->json([
                'message' => 'Vendor access is required.',
            ], 403);
        }

        $payload = $this->normalizePayloadInput($validated['payload']);
        if (!$payload) {
            return response()->json([
                'message' => 'Invalid purchase QR payload.',
            ], 422);
        }

        $pickup = OrderPickup::query()
            ->leftJoin('orders', 'orders.order_id', '=', 'order_pickups.order_id')
            ->leftJoin('users', 'users.user_id', '=', 'order_pickups.user_id')
            ->leftJoin('vendor_locations', 'vendor_locations.id', '=', 'order_pickups.vendor_location_id')
            ->where('order_pickups.order_pickup_id', (string) ($payload['order_pickup_id'] ?? ''))
            ->first([
                'order_pickups.*',
                'orders.order_no',
                'users.first_name',
                'users.last_name',
                'vendor_locations.location_name as vendor_location_name',
            ]);

        if (!$pickup) {
            return response()->json([
                'message' => 'Purchase pickup not found.',
            ], 404);
        }

        if ((string) $pickup->vendor_id !== $vendorId) {
            return response()->json([
                'message' => 'You are not allowed to validate this pickup.',
            ], 403);
        }

        $providedSignature = trim((string) ($payload['signature'] ?? ''));
        $storedPayload = (array) ($pickup->pickup_payload_json ?? []);
        if ($providedSignature === '' || !hash_equals((string) ($storedPayload['signature'] ?? ''), $providedSignature)) {
            return response()->json([
                'message' => 'Purchase QR signature is invalid.',
            ], 422);
        }

        if ((string) $pickup->pickup_status === 'picked_up') {
            return response()->json([
                'message' => 'This purchase has already been picked up.',
                'data' => $this->formatValidationData($pickup),
            ], 409);
        }

        if (!$pickup->scanned_at) {
            OrderPickup::query()
                ->whereKey((string) $pickup->order_pickup_id)
                ->update([
                    'scanned_at' => now(),
                    'scanned_by_user_id' => (string) $request->user()->user_id,
                    'updated_at' => now(),
                ]);

            $pickup = OrderPickup::query()
                ->leftJoin('orders', 'orders.order_id', '=', 'order_pickups.order_id')
                ->leftJoin('users', 'users.user_id', '=', 'order_pickups.user_id')
                ->leftJoin('vendor_locations', 'vendor_locations.id', '=', 'order_pickups.vendor_location_id')
                ->where('order_pickups.order_pickup_id', (string) $pickup->order_pickup_id)
                ->first([
                    'order_pickups.*',
                    'orders.order_no',
                    'users.first_name',
                    'users.last_name',
                    'vendor_locations.location_name as vendor_location_name',
                ]);
        }

        return response()->json([
            'data' => $this->formatValidationData($pickup),
        ]);
    }

    public function confirm(Request $request, string $order_pickup_id)
    {
        $vendorId = $this->currentVendorId($request);
        if (!$vendorId) {
            return response()->json([
                'message' => 'Vendor access is required.',
            ], 403);
        }

        $result = DB::transaction(function () use ($order_pickup_id, $request, $vendorId) {
            $pickup = OrderPickup::query()
                ->whereKey($order_pickup_id)
                ->lockForUpdate()
                ->first();

            if (!$pickup) {
                return [
                    'status' => 404,
                    'message' => 'Purchase pickup not found.',
                ];
            }

            if ((string) $pickup->vendor_id !== $vendorId) {
                return [
                    'status' => 403,
                    'message' => 'You are not allowed to confirm this pickup.',
                ];
            }

            if ((string) $pickup->pickup_status === 'picked_up') {
                return [
                    'status' => 409,
                    'message' => 'This purchase has already been picked up.',
                    'data' => [
                        'order_pickup_id' => (string) $pickup->order_pickup_id,
                        'pickup_status' => (string) $pickup->pickup_status,
                        'picked_up_at' => $pickup->picked_up_at ? (string) $pickup->picked_up_at : null,
                    ],
                ];
            }

            $now = now();

            $pickup->update([
                'pickup_status' => 'picked_up',
                'picked_up_at' => $now,
                'picked_up_by_user_id' => (string) $request->user()->user_id,
                'scanned_at' => $pickup->scanned_at ?? $now,
                'scanned_by_user_id' => $pickup->scanned_by_user_id ?? (string) $request->user()->user_id,
            ]);

            OrderPickupItem::query()
                ->where('order_pickup_id', $pickup->order_pickup_id)
                ->update([
                    'picked_up_quantity' => DB::raw('ordered_quantity'),
                    'updated_at' => $now,
                ]);

            Orders::query()
                ->where('order_id', $pickup->order_id)
                ->update([
                    'order_status' => 'completed',
                    'updated_at' => $now,
                ]);

            return [
                'status' => 200,
                'message' => 'Purchase pickup confirmed.',
                'data' => [
                    'order_pickup_id' => (string) $pickup->order_pickup_id,
                    'pickup_status' => 'picked_up',
                    'picked_up_at' => (string) $now,
                ],
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    private function formatValidationData(object $pickup): array
    {
        $items = OrderPickupItem::query()
            ->where('order_pickup_id', (string) $pickup->order_pickup_id)
            ->orderBy('created_at')
            ->get(['product_name', 'ordered_quantity', 'rack_name', 'compartment_name'])
            ->map(fn($item) => [
                'product_name' => (string) $item->product_name,
                'ordered_quantity' => (int) $item->ordered_quantity,
            ])
            ->values();

        return [
            'order_pickup_id' => (string) $pickup->order_pickup_id,
            'order_no' => (string) $pickup->order_no,
            'buyer_name' => trim(((string) ($pickup->first_name ?? '')) . ' ' . ((string) ($pickup->last_name ?? ''))),
            'vendor_location_name' => $pickup->vendor_location_name ? (string) $pickup->vendor_location_name : null,
            'pickup_status' => (string) $pickup->pickup_status,
            'scanned_at' => $pickup->scanned_at ? (string) $pickup->scanned_at : null,
            'picked_up_at' => $pickup->picked_up_at ? (string) $pickup->picked_up_at : null,
            'items' => $items,
        ];
    }

    private function normalizePayloadInput(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function currentVendorId(Request $request): ?string
    {
        return Vendors::query()
            ->where('user_id', (string) $request->user()->user_id)
            ->where('is_active', true)
            ->value('vendor_id');
    }
}
