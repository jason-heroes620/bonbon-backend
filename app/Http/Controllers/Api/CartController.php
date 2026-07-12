<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EventPricingRule;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAnswer;
use App\Models\Events;
use App\Models\MembershipTypes;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\Products;
use App\Models\Taxes;
use App\Models\UserMemberships;
use App\Services\ProductPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    protected ProductPricingService $pricingService;

    public function __construct(ProductPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
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
                $subtitleParts = array_values(array_filter([
                    $row?->vendor_name ? (string) $row->vendor_name : null,
                    !empty($meta['pickup_location_name']) ? (string) $meta['pickup_location_name'] : null,
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

        $totals = $this->sumCartTotals($items);

        return response()->json([
            'data' => [
                'cart' => $cart,
                'items' => $enrichedItems,
                'totals' => $totals,
            ],
        ]);
    }

    public function upsertItem(Request $request)
    {
        $validated = $request->validate([
            'line_type' => ['required', 'in:product,event'],
            'source_id' => ['required', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'uom' => ['nullable', 'string', 'max:50'],
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
                $vendorLocationId = isset($validated['vendor_location_id']) ? (int) $validated['vendor_location_id'] : null;
                $compartmentStockId = isset($validated['compartment_stock_id']) ? (string) $validated['compartment_stock_id'] : null;
                $compartmentStockProductId = isset($validated['compartment_stock_product_id']) ? (string) $validated['compartment_stock_product_id'] : null;

                if (!$vendorLocationId || !$compartmentStockId || !$compartmentStockProductId) {
                    return response()->json([
                        'message' => 'Pickup location is required for product purchases.',
                    ], 422);
                }

                $otherPickupLocationId = CartItem::query()
                    ->where('cart_id', $cart->cart_id)
                    ->where('line_type', 'product')
                    ->when($cartItem, fn($q) => $q->where('cart_item_id', '!=', $cartItem->cart_item_id))
                    ->pluck('metadata_json')
                    ->map(function ($meta) {
                        $value = is_array($meta) ? ($meta['vendor_location_id'] ?? null) : null;
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
            $totals = $this->sumCartTotals($items);

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

        DB::transaction(function () use ($item) {
            if ($item->line_type === 'event') {
                EventRegistration::query()
                    ->where('cart_item_id', $item->cart_item_id)
                    ->whereIn('registration_status', ['draft', 'pending_payment'])
                    ->update([
                        'registration_status' => 'cancelled',
                    ]);
            }

            $item->delete();
        });

        return response()->json([
            'ok' => true,
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

        foreach ($items as $item) {
            if ((string) $item->line_type === 'product') {
                $meta = (array) ($item->metadata_json ?? []);
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

        $totals = $this->sumCartTotals($items);

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

        DB::transaction(function () use ($cart, $items, $totals, $refNo, $userId, $orderDescription, $products, $events) {
            $order = Orders::create([
                'user_id' => $userId,
                'order_no' => $refNo,
                'order_date' => now()->toDateString(),
                'order_description' => $orderDescription,
                'total_price' => (float) $totals['subtotal'],
                'total_charges' => 0,
                'total_discount' => (float) $totals['total_discount'],
                'total_payment' => (float) $totals['total_payment'],
                'shipping_method' => 'pickup',
                'shipping_address' => null,
                'billing_address' => null,
                'discount_code' => null,
                'wallet_credit_used' => 0,
                'order_status' => 'pending',
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

    private function sumCartTotals($items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $discountTotal = 0.0;
        $totalPayment = 0.0;

        foreach ($items as $item) {
            $subtotal += round(((float) $item->unit_price) * ((int) $item->quantity), 2);
            $taxTotal += (float) $item->tax;
            $discountTotal += (float) $item->discount;
            $totalPayment += (float) $item->total_price;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total_discount' => round($discountTotal, 2),
            'total_payment' => round($totalPayment, 2),
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
