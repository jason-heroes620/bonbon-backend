<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EventPricingRule;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAnswer;
use App\Models\Events;
use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\OrderItems;
use App\Models\OrderPickup;
use App\Models\Orders;
use App\Models\Payments;
use App\Models\Products;
use App\Models\ReferralCodes;
use App\Models\Taxes;
use App\Models\User;
use App\Models\UserAddresses;
use App\Models\UserVoucherClaims;
use App\Models\UserVouchers;
use App\Models\UserMemberships;
use App\Models\Vouchers;
use App\Services\DelyvaService;
use App\Services\ProductPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CartController extends Controller
{
    protected ProductPricingService $pricingService;
    protected DelyvaService $delyvaService;

    public function __construct(ProductPricingService $pricingService, DelyvaService $delyvaService)
    {
        $this->pricingService = $pricingService;
        $this->delyvaService = $delyvaService;
    }

    public function show(Request $request)
    {
        $cart = $this->getOrCreateActiveCart((string) $request->user()->user_id);
        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->orderBy('created_at', 'asc')
            ->get();
        $productPricingDetails = $this->syncProductCartItemPricing($items);

        $productIds = $items->where('line_type', 'product')->pluck('source_id')->filter()->unique()->values();
        $eventIds = $items->where('line_type', 'event')->pluck('source_id')->filter()->unique()->values();

        $products = DB::table('products')
            ->leftJoin('vendors', 'vendors.vendor_id', '=', 'products.vendor_id')
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'products.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->whereIn('products.product_id', $productIds)
            ->select([
                'products.product_id',
                'products.product_name',
                'vendors.vendor_name',
                'primary_image.mobile_image_url',
                'primary_image.image_url',
            ])
            ->get()
            ->keyBy('product_id');

        $events = Events::query()
            ->whereIn('event_id', $eventIds)
            ->get(['event_id', 'event_name', 'event_image_path'])
            ->keyBy('event_id');

        $enrichedItems = $items->map(function (CartItem $item) use ($products, $events, $productPricingDetails) {
            $data = $item->toArray();
            $sourceId = (string) ($data['source_id'] ?? '');
            $lineType = (string) ($data['line_type'] ?? '');
            $meta = (array) ($data['metadata_json'] ?? []);

            $display = [
                'title' => null,
                'subtitle' => null,
                'thumbnail_url' => null,
            ];

            if ($lineType === 'product') {
                $row = $products->get($sourceId);
                $thumb = $row?->mobile_image_url ?: $row?->image_url;
                $fulfillmentType = (string) ($meta['fulfillment_type'] ?? 'pickup');
                $subtitleParts = array_values(array_filter([
                    $row?->vendor_name ? (string) $row->vendor_name : null,
                    !empty($meta['pickup_location_name']) ? (string) $meta['pickup_location_name'] : null,
                    $fulfillmentType === 'delivery' && !empty($meta['vendor_location_name'])
                        ? (string) $meta['vendor_location_name']
                        : null,
                ]));
                $display = [
                    'title' => $row?->product_name ? (string) $row->product_name : 'Product',
                    'subtitle' => !empty($subtitleParts) ? implode(' · ', $subtitleParts) : null,
                    'thumbnail_url' => $thumb ? (string) $thumb : null,
                ];
            } elseif ($lineType === 'event') {
                $event = $events->get($sourceId);
                $display = [
                    'title' => $event?->event_name ? (string) $event->event_name : 'Event',
                    'subtitle' => null,
                    'thumbnail_url' => $event?->event_image_path ? (string) $event->event_image_path : null,
                ];
            }

            $data['display'] = $display;
            if ($lineType === 'product') {
                $data['pricing'] = $productPricingDetails[(string) $item->cart_item_id] ?? null;
            }
            return $data;
        })->values();

        $voucherState = $this->resolveCartVoucherState((string) $request->user()->user_id, $cart, $items);
        $totals = $this->sumCartTotals(
            $items,
            (float) ($voucherState['applied']['discount_amount'] ?? 0),
            (float) ($cart->shipping_fee ?? 0)
        );

        return response()->json([
            'data' => [
                'cart' => $cart,
                'items' => $enrichedItems,
                'totals' => $totals,
                'voucher' => $voucherState,
                'checkout' => [
                    'fulfillment_method' => $cart->fulfillment_method,
                    'fulfillment_vendor_location_id' => $cart->fulfillment_vendor_location_id,
                    'shipping_address_id' => $cart->shipping_address_id,
                    'shipping_address' => $cart->shipping_address_json,
                    'shipping_provider' => $cart->shipping_provider,
                    'shipping_service_code' => $cart->shipping_service_code,
                    'shipping_service_name' => $cart->shipping_service_name,
                    'shipping_fee' => (float) ($cart->shipping_fee ?? 0),
                ],
            ],
        ]);
    }

    public function applyVoucher(Request $request)
    {
        $validated = $request->validate([
            'user_voucher_id' => ['present', 'nullable', 'uuid'],
            'voucher_id' => ['nullable', 'uuid'],
        ]);

        $userId = (string) $request->user()->user_id;

        return DB::transaction(function () use ($validated, $userId) {
            $activeCart = $this->getOrCreateActiveCart($userId);
            $cart = Cart::query()
                ->where('cart_id', $activeCart->cart_id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = CartItem::query()
                ->where('cart_id', $cart->cart_id)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $this->syncProductCartItemPricing($items);
            $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);

            $requestedUserVoucherId = isset($validated['user_voucher_id'])
                ? (string) $validated['user_voucher_id']
                : '';
            $requestedVoucherId = isset($validated['voucher_id'])
                ? (string) $validated['voucher_id']
                : '';

            if ($requestedUserVoucherId === '' && $requestedVoucherId === '') {
                $this->syncAppliedVoucher($cart, null, true);
            } else {
                $selectedVoucher = collect($voucherState['available'])
                    ->first(function ($voucher) use ($requestedUserVoucherId, $requestedVoucherId) {
                        $selectedUserVoucherId = (string) ($voucher['user_voucher_id'] ?? '');
                        $selectedVoucherId = (string) ($voucher['voucher_id'] ?? '');

                        return ($requestedUserVoucherId !== '' && $selectedUserVoucherId === $requestedUserVoucherId)
                            || ($requestedVoucherId !== '' && $selectedVoucherId === $requestedVoucherId);
                    });

                if (!$selectedVoucher) {
                    return response()->json([
                        'message' => 'Selected voucher is not available for this cart.',
                    ], 422);
                }

                if (!(bool) ($selectedVoucher['is_eligible'] ?? false)) {
                    return response()->json([
                        'message' => (string) ($selectedVoucher['disabled_reason'] ?? 'Selected voucher is not available for this cart.'),
                    ], 422);
                }

                if (empty($selectedVoucher['user_voucher_id'])) {
                    $claimedVoucher = $this->claimVoucherForCheckout($userId, $selectedVoucher);
                    if (!$claimedVoucher['ok']) {
                        return response()->json([
                            'message' => $claimedVoucher['message'],
                        ], 422);
                    }

                    $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);
                    $selectedVoucher = collect($voucherState['available'])
                        ->first(fn($voucher) => (string) ($voucher['user_voucher_id'] ?? '') === (string) ($claimedVoucher['user_voucher_id'] ?? ''));

                    if (!$selectedVoucher) {
                        return response()->json([
                            'message' => 'Selected voucher is not available for this cart.',
                        ], 422);
                    }
                }

                $this->syncAppliedVoucher($cart, $selectedVoucher, false);
            }

            $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);
            $totals = $this->sumCartTotals(
                $items,
                (float) ($voucherState['applied']['discount_amount'] ?? 0),
                (float) ($cart->shipping_fee ?? 0)
            );

            return response()->json([
                'data' => [
                    'cart' => $cart->fresh(),
                    'totals' => $totals,
                    'voucher' => $voucherState,
                ],
            ]);
        });
    }

    public function upsertItem(Request $request)
    {
        $validated = $request->validate([
            'line_type' => ['required', 'in:product,event'],
            'source_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'uom' => ['nullable', 'string', 'max:50'],
            'fulfillment_type' => ['nullable', 'in:pickup,delivery'],
            'clear_incompatible' => ['nullable', 'boolean'],
            'vendor_location_id' => ['nullable', 'integer'],
            'compartment_stock_id' => ['nullable', 'uuid'],
            'compartment_stock_product_id' => ['nullable', 'uuid'],
            'rack_id' => ['nullable', 'uuid'],
            'compartment_id' => ['nullable', 'uuid'],
            'pickup_location_name' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (string) $request->user()->user_id;
        $cart = $this->getOrCreateActiveCart($userId);

        if ($cart->expires_at && now()->greaterThan($cart->expires_at)) {
            $cart->update([
                'cart_status' => 'abandoned',
            ]);
            $cart = $this->getOrCreateActiveCart($userId);
        }

        $lineType = (string) $validated['line_type'];
        $sourceId = (string) $validated['source_id'];
        $quantity = (int) ($validated['quantity'] ?? 1);

        if ($lineType === 'event') {
            $quantity = 1;
        }

        return DB::transaction(function () use ($cart, $lineType, $sourceId, $quantity, $validated, $userId) {
            $cartItem = CartItem::query()
                ->where('cart_id', $cart->cart_id)
                ->where('line_type', $lineType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($lineType === 'product') {
                $product = Products::query()->whereKey($sourceId)->first();
                if (!$product || !$product->is_active) {
                    return response()->json([
                        'message' => 'Product is inactive or not found.',
                    ], 404);
                }

                $uom = (string) ($validated['uom'] ?? $product->uom ?? 'unit');
                $fulfillmentType = (string) ($validated['fulfillment_type'] ?? 'pickup');
                $clearIncompatible = (bool) ($validated['clear_incompatible'] ?? false);
                $vendorLocationId = isset($validated['vendor_location_id']) ? (int) $validated['vendor_location_id'] : null;
                $compartmentStockId = isset($validated['compartment_stock_id']) ? (string) $validated['compartment_stock_id'] : null;
                $compartmentStockProductId = isset($validated['compartment_stock_product_id']) ? (string) $validated['compartment_stock_product_id'] : null;

                $otherProductItems = CartItem::query()
                    ->where('cart_id', $cart->cart_id)
                    ->where('line_type', 'product')
                    ->when($cartItem, fn($q) => $q->where('cart_item_id', '!=', $cartItem->cart_item_id))
                    ->lockForUpdate()
                    ->get();

                $otherProductRows = Products::query()
                    ->whereIn('product_id', $otherProductItems->pluck('source_id')->filter()->unique()->values())
                    ->get(['product_id', 'vendor_id'])
                    ->keyBy('product_id');

                $otherProductVendorIds = $otherProductItems
                    ->map(function (CartItem $item) use ($otherProductRows) {
                        $meta = (array) ($item->metadata_json ?? []);
                        $fallbackVendorId = (string) ($otherProductRows->get((string) $item->source_id)?->vendor_id ?? '');
                        return trim((string) ($meta['vendor_id'] ?? $fallbackVendorId));
                    })
                    ->filter()
                    ->unique()
                    ->values();

                if ($otherProductVendorIds->isNotEmpty() && $otherProductVendorIds->contains(fn($vendorId) => $vendorId !== (string) $product->vendor_id)) {
                    return response()->json([
                        'message' => 'Your cart contains items from another vendor. Please complete the current order or remove it from your cart before proceeding.',
                    ], 422);
                }

                $conflictingProductItems = $otherProductItems->filter(function (CartItem $item) use ($fulfillmentType) {
                    $meta = (array) ($item->metadata_json ?? []);
                    return (string) ($meta['fulfillment_type'] ?? 'pickup') !== $fulfillmentType;
                })->values();

                if ($conflictingProductItems->isNotEmpty()) {
                    if (!$clearIncompatible) {
                        return response()->json([
                            'message' => 'Your cart already contains items from a different fulfillment type.',
                            'code' => 'cart_fulfillment_conflict',
                        ], 409);
                    }

                    CartItem::query()
                        ->whereIn('cart_item_id', $conflictingProductItems->pluck('cart_item_id')->all())
                        ->delete();
                }

                if ($fulfillmentType === 'delivery') {
                    if (!(bool) $product->delivery) {
                        return response()->json([
                            'message' => 'This product is not available for delivery.',
                        ], 422);
                    }

                    if (!$vendorLocationId) {
                        return response()->json([
                            'message' => 'Delivery branch is required.',
                        ], 422);
                    }

                    $vendorLocation = DB::table('vendor_locations')
                        ->leftJoin('vendors', 'vendors.vendor_id', '=', 'vendor_locations.vendor_id')
                        ->where('vendor_locations.id', $vendorLocationId)
                        ->where('vendor_locations.vendor_id', $product->vendor_id)
                        ->select([
                            'vendor_locations.id',
                            'vendor_locations.vendor_id',
                            'vendor_locations.location_name',
                            'vendor_locations.address',
                            'vendor_locations.latitude',
                            'vendor_locations.longitude',
                            'vendors.vendor_name',
                        ])
                        ->first();

                    if (!$vendorLocation) {
                        return response()->json([
                            'message' => 'Selected delivery branch is invalid.',
                        ], 422);
                    }

                    $otherDeliveryLocationId = $otherProductItems
                        ->map(function (CartItem $item) {
                            $meta = (array) ($item->metadata_json ?? []);
                            $value = $meta['vendor_location_id'] ?? null;
                            return $value !== null ? (int) $value : null;
                        })
                        ->filter(fn($value) => $value !== null)
                        ->first();

                    if ($otherDeliveryLocationId !== null && $otherDeliveryLocationId !== $vendorLocationId) {
                        return response()->json([
                            'message' => 'All delivery items in the cart must come from the same branch.',
                        ], 422);
                    }

                    $linePricing = $this->resolveProductCartLine($product, $quantity);

                    $payload = [
                        'cart_id' => $cart->cart_id,
                        'line_type' => 'product',
                        'source_id' => $sourceId,
                        'quantity' => $quantity,
                        'unit_price' => $linePricing['unit_price'],
                        'discount' => $linePricing['discount'],
                        'tax' => $linePricing['tax'],
                        'total_price' => $linePricing['total_price'],
                        'metadata_json' => [
                            'uom' => $uom,
                            'fulfillment_type' => 'delivery',
                            'vendor_location_id' => $vendorLocationId,
                            'vendor_location_name' => (string) ($vendorLocation->location_name ?? ''),
                            'vendor_location_address' => (string) ($vendorLocation->address ?? ''),
                            'vendor_location_latitude' => $vendorLocation->latitude !== null ? (float) $vendorLocation->latitude : null,
                            'vendor_location_longitude' => $vendorLocation->longitude !== null ? (float) $vendorLocation->longitude : null,
                            'vendor_id' => (string) ($vendorLocation->vendor_id ?? ''),
                            'vendor_name' => (string) ($vendorLocation->vendor_name ?? ''),
                        ],
                    ];

                    if ($cartItem) {
                        $cartItem->update($payload);
                    } else {
                        $cartItem = CartItem::create($payload);
                    }

                    $this->syncCartFulfillment($cart, 'delivery', $vendorLocationId);
                } else {
                    if (!$vendorLocationId || !$compartmentStockId || !$compartmentStockProductId) {
                        return response()->json([
                            'message' => 'Pickup location is required for product purchases.',
                        ], 422);
                    }

                    $otherPickupLocationId = $otherProductItems
                        ->map(function (CartItem $item) {
                            $meta = (array) ($item->metadata_json ?? []);
                            $value = $meta['vendor_location_id'] ?? null;
                            return $value !== null ? (int) $value : null;
                        })
                        ->filter(fn($value) => $value !== null)
                        ->first();

                    if ($otherPickupLocationId !== null && $otherPickupLocationId !== $vendorLocationId) {
                        return response()->json([
                            'message' => 'All pickup items in the cart must come from the same pickup location.',
                        ], 422);
                    }

                    $pickupStock = $this->fetchPickupStockContext(
                        $sourceId,
                        $vendorLocationId,
                        $compartmentStockId,
                        $compartmentStockProductId
                    );

                    if (!$pickupStock) {
                        return response()->json([
                            'message' => 'Selected pickup stock is not available.',
                        ], 422);
                    }

                    if ((int) $pickupStock->available_quantity < $quantity) {
                        return response()->json([
                            'message' => 'Selected pickup stock does not have enough quantity.',
                        ], 422);
                    }

                    $linePricing = $this->resolveProductCartLine($product, $quantity);

                    $payload = [
                        'cart_id' => $cart->cart_id,
                        'line_type' => 'product',
                        'source_id' => $sourceId,
                        'quantity' => $quantity,
                        'unit_price' => $linePricing['unit_price'],
                        'discount' => $linePricing['discount'],
                        'tax' => $linePricing['tax'],
                        'total_price' => $linePricing['total_price'],
                        'metadata_json' => [
                            'uom' => $uom,
                            'fulfillment_type' => 'pickup',
                            'vendor_location_id' => $vendorLocationId,
                            'pickup_location_name' => (string) ($validated['pickup_location_name'] ?? $pickupStock->vendor_location_name ?? ''),
                            'compartment_stock_id' => $compartmentStockId,
                            'compartment_stock_product_id' => $compartmentStockProductId,
                            'rack_id' => isset($validated['rack_id']) ? (string) $validated['rack_id'] : (string) ($pickupStock->rack_id ?? ''),
                            'compartment_id' => isset($validated['compartment_id']) ? (string) $validated['compartment_id'] : (string) ($pickupStock->compartment_id ?? ''),
                            'vendor_id' => (string) ($pickupStock->vendor_id ?? ''),
                            'vendor_name' => (string) ($pickupStock->vendor_name ?? ''),
                            'rack_name' => (string) ($pickupStock->rack_name ?? ''),
                            'compartment_name' => (string) ($pickupStock->compartment_name ?? ''),
                        ],
                    ];

                    if ($cartItem) {
                        $cartItem->update($payload);
                    } else {
                        $cartItem = CartItem::create($payload);
                    }

                    $this->syncCartFulfillment($cart, 'pickup', $vendorLocationId);
                }
            } else {
                $event = Events::query()->whereKey($sourceId)->first();
                if (!$event || !$event->is_active || !$event->is_published) {
                    return response()->json([
                        'message' => 'Event is inactive or not found.',
                    ], 404);
                }

                $this->assertEventRsvpWindow($event);
                $this->assertSeatAvailableAndHold($event, $userId, $cartItem?->cart_item_id);

                $membershipType = $this->getUserMembershipType($userId);
                $membershipTypeId = $this->getMembershipTypeIdByName($membershipType);
                $pricing = $this->resolveEventPricing($event, $membershipTypeId);

                $payload = [
                    'cart_id' => $cart->cart_id,
                    'line_type' => 'event',
                    'source_id' => (string) $event->event_id,
                    'quantity' => 1,
                    'unit_price' => (float) $pricing['unit_price'],
                    'discount' => (float) $pricing['discount_amount'],
                    'tax' => 0.0,
                    'total_price' => (float) $pricing['total_price'],
                    'metadata_json' => [
                        'uom' => 'ticket',
                    ],
                ];

                if ($cartItem) {
                    $cartItem->update($payload);
                } else {
                    $cartItem = CartItem::create($payload);
                }

                $registration = EventRegistration::query()
                    ->where('event_id', $event->event_id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                $seatHoldExpiresAt = null;
                if ($event->registration_type === 'paid' && !$event->is_unlimited_seats) {
                    $seatHoldExpiresAt = now()->addMinutes((int) $event->seat_hold_minutes);
                }

                $status = $event->registration_type === 'free' && !$event->require_questionnaire ? 'confirmed' : 'draft';

                if (!$registration) {
                    $registration = EventRegistration::create([
                        'event_id' => $event->event_id,
                        'user_id' => $userId,
                        'cart_item_id' => $cartItem->cart_item_id,
                        'order_id' => null,
                        'payment_id' => null,
                        'registration_status' => $status,
                        'seat_hold_expires_at' => $seatHoldExpiresAt,
                        'membership_type_at_registration' => $membershipType,
                        'price_before_discount' => (float) $pricing['price_before_discount'],
                        'discount_amount' => (float) $pricing['discount_amount'],
                        'price_paid' => (float) $pricing['total_price'],
                        'joined_at' => now(),
                        'confirmed_at' => $status === 'confirmed' ? now() : null,
                        'expired_at' => null,
                    ]);
                } elseif (in_array((string) $registration->registration_status, ['cancelled', 'expired'], true)) {
                    EventRegistrationAnswer::query()
                        ->where('event_registration_id', $registration->event_registration_id)
                        ->delete();

                    $registration->update([
                        'cart_item_id' => $cartItem->cart_item_id,
                        'order_id' => null,
                        'payment_id' => null,
                        'registration_status' => $status,
                        'seat_hold_expires_at' => $seatHoldExpiresAt,
                        'membership_type_at_registration' => $membershipType,
                        'price_before_discount' => (float) $pricing['price_before_discount'],
                        'discount_amount' => (float) $pricing['discount_amount'],
                        'price_paid' => (float) $pricing['total_price'],
                        'joined_at' => now(),
                        'confirmed_at' => $status === 'confirmed' ? now() : null,
                        'expired_at' => null,
                    ]);
                } else {
                    $registration->update([
                        'cart_item_id' => $cartItem->cart_item_id,
                    ]);
                }

                $cartItem->update([
                    'metadata_json' => array_merge(
                        (array) ($cartItem->metadata_json ?? []),
                        [
                            'uom' => 'ticket',
                            'event_registration_id' => (string) $registration->event_registration_id,
                        ],
                    ),
                ]);

                if ($registration->seat_hold_expires_at) {
                    $cart->update([
                        'expires_at' => $registration->seat_hold_expires_at,
                    ]);
                }
            }

            $items = CartItem::query()->where('cart_id', $cart->cart_id)->get();
            $this->syncProductCartItemPricing($items);
            $totals = $this->sumCartTotals(
                $items,
                0.0,
                (float) ($cart->shipping_fee ?? 0)
            );

            return response()->json([
                'data' => [
                    'cart' => $cart->fresh(),
                    'item' => $cartItem->fresh(),
                    'totals' => $totals,
                ],
            ]);
        });
    }

    public function removeItem(Request $request, string $cart_item_id)
    {
        $userId = (string) $request->user()->user_id;
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $item = CartItem::query()
            ->whereKey($cart_item_id)
            ->where('cart_id', $cart->cart_id)
            ->first();

        if (!$item) {
            return response()->json([
                'message' => 'Cart item not found.',
            ], 404);
        }

        DB::transaction(function () use ($item, $cart) {
            if ($item->line_type === 'event') {
                EventRegistration::query()
                    ->where('cart_item_id', $item->cart_item_id)
                    ->whereIn('registration_status', ['draft', 'pending_payment'])
                    ->update([
                        'registration_status' => 'cancelled',
                    ]);
            }

            $item->delete();

            if (!CartItem::query()->where('cart_id', $cart->cart_id)->where('line_type', 'product')->exists()) {
                $this->syncCartFulfillment($cart, null, null);
            }
        });

        return response()->json([
            'ok' => true,
        ]);
    }

    public function clear(Request $request)
    {
        $validated = $request->validate([
            'line_type' => ['nullable', 'in:product,event,all'],
        ]);

        $userId = (string) $request->user()->user_id;
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cart) {
            return response()->json([
                'ok' => true,
            ]);
        }

        $lineType = (string) ($validated['line_type'] ?? 'all');

        DB::transaction(function () use ($cart, $lineType) {
            $itemsQuery = CartItem::query()->where('cart_id', $cart->cart_id);
            if ($lineType !== 'all') {
                $itemsQuery->where('line_type', $lineType);
            }

            $items = $itemsQuery->get();

            $eventCartItemIds = $items
                ->where('line_type', 'event')
                ->pluck('cart_item_id')
                ->all();

            if (!empty($eventCartItemIds)) {
                EventRegistration::query()
                    ->whereIn('cart_item_id', $eventCartItemIds)
                    ->whereIn('registration_status', ['draft', 'pending_payment'])
                    ->update([
                        'registration_status' => 'cancelled',
                    ]);
            }

            $itemsQuery->delete();

            if (!CartItem::query()->where('cart_id', $cart->cart_id)->where('line_type', 'product')->exists()) {
                $this->syncCartFulfillment($cart, null, null);
            }
        });

        return response()->json([
            'ok' => true,
        ]);
    }

    public function deliveryQuotes(Request $request)
    {
        $validated = $request->validate([
            'user_address_id' => ['required', 'uuid'],
        ]);

        $userId = (string) $request->user()->user_id;
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->orderBy('created_at', 'asc')
            ->get();

        $context = $this->buildDeliveryCheckoutContext($cart, $items, (string) $validated['user_address_id'], $userId);
        if (!$context['ok']) {
            return response()->json([
                'message' => $context['message'],
            ], 422);
        }

        if (!$this->delyvaService->isConfigured()) {
            return response()->json([
                'message' => 'Delivery quotes are not configured yet.',
            ], 422);
        }

        $options = $this->delyvaService->quote(
            $context['origin'],
            $context['destination'],
            (float) $context['weight_kg']
        );

        $cart->update([
            'shipping_address_id' => $context['address']['user_address_id'],
            'shipping_address_json' => $context['address'],
        ]);

        return response()->json([
            'data' => [
                'address' => $context['address'],
                'origin' => $context['origin_summary'],
                'options' => $options,
            ],
        ]);
    }

    public function selectDeliveryOption(Request $request)
    {
        $validated = $request->validate([
            'user_address_id' => ['required', 'uuid'],
            'option' => ['required', 'array'],
            'option.service_code' => ['required', 'string', 'max:100'],
            'option.service_name' => ['required', 'string', 'max:150'],
            'option.provider_name' => ['nullable', 'string', 'max:150'],
            'option.amount' => ['required', 'numeric', 'min:0'],
            'option.currency' => ['nullable', 'string', 'max:10'],
            'option.raw' => ['nullable'],
        ]);

        $userId = (string) $request->user()->user_id;
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->orderBy('created_at', 'asc')
            ->get();

        $context = $this->buildDeliveryCheckoutContext($cart, $items, (string) $validated['user_address_id'], $userId);
        if (!$context['ok']) {
            return response()->json([
                'message' => $context['message'],
            ], 422);
        }

        $option = [
            'service_code' => (string) $validated['option']['service_code'],
            'service_name' => (string) $validated['option']['service_name'],
            'provider_name' => isset($validated['option']['provider_name'])
                ? (string) $validated['option']['provider_name']
                : (string) $validated['option']['service_name'],
            'amount' => round((float) $validated['option']['amount'], 2),
            'currency' => isset($validated['option']['currency']) ? (string) $validated['option']['currency'] : 'MYR',
            'raw' => $validated['option']['raw'] ?? $validated['option'],
        ];

        $this->syncCartShippingSelection($cart, $context['address'], $option);

        $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);
        $totals = $this->sumCartTotals(
            $items,
            (float) ($voucherState['applied']['discount_amount'] ?? 0),
            (float) ($cart->fresh()->shipping_fee ?? 0)
        );

        return response()->json([
            'data' => [
                'cart' => $cart->fresh(),
                'totals' => $totals,
            ],
        ]);
    }

    public function checkout(Request $request)
    {
        $userId = (string) $request->user()->user_id;
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
            ], 404);
        }

        if ($cart->expires_at && now()->greaterThan($cart->expires_at)) {
            return response()->json([
                'message' => 'Cart has expired.',
            ], 422);
        }

        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty.',
            ], 422);
        }

        $this->syncProductCartItemPricing($items);

        $productItems = $items->where('line_type', 'product')->values();
        $productFulfillment = null;
        if ($productItems->isNotEmpty()) {
            $firstProductMeta = (array) ($productItems->first()->metadata_json ?? []);
            $productFulfillment = (string) ($firstProductMeta['fulfillment_type'] ?? 'pickup');

            foreach ($productItems as $productItem) {
                $meta = (array) ($productItem->metadata_json ?? []);
                $lineFulfillment = (string) ($meta['fulfillment_type'] ?? 'pickup');

                if ($productFulfillment !== $lineFulfillment) {
                    return response()->json([
                        'message' => 'All product items in the cart must use the same fulfillment type.',
                    ], 422);
                }
            }
        }

        foreach ($items as $item) {
            if ((string) $item->line_type === 'product') {
                $meta = (array) ($item->metadata_json ?? []);
                $fulfillmentType = (string) ($meta['fulfillment_type'] ?? 'pickup');

                if ($fulfillmentType === 'delivery') {
                    $vendorLocationId = isset($meta['vendor_location_id']) ? (int) $meta['vendor_location_id'] : null;
                    if (!$vendorLocationId) {
                        return response()->json([
                            'message' => 'A product in your cart is missing delivery branch details.',
                        ], 422);
                    }

                    $vendorLocationExists = DB::table('vendor_locations')
                        ->where('id', $vendorLocationId)
                        ->exists();

                    if (!$vendorLocationExists) {
                        return response()->json([
                            'message' => 'A selected delivery branch is no longer available.',
                        ], 422);
                    }

                    continue;
                }

                $vendorLocationId = isset($meta['vendor_location_id']) ? (int) $meta['vendor_location_id'] : null;
                $compartmentStockId = isset($meta['compartment_stock_id']) ? (string) $meta['compartment_stock_id'] : null;
                $compartmentStockProductId = isset($meta['compartment_stock_product_id']) ? (string) $meta['compartment_stock_product_id'] : null;

                if (!$vendorLocationId || !$compartmentStockId || !$compartmentStockProductId) {
                    return response()->json([
                        'message' => 'A product in your cart is missing pickup location details.',
                    ], 422);
                }

                $pickupStock = $this->fetchPickupStockContext(
                    (string) $item->source_id,
                    $vendorLocationId,
                    $compartmentStockId,
                    $compartmentStockProductId
                );

                if (!$pickupStock) {
                    return response()->json([
                        'message' => 'A selected pickup stock item is no longer available.',
                    ], 422);
                }

                if ((int) $pickupStock->available_quantity < (int) $item->quantity) {
                    return response()->json([
                        'message' => 'A selected pickup stock item no longer has enough quantity.',
                    ], 422);
                }

                continue;
            }

            if ((string) $item->line_type !== 'event') {
                continue;
            }

            $registration = EventRegistration::query()
                ->where('cart_item_id', $item->cart_item_id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$registration) {
                return response()->json([
                    'message' => 'Missing event registration for cart item.',
                ], 422);
            }

            $event = Events::query()->whereKey($item->source_id)->first();
            if (!$event) {
                return response()->json([
                    'message' => 'Event not found for cart item.',
                ], 422);
            }

            if ($registration->seat_hold_expires_at && now()->greaterThan($registration->seat_hold_expires_at)) {
                $registration->update([
                    'registration_status' => 'expired',
                    'expired_at' => now(),
                ]);
                return response()->json([
                    'message' => 'Seat hold expired for an event in your cart.',
                ], 422);
            }

            if ($event->require_questionnaire) {
                $requiredCount = (int) DB::table('event_questionnaires')
                    ->where('event_id', $event->event_id)
                    ->where('is_active', true)
                    ->where('is_required', true)
                    ->count();

                $answeredDistinctCount = (int) DB::table('event_registration_answers')
                    ->where('event_registration_id', $registration->event_registration_id)
                    ->distinct('event_questionnaire_id')
                    ->count('event_questionnaire_id');

                if ($requiredCount > 0 && $answeredDistinctCount < $requiredCount) {
                    return response()->json([
                        'message' => 'Please answer all required questions before checkout.',
                    ], 422);
                }

                if ($event->registration_type === 'paid' && $registration->registration_status === 'draft') {
                    $registration->update([
                        'registration_status' => 'pending_payment',
                    ]);
                }
            }
        }

        if ($productFulfillment === 'delivery') {
            if (!$cart->shipping_address_id || !$cart->shipping_service_code) {
                return response()->json([
                    'message' => 'Please choose a delivery address and service before checkout.',
                ], 422);
            }

            $context = $this->buildDeliveryCheckoutContext($cart, $productItems, (string) $cart->shipping_address_id, $userId);
            if (!$context['ok']) {
                return response()->json([
                    'message' => $context['message'],
                ], 422);
            }
        }

        $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);
        $appliedVoucher = is_array($voucherState['applied'] ?? null)
            ? $voucherState['applied']
            : null;
        $totals = $this->sumCartTotals(
            $items,
            (float) ($appliedVoucher['discount_amount'] ?? 0),
            (float) ($cart->shipping_fee ?? 0)
        );

        $merchantCode = config('services.ipay88.code');
        $merchantKey = config('services.ipay88.key');

        $refNo = $this->generateOrderNo();
        $currency = "MYR";
        $amountRaw = number_format((float) $totals['total_payment'], 2, '.', '');
        $amount = str_replace(['.', ','], '', $amountRaw);
        $payloadToHash = $merchantKey . $merchantCode . $refNo . $amount . $currency;
        $signature = hash_hmac('sha512', $payloadToHash, $merchantKey);

        $productIds = $items->where('line_type', 'product')->pluck('source_id')->filter()->unique()->values();
        $eventIds = $items->where('line_type', 'event')->pluck('source_id')->filter()->unique()->values();

        $products = Products::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_name'])
            ->keyBy('product_id');

        $events = Events::query()
            ->whereIn('event_id', $eventIds)
            ->get(['event_id', 'event_name', 'event_start_date', 'event_start_time', 'event_end_time'])
            ->keyBy('event_id');

        $orderDescription = $this->buildOrderDescription($items, $products, $events);

        DB::transaction(function () use ($cart, $items, $totals, $refNo, $userId, $orderDescription, $products, $events, $appliedVoucher, $productFulfillment) {
            $order = Orders::create([
                'user_id' => $userId,
                'order_no' => $refNo,
                'order_date' => now()->toDateString(),
                'order_description' => $orderDescription,
                'total_price' => (float) $totals['subtotal'],
                'total_discount' => (float) $totals['total_discount'],
                'total_payment' => (float) $totals['total_payment'],
                'shipping_method' => $productFulfillment === 'delivery' ? 'delivery' : 'pickup',
                'fulfillment_vendor_location_id' => $cart->fulfillment_vendor_location_id,
                'shipping_address' => $productFulfillment === 'delivery'
                    ? json_encode($cart->shipping_address_json)
                    : null,
                'shipping_address_id' => $productFulfillment === 'delivery' ? $cart->shipping_address_id : null,
                'shipping_address_json' => $productFulfillment === 'delivery' ? $cart->shipping_address_json : null,
                'shipping_provider' => $productFulfillment === 'delivery' ? $cart->shipping_provider : null,
                'shipping_service_code' => $productFulfillment === 'delivery' ? $cart->shipping_service_code : null,
                'shipping_service_name' => $productFulfillment === 'delivery' ? $cart->shipping_service_name : null,
                'shipping_quote_payload' => $productFulfillment === 'delivery' ? $cart->shipping_quote_payload : null,
                'fulfillment_type' => match ($productFulfillment) {
                    'delivery' => 'delivery',
                    'pickup' => 'pickup',
                    default => 'na',
                },
                'billing_address' => null,
                'discount_code' => $appliedVoucher['voucher_code'] ?? null,
                'applied_user_voucher_id' => $appliedVoucher['user_voucher_id'] ?? null,
                'applied_voucher_id' => $appliedVoucher['voucher_id'] ?? null,
                'applied_voucher_discount' => (float) ($appliedVoucher['discount_amount'] ?? 0),
                'wallet_credit_used' => 0,
                'order_status' => 'pending',
                'total_charges' => (float) ($productFulfillment === 'delivery' ? ($cart->shipping_fee ?? 0) : 0),
            ]);

            foreach ($items as $item) {
                $meta = (array) ($item->metadata_json ?? []);
                $uom = (string) ($meta['uom'] ?? ($item->line_type === 'event' ? 'ticket' : 'unit'));

                if ($item->line_type === 'event') {
                    $eventName = (string) ($events[$item->source_id]?->event_name ?? 'Event');
                    OrderItems::create([
                        'order_id' => $order->order_id,
                        'product_id' => null,
                        'line_type' => 'event',
                        'source_id' => $item->source_id,
                        'line_name' => $eventName,
                        'line_description' => null,
                        'quantity' => (int) $item->quantity,
                        'uom' => $uom,
                        'unit_price' => (float) $item->unit_price,
                        'tax' => (float) $item->tax,
                        'discount' => (float) $item->discount,
                        'total_price' => (float) $item->total_price,
                    ]);

                    EventRegistration::query()
                        ->where('cart_item_id', $item->cart_item_id)
                        ->where('user_id', $userId)
                        ->whereIn('registration_status', ['draft', 'pending_payment'])
                        ->update([
                            'order_id' => $order->order_id,
                            'registration_status' => 'pending_payment',
                        ]);
                } else {
                    $productName = (string) ($products[$item->source_id]?->product_name ?? 'Product');
                    OrderItems::create([
                        'order_id' => $order->order_id,
                        'product_id' => $item->source_id,
                        'line_type' => 'product',
                        'source_id' => $item->source_id,
                        'line_name' => $productName,
                        'line_description' => null,
                        'quantity' => (int) $item->quantity,
                        'uom' => $uom,
                        'unit_price' => (float) $item->unit_price,
                        'tax' => (float) $item->tax,
                        'discount' => (float) $item->discount,
                        'total_price' => (float) $item->total_price,
                    ]);
                }
            }

            $cart->update([
                'cart_status' => 'pending_payment',
            ]);
        });

        return response()->json([
            'merchantCode' => $merchantCode,
            'refNo' => $refNo,
            'amount' => $amountRaw,
            'currency' => $currency,
            'signature' => $signature,
            'prodDesc' => $orderDescription,
            'userName' => $request->user()->last_name . " " . $request->user()->first_name,
            'userEmail' => $request->user()->email,
            'responseUrl' => url('/api/payments/frontend-callback'),
            'backendUrl' => url('/api/payments/backend-callback'),
            //     'responseUrl' => 'https://merchant.bonbon.com.my/api/payments/frontend-callback',
            //     'backendUrl' => 'https://merchant.bonbon.com.my/api/payments/backend-callback',
        ]);
    }

    public function simulateCheckoutSuccess(Request $request)
    {
        if (!app()->environment(['local', 'staging', 'testing'])) {
            abort(403, 'DEV-only endpoint disabled in production.');
        }
        if (config('app.env') === 'production') {
            abort(403, 'DEV-only endpoint disabled in production.');
        }

        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        $userId = (string) $user->user_id;

        $cart = Cart::query()
            ->where('user_id', $userId)
            ->where('cart_status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$cart) {
            abort(422, 'No active cart found.');
        }

        $items = CartItem::query()
            ->where('cart_id', $cart->cart_id)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($items->count() === 0) {
            abort(422, 'Your cart is empty.');
        }

        $productItems = $items->where('line_type', 'product')->values();
        $productFulfillment = null;
        if ($productItems->isNotEmpty()) {
            $firstProductMeta = (array) ($productItems->first()->metadata_json ?? []);
            $productFulfillment = (string) ($firstProductMeta['fulfillment_type'] ?? 'pickup');

            foreach ($productItems as $productItem) {
                $meta = (array) ($productItem->metadata_json ?? []);
                $lineFulfillment = (string) ($meta['fulfillment_type'] ?? 'pickup');

                if ($productFulfillment !== $lineFulfillment) {
                    abort(422, 'Cart contains mixed fulfillment. Remove items or switch to one mode.');
                }
            }
        }

        if ($productFulfillment === 'delivery') {
            if (empty($cart->shipping_address_id) || empty($cart->shipping_service_code)) {
                abort(422, 'Please select a delivery address and courier service before checkout.');
            }
        }

        $voucherState = $this->resolveCartVoucherState($userId, $cart, $items);
        $appliedVoucher = $voucherState['applied'] ?? [];
        $totals = $this->sumCartTotals(
            $items,
            (float) ($appliedVoucher['discount_amount'] ?? 0),
            (float) ($cart->shipping_fee ?? 0),
        );

        $refNo = 'SIM-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $productIds = $items->where('line_type', 'product')->pluck('source_id')->all();
        $eventIds = $items->where('line_type', 'event')->pluck('source_id')->all();

        $products = Products::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_name'])
            ->keyBy('product_id');

        $events = Events::query()
            ->whereIn('event_id', $eventIds)
            ->get(['event_id', 'event_name', 'event_start_date', 'event_start_time', 'event_end_time'])
            ->keyBy('event_id');

        $orderDescription = $this->buildOrderDescription($items, $products, $events);
        $delyvaResult = ['ok' => false, 'order_no' => null, 'tracking_no' => null, 'error' => null];

        DB::transaction(function () use (
            $cart,
            $items,
            $totals,
            $refNo,
            $userId,
            $orderDescription,
            $products,
            $events,
            $appliedVoucher,
            $productFulfillment,
            $user,
            &$delyvaResult,
        ) {
            $order = Orders::create([
                'user_id' => $userId,
                'order_no' => $refNo,
                'order_date' => now()->toDateString(),
                'order_description' => $orderDescription,
                'total_price' => (float) $totals['subtotal'],
                'total_discount' => (float) $totals['total_discount'],
                'total_payment' => (float) $totals['total_payment'],
                'shipping_method' => $productFulfillment === 'delivery' ? 'delivery' : 'pickup',
                'fulfillment_vendor_location_id' => $cart->fulfillment_vendor_location_id,
                'shipping_address' => $productFulfillment === 'delivery'
                    ? json_encode($cart->shipping_address_json)
                    : null,
                'shipping_address_id' => $productFulfillment === 'delivery' ? $cart->shipping_address_id : null,
                'shipping_address_json' => $productFulfillment === 'delivery' ? $cart->shipping_address_json : null,
                'shipping_provider' => $productFulfillment === 'delivery' ? $cart->shipping_provider : null,
                'shipping_service_code' => $productFulfillment === 'delivery' ? $cart->shipping_service_code : null,
                'shipping_service_name' => $productFulfillment === 'delivery' ? $cart->shipping_service_name : null,
                'shipping_quote_payload' => $productFulfillment === 'delivery' ? $cart->shipping_quote_payload : null,
                'fulfillment_type' => $productFulfillment === 'delivery' ? 'delivery' : ($productFulfillment === 'pickup' ? 'pickup' : 'na'),
                'billing_address' => null,
                'discount_code' => $appliedVoucher['voucher_code'] ?? null,
                'applied_user_voucher_id' => $appliedVoucher['user_voucher_id'] ?? null,
                'applied_voucher_id' => $appliedVoucher['voucher_id'] ?? null,
                'applied_voucher_discount' => (float) ($appliedVoucher['discount_amount'] ?? 0),
                'wallet_credit_used' => 0,
                'order_status' => 'pending',
                'total_charges' => (float) ($productFulfillment === 'delivery' ? ($cart->shipping_fee ?? 0) : 0),
            ]);

            foreach ($items as $item) {
                $meta = (array) ($item->metadata_json ?? []);
                $uom = (string) ($meta['uom'] ?? ($item->line_type === 'event' ? 'ticket' : 'unit'));

                if ($item->line_type === 'event') {
                    $eventName = (string) ($events[$item->source_id]?->event_name ?? 'Event');
                    OrderItems::create([
                        'order_id' => $order->order_id,
                        'product_id' => null,
                        'line_type' => 'event',
                        'source_id' => $item->source_id,
                        'line_name' => $eventName,
                        'line_description' => null,
                        'quantity' => (int) $item->quantity,
                        'uom' => $uom,
                        'unit_price' => (float) $item->unit_price,
                        'tax' => (float) $item->tax,
                        'discount' => (float) $item->discount,
                        'total_price' => (float) $item->total_price,
                    ]);

                    EventRegistration::query()
                        ->where('cart_item_id', $item->cart_item_id)
                        ->where('user_id', $userId)
                        ->whereIn('registration_status', ['draft', 'pending_payment'])
                        ->update([
                            'order_id' => $order->order_id,
                            'registration_status' => 'confirmed',
                            'confirmed_at' => now(),
                        ]);
                } else {
                    $productName = (string) ($products[$item->source_id]?->product_name ?? 'Product');
                    OrderItems::create([
                        'order_id' => $order->order_id,
                        'product_id' => $item->source_id,
                        'line_type' => 'product',
                        'source_id' => $item->source_id,
                        'line_name' => $productName,
                        'line_description' => null,
                        'quantity' => (int) $item->quantity,
                        'uom' => $uom,
                        'unit_price' => (float) $item->unit_price,
                        'tax' => (float) $item->tax,
                        'discount' => (float) $item->discount,
                        'total_price' => (float) $item->total_price,
                    ]);
                }
            }

            $cart->update(['cart_status' => 'checked_out']);

            Payments::updateOrCreate(
                ['order_no' => $refNo],
                [
                    'order_no' => $refNo,
                    'ref_no' => $refNo,
                    'transaction_id' => 'DEV_SIMULATED_' . $refNo,
                    'payment_amount' => (float) $totals['total_payment'],
                    'payment_date' => now()->format('Y-m-d H:i:s'),
                    'issuing_bank' => 'DEV_SIMULATED',
                    'cc_name' => 'DEV SIMULATED',
                    'cc_number' => '4111-XXXX-XXXX-1111',
                    'payment_status' => '1',
                ]
            );

            $pickupCreated = false;
            if ($productFulfillment === 'pickup') {
                $pickupCreated = $this->simulateFulfillProductPickupOrder($order, $cart, $userId);
            }

            $order->update([
                'order_status' => $pickupCreated ? 'processing' : 'completed',
            ]);

            $this->simulateFinalizeAppliedVoucherRedemption($order);

            if ($productFulfillment === 'delivery' && $this->delyvaService->isConfigured()) {
                $delyvaResult = $this->simulateCreateDelyvaOrder($order, $cart, $items, $userId, $products);
                if (!empty($delyvaResult['order_no']) || !empty($delyvaResult['tracking_no'])) {
                    $order->update([
                        'delivery_order_id' => $delyvaResult['id'],
                        'delivery_order_no' => $delyvaResult['nanoId'],
                        'delivery_tracking_no' => $delyvaResult['tracking_no'] ?? $order->delivery_tracking_no,
                    ]);
                }
            }

            $this->simulateProcessMembershipReferralSideEffects($order, $user);
        });

        return response()->json([
            'success' => true,
            'refNo' => $refNo,
            'simulated' => true,
            'payment_status' => 'paid',
            'delyva' => $delyvaResult,
        ]);
    }

    private function simulateFulfillProductPickupOrder(Orders $order, Cart $pendingCart, string $actorUserId): bool
    {
        $cartItems = CartItem::query()
            ->where('cart_id', $pendingCart->cart_id)
            ->where('line_type', 'product')
            ->get()
            ->keyBy('source_id');

        if ($cartItems->isEmpty()) {
            return false;
        }

        $orderItems = OrderItems::query()
            ->where('order_id', $order->order_id)
            ->where('line_type', 'product')
            ->get();

        if ($orderItems->isEmpty()) {
            return false;
        }

        $firstPickupMeta = null;
        foreach ($orderItems as $orderItem) {
            $cartItem = $cartItems->get((string) $orderItem->source_id);
            $meta = (array) ($cartItem?->metadata_json ?? []);
            if (!empty($meta['compartment_stock_product_id']) && !empty($meta['vendor_location_id'])) {
                $firstPickupMeta = $meta;
                break;
            }
        }

        if (!$firstPickupMeta) {
            return false;
        }

        $pickupCode = strtoupper(Str::random(12));
        OrderPickup::query()->firstOrCreate(
            ['order_id' => $order->order_id],
            [
                'user_id' => (string) $order->user_id,
                'vendor_id' => (string) ($firstPickupMeta['vendor_id'] ?? ''),
                'vendor_location_id' => (int) $firstPickupMeta['vendor_location_id'],
                'fulfillment_method' => 'pickup',
                'pickup_status' => 'pending_pickup',
                'pickup_code' => $pickupCode,
                'pickup_payload_json' => null,
                'pickup_signature_hash' => null,
                'qr_issued_at' => now(),
            ]
        );

        return true;
    }

    private function simulateFinalizeAppliedVoucherRedemption(Orders $order): void
    {
        if (
            empty($order->applied_user_voucher_id)
            || empty($order->applied_voucher_id)
            || $order->voucher_redeemed_at
        ) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Orders::query()
                ->where('order_id', $order->order_id)
                ->lockForUpdate()
                ->first();

            if (
                !$lockedOrder
                || empty($lockedOrder->applied_user_voucher_id)
                || empty($lockedOrder->applied_voucher_id)
                || $lockedOrder->voucher_redeemed_at
            ) {
                return;
            }

            $userVoucher = UserVouchers::query()
                ->where('user_voucher_id', $lockedOrder->applied_user_voucher_id)
                ->where('user_id', $lockedOrder->user_id)
                ->where('voucher_id', $lockedOrder->applied_voucher_id)
                ->lockForUpdate()
                ->first();

            $voucher = Vouchers::query()
                ->where('voucher_id', $lockedOrder->applied_voucher_id)
                ->lockForUpdate()
                ->first();

            if (!$userVoucher || !$voucher || !$userVoucher->is_valid) {
                return;
            }

            $claimLimit = max(1, (int) ($voucher->voucher_claim_per_user ?? 1));
            $userClaimCount = (int) UserVoucherClaims::query()
                ->where('user_voucher_id', $userVoucher->user_voucher_id)
                ->count();

            if ($userClaimCount >= $claimLimit) {
                $userVoucher->update(['is_valid' => false]);
                return;
            }

            UserVoucherClaims::query()->create([
                'user_voucher_id' => $userVoucher->user_voucher_id,
                'claimed_at' => now(),
            ]);

            if (($userClaimCount + 1) >= $claimLimit) {
                $userVoucher->update(['is_valid' => false]);
            }

            $lockedOrder->update([
                'voucher_redeemed_at' => now(),
            ]);
        });
    }

    private function simulateCreateDelyvaOrder(Orders $order, Cart $cart, $items, string $userId, $products): array
    {
        try {
            $addressId = (string) ($cart->shipping_address_id ?? '');
            if ($addressId === '') {
                return ['ok' => false, 'order_no' => null, 'tracking_no' => null, 'error' => 'Missing shipping_address_id'];
            }

            $ctx = $this->buildDeliveryCheckoutContext($cart, $items, $addressId, $userId);
            if (!($ctx['ok'] ?? false)) {
                return ['ok' => false, 'order_no' => null, 'tracking_no' => null, 'error' => $ctx['message'] ?? 'Invalid delivery context'];
            }

            $origin = $ctx['origin'] ?? [];
            $destination = $ctx['destination'] ?? [];
            $weightKg = (float) ($ctx['weight_kg'] ?? 0.1);

            $orderItemsPayload = [];
            foreach ($items as $item) {
                if ($item->line_type !== 'product') {
                    continue;
                }
                $orderItemsPayload[] = [
                    'name' => (string) ($products[$item->source_id]?->product_name ?? 'Product'),
                    'type' => 'PARCEL',
                    'quantity' => (int) $item->quantity,
                    'price' => [
                        'amount' => round((float) $item->unit_price, 2),
                        'currency' => 'MYR',
                    ],
                    'weight' => [
                        'unit' => 'kg',
                        'value' => round($weightKg, 2),
                    ],
                ];
            }




            $selectedServiceCode = (string) ($cart->shipping_service_code ?? '');
            $selectedProvider = (string) ($cart->shipping_provider ?? '');
            $customerId = trim((string) config('services.delyva.customer_id'));

            $payload = [
                'customerId' => $customerId,
                'origin' => [
                    'contact' => $origin ?? [],
                    'inventory' => $orderItemsPayload,
                ],
                'process' => false,
                'destination' => [
                    'contact' => $destination ?? [],
                    'inventory' => $orderItemsPayload,
                ],
                'serviceCode' => $selectedServiceCode,
            ];

            Log::info('dev_simulate_delyva_order', $payload);
            $result = $this->delyvaService->createOrder($payload);

            return [
                'ok' => true,
                'order_no' => $result['consignment_no'] ?? $result['order_no'] ?? $result['consignmentNumber'] ?? null,
                'tracking_no' => $result['tracking_no'] ?? $result['trackingNo'] ?? $result['consignment_no'] ?? null,
                'raw' => $result,
            ];
        } catch (\Exception $e) {
            Log::warning('[DEV_SIMULATE] Delyva order creation failed: ');
            Log::error(json_encode($e->getMessage(), JSON_PRETTY_PRINT));

            return [
                'ok' => false,
                'order_no' => null,
                'tracking_no' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function simulateProcessMembershipReferralSideEffects(Orders $order, $user): void
    {
        try {
            $orderItems = OrderItems::query()
                ->where('order_id', $order->order_id)
                ->get();

            foreach ($orderItems as $item) {
                if (empty($item->product_id)) {
                    continue;
                }
                $product = Products::query()->where('product_id', $item->product_id)->first();
                if (!$product) {
                    continue;
                }
                $membership = Memberships::query()
                    ->where('membership_code', $product->product_code)
                    ->first();
                if (!$membership) {
                    continue;
                }
                if (empty($user->referral_code)) {
                    $referralCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $user->last_name), 0, 3)
                        . substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $user->first_name), 0, 3)
                        . Str::random(4));
                    ReferralCodes::firstOrCreate(
                        ['user_id' => $user->user_id],
                        [
                            'campaign_name' => 'Default',
                            'referral_code' => $referralCode,
                            'code_effective_date' => now()->toDateString(),
                            'code_expiry_date' => now()->addYear()->toDateString(),
                            'usage_count' => 0,
                            'max_usage' => 0,
                            'is_active' => true,
                        ]
                    );
                    User::query()
                        ->where('user_id', $user->user_id)
                        ->update(['referral_code' => $referralCode]);
                }
                UserMemberships::query()
                    ->where('user_id', $user->user_id)
                    ->where('membership_status', 'active')
                    ->update([
                        'membership_status' => 'inactive',
                        'membership_end_date' => now()->subDay(1)->toDateString(),
                    ]);
                UserMemberships::create([
                    'user_id' => $user->user_id,
                    'membership_id' => $membership->membership_id,
                    'membership_status' => 'active',
                    'membership_start_date' => now()->toDateString(),
                    'membership_end_date' => now()->addYear()->subDay(1)->toDateString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[DEV_SIMULATE] Membership/referral side effect skipped: ' . $e->getMessage());
        }
    }

    private function resolveCartVoucherState(string $userId, Cart $cart, $items): array
    {
        $context = $this->extractCartVoucherContext($items);

        if (!($context['eligible'] ?? false)) {
            if ($cart->applied_user_voucher_id || $cart->applied_voucher_id || $cart->voucher_auto_apply_disabled) {
                $this->syncAppliedVoucher($cart, null, false);
            }

            return [
                'vendor_id' => null,
                'vendor_name' => null,
                'selection_mode' => 'none',
                'should_auto_apply' => false,
                'available' => [],
                'applied' => null,
            ];
        }

        $available = $this->buildAvailableCartVouchers($userId, $context);
        $availableByUserVoucherId = collect($available)->keyBy('user_voucher_id');
        $applied = null;

        if ($cart->applied_user_voucher_id) {
            $applied = $availableByUserVoucherId->get((string) $cart->applied_user_voucher_id);
        }

        if ($applied && !(bool) ($applied['is_eligible'] ?? false)) {
            $applied = null;
            $this->syncAppliedVoucher($cart, null, false);
        }

        if (!$applied && ($cart->applied_user_voucher_id || $cart->applied_voucher_id)) {
            $this->syncAppliedVoucher($cart, null, false);
        }

        $availableCount = count($available);
        $eligibleAutoApplyCount = collect($available)
            ->filter(fn($voucher) => !empty($voucher['user_voucher_id']) && (bool) ($voucher['is_eligible'] ?? false))
            ->count();

        return [
            'vendor_id' => $context['vendor_id'],
            'vendor_name' => $context['vendor_name'],
            'selection_mode' => $availableCount > 1 ? 'modal' : ($availableCount === 1 ? 'single' : 'none'),
            'should_auto_apply' => !$applied && !$cart->voucher_auto_apply_disabled && $eligibleAutoApplyCount === 1,
            'available' => array_values($available),
            'applied' => $applied ? array_merge($applied, [
                'is_auto_applied' => !$cart->voucher_auto_apply_disabled && $eligibleAutoApplyCount === 1,
            ]) : null,
        ];
    }

    private function extractCartVoucherContext($items): array
    {
        $productItems = collect($items)
            ->where('line_type', 'product')
            ->values();

        if ($productItems->isEmpty()) {
            return [
                'eligible' => false,
            ];
        }

        $productIds = $productItems
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $productRows = Products::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'vendor_id'])
            ->keyBy('product_id');

        $vendorIds = [];
        $vendorName = null;
        $productTotals = [];

        foreach ($productItems as $item) {
            $meta = (array) ($item->metadata_json ?? []);
            $sourceId = (string) $item->source_id;
            $fallbackVendorId = (string) ($productRows->get($sourceId)?->vendor_id ?? '');
            $vendorId = (string) ($meta['vendor_id'] ?? $fallbackVendorId);

            if ($vendorId === '') {
                return [
                    'eligible' => false,
                ];
            }

            $vendorIds[$vendorId] = true;
            if ($vendorName === null) {
                $name = trim((string) ($meta['vendor_name'] ?? ''));
                $vendorName = $name !== '' ? $name : null;
            }

            $productTotals[$sourceId] = round(
                (float) ($productTotals[$sourceId] ?? 0) + (float) $item->total_price,
                2,
            );
        }

        if (count($vendorIds) !== 1) {
            return [
                'eligible' => false,
            ];
        }

        return [
            'eligible' => true,
            'vendor_id' => (string) array_key_first($vendorIds),
            'vendor_name' => $vendorName,
            'product_ids' => $productIds,
            'product_totals' => $productTotals,
            'subtotal_amount' => round($productItems->sum(fn($item) => round(((float) $item->unit_price) * ((int) $item->quantity), 2)), 2),
            'total_product_amount' => round(array_sum($productTotals), 2),
        ];
    }

    private function buildAvailableCartVouchers(string $userId, array $context): array
    {
        $userVoucherSubquery = DB::table('user_vouchers')
            ->where('user_id', $userId)
            ->where('is_valid', true)
            ->selectRaw('MAX(user_voucher_id) as user_voucher_id, voucher_id')
            ->groupBy('voucher_id');

        $rows = DB::table('vouchers')
            ->leftJoinSub($userVoucherSubquery, 'user_vouchers', function ($join) {
                $join->on('user_vouchers.voucher_id', '=', 'vouchers.voucher_id');
            })
            ->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->where('vouchers.voucher_status', true)
            ->whereDate('vouchers.voucher_start_date', '<=', today())
            ->whereDate('vouchers.voucher_expiry_date', '>=', today())
            ->where('vouchers.vendor_id', $context['vendor_id'])
            ->select([
                'user_vouchers.user_voucher_id',
                'user_vouchers.voucher_id',
                'vouchers.vendor_id',
                'vouchers.voucher_name',
                'vouchers.voucher_code',
                'vouchers.voucher_value',
                'vouchers.voucher_short_description',
                'vouchers.voucher_expiry_date',
                'vouchers.voucher_discount',
                'vouchers.voucher_discount_type',
                'vouchers.voucher_limit',
                'vouchers.voucher_claim_per_user',
                'vouchers.voucher_claim_period',
                'vouchers.voucher_claim_per_period',
                'vouchers.is_unlimited',
                'vendors.vendor_name',
                "vouchers.min_purchase"
            ])
            ->orderBy('vouchers.voucher_expiry_date')
            ->orderBy('vouchers.created_at')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $voucherProducts = DB::table('voucher_products')
            ->whereIn('voucher_id', $rows->pluck('voucher_id')->all())
            ->get(['voucher_id', 'product_id'])
            ->groupBy('voucher_id');

        $available = [];
        foreach ($rows as $row) {
            if (!$this->canRedeemVoucherForCheckout($row, $userId)) {
                continue;
            }

            $linkedProductIds = $voucherProducts
                ->get((string) $row->voucher_id, collect())
                ->pluck('product_id')
                ->filter()
                ->map(fn($productId) => (string) $productId)
                ->unique()
                ->values()
                ->all();

            $eligibleProductIds = empty($linkedProductIds)
                ? array_values($context['product_ids'])
                : array_values(array_intersect($context['product_ids'], $linkedProductIds));

            if (empty($eligibleProductIds)) {
                continue;
            }

            $eligibleLineAmount = round(array_sum(array_map(
                fn($productId) => (float) ($context['product_totals'][$productId] ?? 0),
                $eligibleProductIds,
            )), 2);

            if ($eligibleLineAmount <= 0) {
                continue;
            }

            $discountAmount = $this->calculateVoucherDiscountAmount($row, $eligibleLineAmount);
            if ($discountAmount <= 0) {
                continue;
            }

            $minPurchase = $row->min_purchase !== null ? round((float) $row->min_purchase, 2) : null;
            $subtotalAmount = round((float) ($context['subtotal_amount'] ?? 0), 2);
            $isEligibleByMinPurchase = $minPurchase === null || $subtotalAmount >= $minPurchase;
            $disabledReason = $isEligibleByMinPurchase
                ? null
                : 'Minimum purchase of RM' . number_format($minPurchase, 2) . ' required.';

            $available[] = [
                'user_voucher_id' => $row->user_voucher_id ? (string) $row->user_voucher_id : null,
                'voucher_id' => (string) $row->voucher_id,
                'vendor_id' => (string) $row->vendor_id,
                'vendor_name' => $row->vendor_name ? (string) $row->vendor_name : ($context['vendor_name'] ?? null),
                'voucher_name' => (string) $row->voucher_name,
                'voucher_code' => $row->voucher_code ? (string) $row->voucher_code : null,
                'voucher_value' => $row->voucher_value ? (string) $row->voucher_value : null,
                'voucher_short_description' => $row->voucher_short_description ? (string) $row->voucher_short_description : null,
                'voucher_expiry_date' => $row->voucher_expiry_date ? (string) $row->voucher_expiry_date : null,
                'voucher_discount_type' => $row->voucher_discount_type ? (string) $row->voucher_discount_type : null,
                'scope' => empty($linkedProductIds) ? 'vendor' : 'product',
                'eligible_product_ids' => $eligibleProductIds,
                'eligible_line_amount' => $eligibleLineAmount,
                'discount_amount' => $discountAmount,
                'min_purchase' => $minPurchase,
                'is_claimed' => !empty($row->user_voucher_id),
                'is_eligible' => $isEligibleByMinPurchase,
                'disabled_reason' => $disabledReason,
            ];
        }

        return $available;
    }

    private function calculateVoucherDiscountAmount(object $voucher, float $eligibleLineAmount): float
    {
        $eligibleLineAmount = round(max(0, $eligibleLineAmount), 2);
        if ($eligibleLineAmount <= 0) {
            return 0.0;
        }

        $discountValue = (float) ($voucher->voucher_discount ?? 0);
        $discountType = strtoupper(trim((string) ($voucher->voucher_discount_type ?? '')));

        if ($discountValue <= 0 || !in_array($discountType, ['F', 'P'], true)) {
            return 0.0;
        }

        if ($discountType === 'P') {
            $discountAmount = $eligibleLineAmount * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }

        return round(min(max(0, $discountAmount), $eligibleLineAmount), 2);
    }

    private function canRedeemVoucherForCheckout(object $voucher, string $userId): bool
    {
        $claimLimit = max(1, (int) ($voucher->voucher_claim_per_user ?? 1));
        $userClaimCount = UserVoucherClaims::query()
            ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
            ->where('user_vouchers.voucher_id', $voucher->voucher_id)
            ->where('user_vouchers.user_id', $userId)
            ->count();

        if ($userClaimCount >= $claimLimit) {
            return false;
        }

        $period = (string) ($voucher->voucher_claim_period ?? '');
        $periodLimit = (int) ($voucher->voucher_claim_per_period ?? 0);
        if ($period !== '' && $periodLimit > 0) {
            $periodStart = null;
            if ($period === 'week') {
                $periodStart = now()->startOfWeek();
            } elseif ($period === 'month') {
                $periodStart = now()->startOfMonth();
            }

            if ($periodStart) {
                $periodRedeemCount = UserVoucherClaims::query()
                    ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
                    ->where('user_vouchers.voucher_id', $voucher->voucher_id)
                    ->where('user_vouchers.user_id', $userId)
                    ->where('user_vouchers.created_at', '>=', $periodStart)
                    ->count();

                if ($periodRedeemCount >= $periodLimit) {
                    return false;
                }
            }
        }

        if (!(bool) ($voucher->is_unlimited ?? false)) {
            $voucherLimit = (int) ($voucher->voucher_limit ?? 0);
            if ($voucherLimit > 0) {
                $totalVoucherRedeemCount = UserVoucherClaims::query()
                    ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
                    ->where('user_vouchers.voucher_id', $voucher->voucher_id)
                    ->count();

                if ($totalVoucherRedeemCount >= $voucherLimit) {
                    return false;
                }
            }
        }

        return true;
    }

    private function claimVoucherForCheckout(string $userId, array $voucher): array
    {
        $voucherId = (string) ($voucher['voucher_id'] ?? '');
        if ($voucherId === '') {
            return [
                'ok' => false,
                'message' => 'Selected voucher is not available for this cart.',
            ];
        }

        $existingVoucher = UserVouchers::query()
            ->where('user_id', $userId)
            ->where('voucher_id', $voucherId)
            ->where('is_valid', true)
            ->lockForUpdate()
            ->first();

        if ($existingVoucher) {
            return [
                'ok' => true,
                'user_voucher_id' => (string) $existingVoucher->user_voucher_id,
            ];
        }

        if (!$this->canRedeemVoucherForCheckout((object) $voucher, $userId)) {
            return [
                'ok' => false,
                'message' => 'Selected voucher is not available for this cart.',
            ];
        }

        $userVoucher = UserVouchers::query()->create([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'is_valid' => true,
        ]);

        return [
            'ok' => true,
            'user_voucher_id' => (string) $userVoucher->user_voucher_id,
        ];
    }

    private function syncAppliedVoucher(Cart $cart, ?array $voucher, bool $disableAutoApply): void
    {
        $nextUserVoucherId = $voucher ? (string) ($voucher['user_voucher_id'] ?? '') : null;
        $nextVoucherId = $voucher ? (string) ($voucher['voucher_id'] ?? '') : null;

        $nextValues = [
            'applied_user_voucher_id' => $nextUserVoucherId !== '' ? $nextUserVoucherId : null,
            'applied_voucher_id' => $nextVoucherId !== '' ? $nextVoucherId : null,
            'voucher_auto_apply_disabled' => $disableAutoApply,
        ];

        $shouldPersist = (string) ($cart->applied_user_voucher_id ?? '') !== (string) ($nextValues['applied_user_voucher_id'] ?? '')
            || (string) ($cart->applied_voucher_id ?? '') !== (string) ($nextValues['applied_voucher_id'] ?? '')
            || (bool) $cart->voucher_auto_apply_disabled !== (bool) $nextValues['voucher_auto_apply_disabled'];

        if ($shouldPersist) {
            $cart->update($nextValues);
        } else {
            $cart->forceFill($nextValues);
        }
    }

    private function getOrCreateActiveCart(string $userId): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->whereIn('cart_status', ['active', 'pending_payment'])
            ->orderByDesc('created_at')
            ->first();

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'cart_status' => 'active',
            'currency_code' => 'MYR',
            'expires_at' => null,
        ]);
    }

    private function fetchPickupStockContext(
        string $productId,
        int $vendorLocationId,
        string $compartmentStockId,
        string $compartmentStockProductId
    ): ?object {
        $now = now();

        return DB::table('compartment_stock_products as csp')
            ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
            ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
            ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
            ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
            ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'vl.vendor_id')
            ->where('csp.product_id', $productId)
            ->where('csp.compartment_stock_id', $compartmentStockId)
            ->where('csp.compartment_stock_product_id', $compartmentStockProductId)
            ->where('vl.id', $vendorLocationId)
            ->where('csp.quantity', '>', 0)
            ->where('cs.status', 'completed')
            ->where('tc.tender_status', 'paid')
            ->whereNotNull('tc.tender_start_date')
            ->whereNotNull('tc.tender_end_date')
            ->where('tc.tender_start_date', '<=', $now)
            ->where('tc.tender_end_date', '>=', $now)
            ->select([
                'csp.compartment_stock_product_id',
                'csp.compartment_stock_id',
                'csp.quantity as available_quantity',
                'c.compartment_id',
                'c.label as compartment_name',
                'r.rack_id',
                'r.rack_name',
                'vl.id as vendor_location_id',
                'vl.location_name as vendor_location_name',
                'v.vendor_id',
                'v.vendor_name',
            ])
            ->first();
    }

    private function resolveProductCartLine(Products $product, int $quantity): array
    {
        $pricing = $this->pricingService->resolvePricing($product, $quantity);
        $unitPrice = round((float) ($pricing['final_unit_price'] ?? 0), 2);
        $lineDiscount = round((float) ($pricing['discount_total'] ?? 0), 2);
        $lineSubtotal = round($unitPrice * $quantity, 2);

        $taxRate = 0.0;
        if ($product->is_taxable) {
            $taxRow = Taxes::query()->where('tax_rate_id', $product->tax_rate_id)->first();
            $taxRate = $taxRow ? (float) $taxRow->tax_rate : 0.0;
        }

        $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
        $lineTotal = round($lineSubtotal + $lineTax, 2);

        return [
            'unit_price' => $unitPrice,
            'discount' => $lineDiscount,
            'tax' => $lineTax,
            'total_price' => $lineTotal,
            'pricing' => $this->formatProductPricingDetails($pricing),
        ];
    }

    private function syncProductCartItemPricing($items): array
    {
        $productIds = collect($items)
            ->where('line_type', 'product')
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $products = Products::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $pricingDetails = [];

        foreach ($items as $item) {
            if ((string) $item->line_type !== 'product') {
                continue;
            }

            $product = $products->get((string) $item->source_id);
            if (!$product || !$product->is_active) {
                continue;
            }

            $linePricing = $this->resolveProductCartLine($product, (int) $item->quantity);
            $pricingDetails[(string) $item->cart_item_id] = $linePricing['pricing'];

            $nextValues = [
                'unit_price' => $linePricing['unit_price'],
                'discount' => $linePricing['discount'],
                'tax' => $linePricing['tax'],
                'total_price' => $linePricing['total_price'],
            ];

            $shouldPersist = round((float) $item->unit_price, 2) !== $nextValues['unit_price']
                || round((float) $item->discount, 2) !== $nextValues['discount']
                || round((float) $item->tax, 2) !== $nextValues['tax']
                || round((float) $item->total_price, 2) !== $nextValues['total_price'];

            if ($shouldPersist) {
                $item->update($nextValues);
            } else {
                $item->forceFill($nextValues);
            }
        }

        return $pricingDetails;
    }

    private function formatProductPricingDetails(array $pricing): array
    {
        $tier = $pricing['tier'] ?? null;
        $productDiscount = $pricing['product_discount'] ?? null;

        return [
            'pricing_mode' => (string) ($pricing['pricing_mode'] ?? 'base'),
            'base_unit_price' => round((float) ($pricing['base_unit_price'] ?? 0), 2),
            'final_unit_price' => round((float) ($pricing['final_unit_price'] ?? 0), 2),
            'unit_discount' => round((float) ($pricing['unit_discount'] ?? 0), 2),
            'discount_total' => round((float) ($pricing['discount_total'] ?? 0), 2),
            'tier' => $tier ? [
                'product_pricing_tier_id' => (string) ($tier->product_pricing_tier_id ?? ''),
                'pricing_mode' => (string) ($tier->pricing_mode ?? ''),
                'min_qty' => (int) ($tier->min_qty ?? 0),
                'unit_price' => isset($tier->unit_price) ? round((float) $tier->unit_price, 2) : null,
                'discount_percent' => isset($tier->discount_percent) ? round((float) $tier->discount_percent, 2) : null,
            ] : null,
            'product_discount' => $productDiscount ? [
                'product_discount_id' => (string) ($productDiscount->product_discount_id ?? ''),
                'discount_type' => (string) ($productDiscount->discount_type ?? ''),
                'discount_amount' => round((float) ($productDiscount->discount_amount ?? 0), 2),
                'discount_start_date' => $productDiscount->discount_start_date ? (string) $productDiscount->discount_start_date : null,
                'discount_end_date' => $productDiscount->discount_end_date ? (string) $productDiscount->discount_end_date : null,
            ] : null,
        ];
    }

    private function syncCartFulfillment(Cart $cart, ?string $method, ?int $vendorLocationId): void
    {
        $isDelivery = $method === 'delivery';

        $cart->update([
            'fulfillment_method' => $method,
            'fulfillment_vendor_location_id' => $vendorLocationId,
            'shipping_address_id' => $isDelivery ? $cart->shipping_address_id : null,
            'shipping_address_json' => $isDelivery ? $cart->shipping_address_json : null,
            'shipping_provider' => $isDelivery ? $cart->shipping_provider : null,
            'shipping_service_code' => $isDelivery ? $cart->shipping_service_code : null,
            'shipping_service_name' => $isDelivery ? $cart->shipping_service_name : null,
            'shipping_fee' => $isDelivery ? (float) ($cart->shipping_fee ?? 0) : 0,
            'shipping_quote_payload' => $isDelivery ? $cart->shipping_quote_payload : null,
        ]);
    }

    private function syncCartShippingSelection(Cart $cart, array $address, array $option): void
    {
        $cart->update([
            'shipping_address_id' => $address['user_address_id'] ?? null,
            'shipping_address_json' => $address,
            'shipping_provider' => $option['provider_name'] ?? null,
            'shipping_service_code' => $option['service_code'] ?? null,
            'shipping_service_name' => $option['service_name'] ?? null,
            'shipping_fee' => (float) ($option['amount'] ?? 0),
            'shipping_quote_payload' => $option['raw'] ?? $option,
        ]);
    }

    private function buildDeliveryCheckoutContext(Cart $cart, $items, string $userAddressId, string $userId): array
    {
        $productItems = collect($items)->where('line_type', 'product')->values();
        if ($productItems->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Your cart does not contain any deliverable products.',
            ];
        }

        $deliveryItems = $productItems->filter(function (CartItem $item) {
            $meta = (array) ($item->metadata_json ?? []);
            return (string) ($meta['fulfillment_type'] ?? 'pickup') === 'delivery';
        })->values();

        if ($deliveryItems->count() !== $productItems->count()) {
            return [
                'ok' => false,
                'message' => 'Your cart still contains pickup items. Remove them before delivery checkout.',
            ];
        }

        $vendorLocationId = (int) ($cart->fulfillment_vendor_location_id ?? 0);
        if ($vendorLocationId <= 0) {
            $vendorLocationId = (int) collect($deliveryItems)
                ->map(fn(CartItem $item) => (int) ((array) ($item->metadata_json ?? []))['vendor_location_id'])
                ->filter(fn($value) => $value > 0)
                ->first();
        }

        if ($vendorLocationId <= 0) {
            return [
                'ok' => false,
                'message' => 'Delivery branch is missing from cart items.',
            ];
        }

        $address = UserAddresses::query()
            ->leftJoin('users', 'user_addresses.user_id', '=', 'users.user_id')
            ->where('user_addresses.user_id', $userId)
            ->where('user_address_id', $userAddressId)
            ->first();

        if (!$address) {
            return [
                'ok' => false,
                'message' => 'Delivery address not found.',
            ];
        }

        $formattedAddress = $this->formatUserAddress($address);
        $origin = DB::table('vendor_locations')
            ->leftJoin('vendors', 'vendor_locations.vendor_id', '=', 'vendors.vendor_id')
            ->where('id', $vendorLocationId)
            ->first([
                'id',
                'vendor_locations.vendor_id',
                'vendors.first_name',
                'vendors.last_name',
                'location_name',
                'vendor_locations.contact_no',
                'address',
                'latitude',
                'longitude',
            ]);

        if (!$origin) {
            return [
                'ok' => false,
                'message' => 'Delivery branch not found.',
            ];
        }

        $products = Products::query()
            ->whereIn('product_id', $deliveryItems->pluck('source_id')->all())
            ->get()
            ->keyBy('product_id');

        $weightKg = 0.0;
        foreach ($deliveryItems as $item) {
            $product = $products->get((string) $item->source_id);
            $unitWeight = (float) ($product?->product_weight ?? 0.1);
            $weightKg += max($unitWeight, 0.1) * max(1, (int) $item->quantity);
        }

        return [
            'ok' => true,
            'address' => $formattedAddress,
            'origin' => $this->toDelyvaAddress([
                'name' => (string) ($origin->first_name . ' ' . $origin->last_name ?? ''),
                'contact_no' => (string) ($origin->contact_no ?? ''),
                'line1' => (string) ($origin->address ?? $origin->location_name ?? ''),
                'line2' => null,
                'postcode' => $this->extractPostcode((string) ($origin->address ?? '')),
                'city' => $this->extractCityState((string) ($origin->address ?? ''), (string) ($origin->location_name ?? ''))[0] ?? '',
                'state' => $this->extractCityState((string) ($origin->address ?? ''), (string) ($origin->location_name ?? ''))[1] ?? '',
                'country' => 'MY',
            ]),
            'destination' => $this->toDelyvaAddress($formattedAddress),
            'origin_summary' => [
                'vendor_location_id' => (int) $origin->id,
                'vendor_id' => (string) ($origin->vendor_id ?? ''),
                'location_name' => (string) ($origin->location_name ?? ''),
                'address' => (string) ($origin->address ?? ''),
            ],
            'weight_kg' => round(max($weightKg, 0.1), 2),
        ];
    }

    private function formatUserAddress(UserAddresses $address): array
    {
        $payload = is_array($address->address) ? $address->address : [];

        return [
            'user_address_id' => (string) $address->user_address_id,
            'label' => (string) ($payload['label'] ?? ''),
            'name' => (string) ($address->first_name . ' ' . $address->last_name ?? ''),
            'contact_no' => (string) ($payload['contact_no'] ?? ''),
            'line1' => (string) ($payload['line1'] ?? ''),
            'line2' => isset($payload['line2']) ? (string) $payload['line2'] : null,
            'postcode' => (string) ($payload['postcode'] ?? ''),
            'city' => (string) ($payload['city'] ?? ''),
            'state' => (string) ($payload['state'] ?? ''),
            'country' => (string) ($payload['country'] ?? 'Malaysia'),
        ];
    }

    private function toDelyvaAddress(array $address): array
    {
        return [
            'name' => (string) ($address['name'] ?? ''),
            'mobile' => (string) ($address['contact_no'] ?? ''),
            'phone' => (string) ($address['contact_no'] ?? ''),
            'address1' => (string) ($address['line1'] ?? ''),
            'address2' => $address['line2'] ?? '',
            'city' => (string) ($address['city'] ?? ''),
            'state' => (string) ($address['state'] ?? ''),
            'postcode' => (string) ($address['postcode'] ?? ''),
            'country' => strtoupper((string) (($address['country'] ?? 'MY') === 'Malaysia' ? 'MY' : ($address['country'] ?? 'MY'))),
        ];
    }

    private function extractPostcode(string $address): string
    {
        if (preg_match('/\b(\d{5})\b/', $address, $matches) === 1) {
            return (string) $matches[1];
        }

        return '00000';
    }

    private function extractCityState(string $address, string $fallbackCity): array
    {
        $address = trim($address);
        if ($address === '') {
            $fallbackCity = trim($fallbackCity);
            return [$fallbackCity !== '' ? $fallbackCity : null, null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn($p) => $p !== ''));
        if (count($parts) === 1) {
            return [$parts[0], null];
        }

        $state = $parts[count($parts) - 2] ?? null;
        $city = $parts[count($parts) - 4] ?? null;
        return [$city, $state];
    }

    private function sumCartTotals($items, float $voucherDiscount = 0.0, float $shippingFee = 0.0): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $productDiscountTotal = 0.0;
        $grossTotalPayment = 0.0;

        foreach ($items as $item) {
            $subtotal += round(((float) $item->unit_price) * ((int) $item->quantity), 2);
            $taxTotal += (float) $item->tax;
            $productDiscountTotal += (float) $item->discount;
            $grossTotalPayment += (float) $item->total_price;
        }

        $voucherDiscount = round(max(0, $voucherDiscount), 2);
        $voucherDiscount = min($voucherDiscount, round(max(0, $grossTotalPayment), 2));
        $shippingFee = round(max(0, $shippingFee), 2);
        $totalDiscount = round($productDiscountTotal + $voucherDiscount, 2);
        $totalPayment = round(max(0, $grossTotalPayment - $voucherDiscount) + $shippingFee, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'product_discount_total' => round($productDiscountTotal, 2),
            'voucher_discount_total' => $voucherDiscount,
            'shipping_fee' => $shippingFee,
            'total_discount' => $totalDiscount,
            'total_payment' => $totalPayment,
        ];
    }

    private function generateOrderNo(): string
    {
        return date('ymd') . '-' . strtoupper(Str::random(6));
    }

    private function buildOrderDescription($items, $products, $events): string
    {
        $eventItems = $items->where('line_type', 'event')->values();
        $productItems = $items->where('line_type', 'product')->values();

        if ($eventItems->count() === 1 && $productItems->count() === 0) {
            $eventItem = $eventItems->first();
            $event = $events[$eventItem->source_id] ?? null;
            if ($event) {
                $dateLabel = '';
                $timeLabel = '';

                if (!empty($event->event_start_date)) {
                    try {
                        $dateLabel = Carbon::parse($event->event_start_date)->format('d M Y');
                    } catch (\Throwable $e) {
                        $dateLabel = '';
                    }
                }

                if (!empty($event->event_start_time)) {
                    try {
                        $start = Carbon::parse((string) $event->event_start_time)->format('g:i A');
                        $end = !empty($event->event_end_time)
                            ? Carbon::parse((string) $event->event_end_time)->format('g:i A')
                            : null;
                        $timeLabel = $end ? $start . ' - ' . $end : $start;
                    } catch (\Throwable $e) {
                        $timeLabel = '';
                    }
                }

                $parts = array_filter([
                    trim((string) $event->event_name) . ' x' . max(1, (int) ($eventItem->quantity ?? 1)),
                    $dateLabel,
                    $timeLabel,
                ]);

                return Str::limit('Event Registration: ' . implode(' | ', $parts), 255, '');
            }
        }

        if ($productItems->count() > 0 && $eventItems->count() === 0) {
            $firstItem = $productItems->first();
            $firstName = trim((string) ($products[$firstItem->source_id]?->product_name ?? 'Product'));
            $firstQty = (int) ($firstItem->quantity ?? 1);
            $remaining = $productItems->count() - 1;

            $description = $remaining > 0
                ? "Product Order: {$firstName} x{$firstQty} + {$remaining} more item(s)"
                : "Product Order: {$firstName} x{$firstQty}";

            return Str::limit($description, 255, '');
        }

        if ($eventItems->count() > 0 && $productItems->count() > 0) {
            $eventItem = $eventItems->first();
            $eventName = trim((string) ($events[$eventItem->source_id]?->event_name ?? 'Event'));
            $extraCount = ($eventItems->count() + $productItems->count()) - 1;
            $description = $extraCount > 0
                ? "Mixed Order: {$eventName} + {$extraCount} more item(s)"
                : "Mixed Order: {$eventName}";

            return Str::limit($description, 255, '');
        }

        return 'BonBon Order';
    }

    private function getUserMembershipType(string $userId): ?string
    {
        $type = UserMemberships::query()
            ->where('user_id', $userId)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->value('memberships.membership_type');

        return $type ? (string) $type : null;
    }

    private function getMembershipTypeIdByName(?string $membershipType): ?string
    {
        if (!$membershipType) {
            return null;
        }

        $id = MembershipTypes::query()
            ->whereRaw('LOWER(membership_type) = ?', [strtolower($membershipType)])
            ->value('membership_type_id');

        return $id ? (string) $id : null;
    }

    private function resolveEventPricing(Events $event, ?string $membershipTypeId): array
    {
        $basePrice = (float) ($event->registration_type === 'paid' ? $event->base_price : 0);
        if ($basePrice < 0) {
            $basePrice = 0;
        }

        $rule = null;
        if ($event->registration_type === 'paid' && $membershipTypeId) {
            $rule = EventPricingRule::query()
                ->where('event_id', $event->event_id)
                ->where('membership_type_id', $membershipTypeId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $now = now();
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) {
                    $now = now();
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->orderByDesc('created_at')
                ->first();
        }

        $discount = 0.0;
        $final = $basePrice;

        if ($rule) {
            $value = (float) $rule->pricing_value;
            if ((string) $rule->pricing_rule_type === 'discount_percent') {
                $discount = round($basePrice * ($value / 100), 2);
                $final = $basePrice - $discount;
            } elseif ((string) $rule->pricing_rule_type === 'discount_fixed') {
                $discount = min($basePrice, round($value, 2));
                $final = $basePrice - $discount;
            } elseif ((string) $rule->pricing_rule_type === 'final_price') {
                $final = round($value, 2);
                $discount = max(0, round($basePrice - $final, 2));
            }
        }

        if ($final < 0) {
            $final = 0;
        }

        return [
            'price_before_discount' => round($basePrice, 2),
            'discount_amount' => round($discount, 2),
            'unit_price' => round($final, 2),
            'total_price' => round($final, 2),
        ];
    }

    private function assertEventRsvpWindow(Events $event): void
    {
        $now = now();
        if ($event->rsvp_open_at && $now->lessThan($event->rsvp_open_at)) {
            abort(422, 'RSVP is not open yet.');
        }
        if ($event->rsvp_close_at && $now->greaterThan($event->rsvp_close_at)) {
            abort(422, 'RSVP is closed.');
        }
    }

    private function assertSeatAvailableAndHold(Events $event, string $userId, ?string $existingCartItemId = null): void
    {
        if ($event->is_unlimited_seats) {
            return;
        }

        $seatLimit = $event->seat_limit ? (int) $event->seat_limit : 0;
        if ($seatLimit <= 0) {
            abort(422, 'Seat limit is not configured for this event.');
        }

        $existing = EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->where('user_id', $userId)
            ->whereIn('registration_status', ['draft', 'pending_payment', 'confirmed'])
            ->exists();

        if ($existing) {
            return;
        }

        $now = now();
        $heldCount = (int) EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->whereIn('registration_status', ['draft', 'pending_payment'])
            ->whereNotNull('seat_hold_expires_at')
            ->where('seat_hold_expires_at', '>', $now)
            ->when($existingCartItemId, function ($q) use ($existingCartItemId) {
                $q->where('cart_item_id', '!=', $existingCartItemId);
            })
            ->count();

        $confirmedCount = (int) EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->where('registration_status', 'confirmed')
            ->count();

        $used = $heldCount + $confirmedCount;
        if ($used >= $seatLimit) {
            abort(422, 'No seats available for this event.');
        }
    }
}
