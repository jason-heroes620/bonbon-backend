<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompartmentStockProductTransaction;
use App\Models\CompartmentStockQrSession;
use App\Models\CompartmentStocks;
use App\Models\Vendors;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompartmentStocksController extends Controller
{
    public function storeQrSession(Request $request, string $vendor_id, string $compartment_stock_product_id)
    {
        $validated = $request->validate([
            'ttl_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
        ]);

        $currentVendorId = $this->currentVendorId($request);
        $isAdmin = $this->isAdmin($request);

        if (!$isAdmin && (!$currentVendorId || (string) $currentVendorId !== (string) $vendor_id)) {
            return response()->json([
                'message' => 'You are not allowed to generate a QR session for this vendor.',
            ], 403);
        }

        $context = $this->fetchStockContextByProductId($compartment_stock_product_id);

        if (!$context || (string) $context->merchant_vendor_id !== (string) $vendor_id) {
            return response()->json([
                'message' => 'Prepared compartment stock item not found.',
            ], 404);
        }

        if ((string) $context->stock_status !== 'prepared') {
            return response()->json([
                'message' => 'Only prepared stock items can generate a QR code.',
            ], 422);
        }

        if ((string) $context->merchant_vendor_id === (string) $context->rack_owner_vendor_id) {
            return response()->json([
                'message' => 'Merchant and rack owner must be different vendors for QR handoff.',
            ], 422);
        }

        $issuedAt = Carbon::now('UTC');
        $expiresAt = $issuedAt->copy()->addSeconds((int) ($validated['ttl_seconds'] ?? 600));
        $nonce = (string) Str::uuid();

        $payload = [
            'compartment_stock_id' => (string) $context->compartment_stock_id,
            'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
            'vendor_id' => (string) $context->merchant_vendor_id,
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'nonce' => $nonce,
        ];

        $signature = $this->signPayload($payload);
        $payload['signature'] = $signature;

        CompartmentStockQrSession::query()
            ->where('compartment_stock_product_id', $compartment_stock_product_id)
            ->where('vendor_id', $vendor_id)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'updated_at' => $issuedAt,
            ]);

        $session = CompartmentStockQrSession::query()->create([
            'compartment_stock_qr_session_id' => (string) Str::uuid(),
            'compartment_stock_id' => (string) $context->compartment_stock_id,
            'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
            'vendor_id' => (string) $context->merchant_vendor_id,
            'rack_owner_vendor_id' => (string) $context->rack_owner_vendor_id,
            'generated_by_user_id' => (string) $request->user()->user_id,
            'nonce' => $nonce,
            'payload_json' => $payload,
            'signature_hash' => hash('sha256', $signature),
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        return response()->json([
            'data' => $this->formatSessionData($session, $context),
        ], 201);
    }

    public function showQrSession(Request $request, string $compartment_stock_qr_session_id)
    {
        $session = $this->fetchSessionContextById($compartment_stock_qr_session_id);
        if (!$session) {
            return response()->json([
                'message' => 'QR session not found.',
            ], 404);
        }

        $session = $this->synchronizeSessionStatus($session);

        $currentVendorId = $this->currentVendorId($request);
        $isAdmin = $this->isAdmin($request);
        $isAllowedVendor = $currentVendorId && in_array(
            (string) $currentVendorId,
            [(string) $session->vendor_id, (string) $session->rack_owner_vendor_id],
            true
        );

        if (!$isAdmin && !$isAllowedVendor) {
            return response()->json([
                'message' => 'You are not allowed to view this QR session.',
            ], 403);
        }

        return response()->json([
            'data' => $this->formatSessionData($session),
        ]);
    }

    public function revokeQrSession(Request $request, string $compartment_stock_qr_session_id)
    {
        $session = $this->fetchSessionContextById($compartment_stock_qr_session_id);
        if (!$session) {
            return response()->json([
                'message' => 'QR session not found.',
            ], 404);
        }

        $currentVendorId = $this->currentVendorId($request);
        $isAdmin = $this->isAdmin($request);

        if (!$isAdmin && (string) $currentVendorId !== (string) $session->vendor_id) {
            return response()->json([
                'message' => 'You are not allowed to revoke this QR session.',
            ], 403);
        }

        $session = $this->synchronizeSessionStatus($session);

        if ((string) $session->status === 'active') {
            CompartmentStockQrSession::query()
                ->whereKey($compartment_stock_qr_session_id)
                ->update([
                    'status' => 'revoked',
                    'updated_at' => Carbon::now('UTC'),
                ]);

            $session = $this->fetchSessionContextById($compartment_stock_qr_session_id);
        }

        return response()->json([
            'data' => $this->formatSessionData($session),
        ]);
    }

    public function validateQr(Request $request)
    {
        $validated = $request->validate([
            'payload' => ['nullable'],
            'compartment_stock_qr_session_id' => ['nullable', 'uuid'],
        ]);

        if (empty($validated['payload']) && empty($validated['compartment_stock_qr_session_id'])) {
            return response()->json([
                'message' => 'QR payload or session ID is required.',
            ], 422);
        }

        $isAdmin = $this->isAdmin($request);
        $currentVendorId = $this->currentVendorId($request);

        if (!$isAdmin && !$currentVendorId) {
            return response()->json([
                'message' => 'Vendor access is required.',
            ], 403);
        }

        $session = null;
        if (!empty($validated['compartment_stock_qr_session_id'])) {
            $session = $this->fetchSessionContextById((string) $validated['compartment_stock_qr_session_id']);
        } else {
            $payload = $this->normalizePayloadInput($validated['payload']);
            if (!$payload) {
                return response()->json([
                    'message' => 'Invalid QR code payload.',
                ], 422);
            }

            $providedSignature = trim((string) ($payload['signature'] ?? ''));
            if ($providedSignature === '') {
                return response()->json([
                    'message' => 'QR code signature is missing.',
                ], 422);
            }

            $requiredFields = [
                'compartment_stock_id',
                'compartment_stock_product_id',
                'vendor_id',
                'issued_at',
                'expires_at',
                'nonce',
            ];

            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $payload) || trim((string) ($payload[$field] ?? '')) === '') {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $isPickupPayload = array_key_exists('order_pickup_id', $payload) || array_key_exists('pickup_code', $payload);
                return response()->json([
                    'message' => $isPickupPayload
                        ? 'This QR payload appears to be for purchase pickup, not stock handoff.'
                        : 'Invalid stock handoff QR payload.',
                    'missing_fields' => $missingFields,
                ], 422);
            }

            $expectedSignature = $this->signPayload($payload);
            if (!hash_equals($expectedSignature, $providedSignature)) {
                Log::warning('Invalid QR code signature.', [
                    'compartment_stock_id' => (string) ($payload['compartment_stock_id'] ?? ''),
                    'compartment_stock_product_id' => (string) ($payload['compartment_stock_product_id'] ?? ''),
                    'vendor_id' => (string) ($payload['vendor_id'] ?? ''),
                    'nonce' => (string) ($payload['nonce'] ?? ''),
                ]);
                return response()->json([
                    'message' => 'QR code signature is invalid.',
                ], 422);
            }

            $sessionId = DB::table('compartment_stock_qr_sessions')
                ->where('nonce', (string) ($payload['nonce'] ?? ''))
                ->where('compartment_stock_id', (string) ($payload['compartment_stock_id'] ?? ''))
                ->where('compartment_stock_product_id', (string) ($payload['compartment_stock_product_id'] ?? ''))
                ->where('vendor_id', (string) ($payload['vendor_id'] ?? ''))
                ->value('compartment_stock_qr_session_id');

            if ($sessionId) {
                $session = $this->fetchSessionContextById((string) $sessionId);
            }

            if (!$session || !hash_equals((string) $session->signature_hash, hash('sha256', $providedSignature))) {
                return response()->json([
                    'message' => 'QR code session could not be verified.',
                ], 422);
            }
        }

        if (!$session) {
            return response()->json([
                'message' => 'QR session not found.',
            ], 404);
        }

        $session = $this->synchronizeSessionStatus($session);

        if ((string) $session->status === 'expired') {
            return response()->json([
                'message' => 'This QR code has expired.',
                'data' => $this->formatValidationData($session),
            ], 422);
        }

        if ((string) $session->status === 'consumed') {
            return response()->json([
                'message' => 'This stock handoff has already been confirmed.',
                'data' => $this->formatValidationData($session),
            ], 409);
        }

        if ((string) $session->status === 'revoked') {
            return response()->json([
                'message' => 'This QR code has been revoked.',
            ], 410);
        }

        if ((string) $session->stock_status !== 'prepared') {
            return response()->json([
                'message' => 'This stock item is no longer in prepared status.',
                'data' => $this->formatValidationData($session),
            ], 409);
        }

        if (!$isAdmin && (string) $currentVendorId !== (string) $session->rack_owner_vendor_id) {
            return response()->json([
                'message' => 'You are not the rack owner for this QR handoff.',
            ], 403);
        }

        if (!$session->scanned_at) {
            CompartmentStockQrSession::query()
                ->whereKey($session->compartment_stock_qr_session_id)
                ->update([
                    'scanned_at' => Carbon::now('UTC'),
                    'scanned_by_user_id' => $request->user()->user_id,
                    'updated_at' => Carbon::now('UTC'),
                ]);

            $session = $this->fetchSessionContextById((string) $session->compartment_stock_qr_session_id);
        }

        return response()->json([
            'data' => $this->formatValidationData($session),
        ]);
    }

    public function confirmReceive(Request $request)
    {
        $validated = $request->validate([
            'compartment_stock_qr_session_id' => ['required', 'uuid'],
        ]);

        $isAdmin = $this->isAdmin($request);
        $currentVendorId = $this->currentVendorId($request);

        if (!$isAdmin && !$currentVendorId) {
            return response()->json([
                'message' => 'Vendor access is required.',
            ], 403);
        }

        $result = DB::transaction(function () use ($validated, $request, $currentVendorId, $isAdmin) {
            $session = CompartmentStockQrSession::query()
                ->whereKey($validated['compartment_stock_qr_session_id'])
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return [
                    'type' => 'error',
                    'status' => 404,
                    'message' => 'QR session not found.',
                ];
            }

            $now = Carbon::now('UTC');

            if ((string) $session->status === 'active' && $session->expires_at && Carbon::parse($session->expires_at, 'UTC')->isPast()) {
                $session->update([
                    'status' => 'expired',
                    'updated_at' => $now,
                ]);

                return [
                    'type' => 'error',
                    'status' => 422,
                    'message' => 'This QR code has expired.',
                ];
            }

            if (!$isAdmin && (string) $currentVendorId !== (string) $session->rack_owner_vendor_id) {
                return [
                    'type' => 'error',
                    'status' => 403,
                    'message' => 'You are not the rack owner for this handoff.',
                ];
            }

            $stock = CompartmentStocks::query()
                ->whereKey($session->compartment_stock_id)
                ->lockForUpdate()
                ->first();

            $product = DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', $session->compartment_stock_product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || !$product) {
                return [
                    'type' => 'error',
                    'status' => 404,
                    'message' => 'Compartment stock details not found.',
                ];
            }

            $context = $this->fetchSessionContextById((string) $session->compartment_stock_qr_session_id);

            if ((string) $session->status === 'consumed' || (string) $stock->status === 'completed') {
                $existing = CompartmentStockProductTransaction::query()
                    ->where('compartment_stock_qr_session_id', $session->compartment_stock_qr_session_id)
                    ->first();

                return [
                    'type' => 'duplicate',
                    'status' => 409,
                    'message' => 'This stock handoff has already been confirmed.',
                    'data' => $this->formatConfirmResult($existing, $context),
                ];
            }

            if ((string) $session->status !== 'active') {
                return [
                    'type' => 'error',
                    'status' => 409,
                    'message' => 'This QR session is no longer active.',
                ];
            }

            if ((string) $stock->status !== 'prepared') {
                return [
                    'type' => 'error',
                    'status' => 409,
                    'message' => 'This stock item is no longer in prepared status.',
                ];
            }

            $stock->update([
                'status' => 'completed',
                'confirmed_received_at' => $now,
                'confirmed_received_by_user_id' => $request->user()->user_id,
                'confirmation_source' => 'merchant_qr',
                'updated_at' => $now,
            ]);

            $session->update([
                'status' => 'consumed',
                'consumed_at' => $now,
                'consumed_by_user_id' => $request->user()->user_id,
                'scanned_at' => $session->scanned_at ?? $now,
                'scanned_by_user_id' => $session->scanned_by_user_id ?? $request->user()->user_id,
                'updated_at' => $now,
            ]);

            $transaction = CompartmentStockProductTransaction::query()->create([
                'compartment_stock_product_transaction_id' => (string) Str::uuid(),
                'transaction_quantity' => (int) $product->quantity,
                'compartment_stock_qr_session_id' => (string) $session->compartment_stock_qr_session_id,
                'compartment_stock_id' => (string) $session->compartment_stock_id,
                'compartment_stock_product_id' => (string) $session->compartment_stock_product_id,
                'vendor_id' => (string) $session->vendor_id,
                'rack_owner_vendor_id' => (string) $session->rack_owner_vendor_id,
                'generated_by_user_id' => (string) $session->generated_by_user_id,
                'received_by_user_id' => (string) $request->user()->user_id,
                'transaction_type' => 'stock_receive',
                'transaction_status' => 'confirmed',
                'prepared_quantity' => (int) $product->quantity,
                'received_quantity' => (int) $product->quantity,
                'quantity_delta' => (int) $product->quantity,
                'actor_user_id' => (string) $request->user()->user_id,
                'actor_vendor_id' => (string) $session->rack_owner_vendor_id,
                'event_source' => 'qr_receive',
                'event_source_id' => (string) $session->compartment_stock_qr_session_id,
                'vendor_location_id' => $context && isset($context->vendor_location_id)
                    ? (int) $context->vendor_location_id
                    : null,
                'rack_id' => $context && isset($context->rack_id)
                    ? (string) $context->rack_id
                    : null,
                'compartment_id' => $context && isset($context->compartment_id)
                    ? (string) $context->compartment_id
                    : null,
                'product_id' => $context && isset($context->product_id)
                    ? (string) $context->product_id
                    : null,
                'description' => $context
                    ? sprintf(
                        'Receipt confirmed for %s in %s / %s / %s.',
                        (string) $context->product_name,
                        (string) $context->vendor_location_name,
                        (string) $context->rack_name,
                        (string) $context->compartment_name
                    )
                    : 'Receipt confirmed.',
                'confirmed_at' => $now,
            ]);

            return [
                'type' => 'success',
                'status' => 200,
                'message' => 'Receipt confirmed successfully.',
                'data' => $this->formatConfirmResult($transaction, $context),
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    public function removeStockProduct(Request $request, string $vendor_id, string $compartment_stock_product_id)
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $currentVendorId = $this->currentVendorId($request);
        $isAdmin = $this->isAdmin($request);

        if (!$isAdmin && (!$currentVendorId || (string) $currentVendorId !== (string) $vendor_id)) {
            return response()->json([
                'message' => 'You are not allowed to remove stock for this vendor.',
            ], 403);
        }

        $context = $this->fetchStockContextByProductId($compartment_stock_product_id);
        if (!$context || (string) $context->merchant_vendor_id !== (string) $vendor_id) {
            return response()->json([
                'message' => 'Compartment stock product not found for this vendor.',
            ], 404);
        }

        $now = Carbon::now('UTC');

        $result = DB::transaction(function () use ($validated, $request, $context, $now) {
            $stock = CompartmentStocks::query()
                ->whereKey((string) $context->compartment_stock_id)
                ->lockForUpdate()
                ->first();

            $product = DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', (string) $context->compartment_stock_product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || !$product) {
                return [
                    'status' => 404,
                    'message' => 'Compartment stock details not found.',
                ];
            }

            $currentQty = (int) $product->quantity;
            $removeQty = isset($validated['quantity']) ? (int) $validated['quantity'] : $currentQty;

            if ($currentQty <= 0) {
                return [
                    'status' => 409,
                    'message' => 'No remaining stock quantity to remove.',
                ];
            }

            if ($removeQty <= 0 || $removeQty > $currentQty) {
                return [
                    'status' => 422,
                    'message' => 'Invalid remove quantity.',
                ];
            }

            $nextQty = $currentQty - $removeQty;

            DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', (string) $context->compartment_stock_product_id)
                ->update([
                    'quantity' => $nextQty,
                    'updated_at' => $now,
                ]);

            if ($nextQty === 0) {
                $stock->update([
                    'status' => 'remove',
                    'updated_at' => $now,
                ]);
            }

            $transaction = CompartmentStockProductTransaction::query()->create([
                'compartment_stock_product_transaction_id' => (string) Str::uuid(),
                'transaction_quantity' => $removeQty,
                'compartment_stock_qr_session_id' => null,
                'compartment_stock_id' => (string) $context->compartment_stock_id,
                'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
                'vendor_id' => (string) $context->merchant_vendor_id,
                'rack_owner_vendor_id' => (string) $context->rack_owner_vendor_id,
                'generated_by_user_id' => null,
                'received_by_user_id' => null,
                'transaction_type' => 'stock_remove',
                'transaction_status' => 'confirmed',
                'prepared_quantity' => null,
                'received_quantity' => null,
                'quantity_delta' => -$removeQty,
                'actor_user_id' => (string) $request->user()->user_id,
                'actor_vendor_id' => (string) $context->merchant_vendor_id,
                'event_source' => 'merchant_remove',
                'event_source_id' => (string) Str::uuid(),
                'vendor_location_id' => isset($context->vendor_location_id) ? (int) $context->vendor_location_id : null,
                'rack_id' => isset($context->rack_id) ? (string) $context->rack_id : null,
                'compartment_id' => isset($context->compartment_id) ? (string) $context->compartment_id : null,
                'product_id' => isset($context->product_id) ? (string) $context->product_id : null,
                'description' => !empty($validated['description'])
                    ? (string) $validated['description']
                    : sprintf(
                        'Stock removed: %s from %s / %s / %s.',
                        (string) $context->product_name,
                        (string) $context->vendor_location_name,
                        (string) $context->rack_name,
                        (string) $context->compartment_name
                    ),
                'confirmed_at' => $now,
            ]);

            return [
                'status' => 200,
                'message' => 'Stock removed successfully.',
                'data' => [
                    'transaction_id' => (string) $transaction->compartment_stock_product_transaction_id,
                    'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
                    'quantity_before' => $currentQty,
                    'quantity_after' => $nextQty,
                ],
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    public function adminRecordPurchase(Request $request)
    {
        if (!$this->isAdmin($request)) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        $validated = $request->validate([
            'compartment_stock_product_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'uuid'],
            'purchaser_user_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $context = $this->fetchStockContextByProductId((string) $validated['compartment_stock_product_id']);
        if (!$context) {
            return response()->json([
                'message' => 'Compartment stock product not found.',
            ], 404);
        }

        $now = Carbon::now('UTC');
        $quantity = (int) $validated['quantity'];

        $result = DB::transaction(function () use ($validated, $request, $context, $now, $quantity) {
            $stock = CompartmentStocks::query()
                ->whereKey((string) $context->compartment_stock_id)
                ->lockForUpdate()
                ->first();

            $product = DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', (string) $context->compartment_stock_product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock || !$product) {
                return [
                    'status' => 404,
                    'message' => 'Compartment stock details not found.',
                ];
            }

            if ((string) $stock->status !== 'completed') {
                return [
                    'status' => 409,
                    'message' => 'Only completed stock items can be purchased from.',
                ];
            }

            $currentQty = (int) $product->quantity;

            if ($quantity > $currentQty) {
                return [
                    'status' => 422,
                    'message' => 'Insufficient stock quantity.',
                ];
            }

            $nextQty = $currentQty - $quantity;

            DB::table('compartment_stock_products')
                ->where('compartment_stock_product_id', (string) $context->compartment_stock_product_id)
                ->update([
                    'quantity' => $nextQty,
                    'updated_at' => $now,
                ]);

            $transaction = CompartmentStockProductTransaction::query()->create([
                'compartment_stock_product_transaction_id' => (string) Str::uuid(),
                'transaction_quantity' => $quantity,
                'compartment_stock_qr_session_id' => null,
                'compartment_stock_id' => (string) $context->compartment_stock_id,
                'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
                'vendor_id' => (string) $context->merchant_vendor_id,
                'rack_owner_vendor_id' => (string) $context->rack_owner_vendor_id,
                'generated_by_user_id' => null,
                'received_by_user_id' => null,
                'transaction_type' => 'purchase',
                'transaction_status' => 'confirmed',
                'prepared_quantity' => null,
                'received_quantity' => null,
                'quantity_delta' => -$quantity,
                'actor_user_id' => (string) ($validated['purchaser_user_id'] ?? $request->user()->user_id),
                'actor_vendor_id' => null,
                'event_source' => 'order',
                'event_source_id' => !empty($validated['order_id']) ? (string) $validated['order_id'] : null,
                'vendor_location_id' => isset($context->vendor_location_id) ? (int) $context->vendor_location_id : null,
                'rack_id' => isset($context->rack_id) ? (string) $context->rack_id : null,
                'compartment_id' => isset($context->compartment_id) ? (string) $context->compartment_id : null,
                'product_id' => isset($context->product_id) ? (string) $context->product_id : null,
                'description' => !empty($validated['description'])
                    ? (string) $validated['description']
                    : sprintf(
                        'Purchase deducted: %s from %s / %s / %s.',
                        (string) $context->product_name,
                        (string) $context->vendor_location_name,
                        (string) $context->rack_name,
                        (string) $context->compartment_name
                    ),
                'confirmed_at' => $now,
            ]);

            return [
                'status' => 200,
                'message' => 'Purchase transaction recorded successfully.',
                'data' => [
                    'transaction_id' => (string) $transaction->compartment_stock_product_transaction_id,
                    'compartment_stock_product_id' => (string) $context->compartment_stock_product_id,
                    'quantity_before' => $currentQty,
                    'quantity_after' => $nextQty,
                ],
            ];
        });

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $currentVendorId = $this->currentVendorId($request);
        $isAdmin = $this->isAdmin($request);

        if (!$isAdmin && !$currentVendorId) {
            return response()->json([
                'message' => 'Vendor access is required.',
            ], 403);
        }

        $perPage = (int) ($validated['per_page'] ?? 15);

        $transactions = DB::table('compartment_stock_product_transactions as tx')
            ->leftJoin('compartment_stock_products as csp', 'csp.compartment_stock_product_id', '=', 'tx.compartment_stock_product_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'csp.product_id')
            ->leftJoin('compartment_stock_qr_sessions as qs', 'qs.compartment_stock_qr_session_id', '=', 'tx.compartment_stock_qr_session_id')
            ->when(!$isAdmin, fn($query) => $query->where('tx.rack_owner_vendor_id', $currentVendorId))
            ->orderByDesc('tx.confirmed_at')
            ->paginate($perPage, [
                'tx.compartment_stock_product_transaction_id',
                'tx.compartment_stock_qr_session_id',
                'tx.compartment_stock_id',
                'tx.compartment_stock_product_id',
                'tx.transaction_type',
                'tx.transaction_status',
                'tx.prepared_quantity',
                'tx.received_quantity',
                'tx.quantity_delta',
                'tx.event_source',
                'tx.event_source_id',
                'tx.confirmed_at',
                'p.product_name',
                'qs.status as qr_session_status',
            ]);

        return response()->json([
            'data' => collect($transactions->items())->map(fn($item) => [
                'compartment_stock_product_transaction_id' => (string) $item->compartment_stock_product_transaction_id,
                'compartment_stock_qr_session_id' => $item->compartment_stock_qr_session_id ? (string) $item->compartment_stock_qr_session_id : null,
                'compartment_stock_id' => $item->compartment_stock_id ? (string) $item->compartment_stock_id : null,
                'compartment_stock_product_id' => $item->compartment_stock_product_id ? (string) $item->compartment_stock_product_id : null,
                'product_name' => $item->product_name ? (string) $item->product_name : null,
                'transaction_type' => isset($item->transaction_type) ? (string) $item->transaction_type : null,
                'transaction_status' => (string) $item->transaction_status,
                'prepared_quantity' => $item->prepared_quantity !== null ? (int) $item->prepared_quantity : null,
                'received_quantity' => $item->received_quantity !== null ? (int) $item->received_quantity : null,
                'quantity_delta' => $item->quantity_delta !== null ? (int) $item->quantity_delta : null,
                'event_source' => $item->event_source ? (string) $item->event_source : null,
                'event_source_id' => $item->event_source_id ? (string) $item->event_source_id : null,
                'confirmed_at' => $item->confirmed_at ? Carbon::parse($item->confirmed_at, 'UTC')->toIso8601String() : null,
                'qr_session_status' => $item->qr_session_status ? (string) $item->qr_session_status : null,
            ])->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function adminIndex(Request $request)
    {
        if (!$this->isAdmin($request)) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        return $this->history($request);
    }

    public function adminShow(Request $request, string $stock_product_transaction_id)
    {
        if (!$this->isAdmin($request)) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        $transaction = DB::table('compartment_stock_product_transactions as tx')
            ->leftJoin('compartment_stock_qr_sessions as qs', 'qs.compartment_stock_qr_session_id', '=', 'tx.compartment_stock_qr_session_id')
            ->leftJoin('compartment_stock_products as csp', 'csp.compartment_stock_product_id', '=', 'tx.compartment_stock_product_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'csp.product_id')
            ->where('tx.compartment_stock_product_transaction_id', $stock_product_transaction_id)
            ->first([
                'tx.*',
                'qs.status as qr_session_status',
                'qs.issued_at',
                'qs.expires_at',
                'qs.scanned_at',
                'qs.consumed_at',
                'p.product_name',
            ]);

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'compartment_stock_product_transaction_id' => (string) $transaction->compartment_stock_product_transaction_id,
                'compartment_stock_qr_session_id' => $transaction->compartment_stock_qr_session_id ? (string) $transaction->compartment_stock_qr_session_id : null,
                'compartment_stock_id' => $transaction->compartment_stock_id ? (string) $transaction->compartment_stock_id : null,
                'compartment_stock_product_id' => $transaction->compartment_stock_product_id ? (string) $transaction->compartment_stock_product_id : null,
                'vendor_id' => $transaction->vendor_id ? (string) $transaction->vendor_id : null,
                'rack_owner_vendor_id' => $transaction->rack_owner_vendor_id ? (string) $transaction->rack_owner_vendor_id : null,
                'product_name' => $transaction->product_name ? (string) $transaction->product_name : null,
                'transaction_type' => (string) $transaction->transaction_type,
                'transaction_status' => (string) $transaction->transaction_status,
                'prepared_quantity' => $transaction->prepared_quantity !== null ? (int) $transaction->prepared_quantity : null,
                'received_quantity' => $transaction->received_quantity !== null ? (int) $transaction->received_quantity : null,
                'quantity_delta' => $transaction->quantity_delta !== null ? (int) $transaction->quantity_delta : null,
                'actor_user_id' => $transaction->actor_user_id ? (string) $transaction->actor_user_id : null,
                'actor_vendor_id' => $transaction->actor_vendor_id ? (string) $transaction->actor_vendor_id : null,
                'event_source' => $transaction->event_source ? (string) $transaction->event_source : null,
                'event_source_id' => $transaction->event_source_id ? (string) $transaction->event_source_id : null,
                'vendor_location_id' => $transaction->vendor_location_id !== null ? (int) $transaction->vendor_location_id : null,
                'rack_id' => $transaction->rack_id ? (string) $transaction->rack_id : null,
                'compartment_id' => $transaction->compartment_id ? (string) $transaction->compartment_id : null,
                'product_id' => $transaction->product_id ? (string) $transaction->product_id : null,
                'description' => $transaction->description ? (string) $transaction->description : null,
                'confirmed_at' => $transaction->confirmed_at ? Carbon::parse($transaction->confirmed_at, 'UTC')->toIso8601String() : null,
                'qr_session_status' => $transaction->qr_session_status ? (string) $transaction->qr_session_status : null,
                'issued_at' => $transaction->issued_at ? Carbon::parse($transaction->issued_at, 'UTC')->toIso8601String() : null,
                'expires_at' => $transaction->expires_at ? Carbon::parse($transaction->expires_at, 'UTC')->toIso8601String() : null,
                'scanned_at' => $transaction->scanned_at ? Carbon::parse($transaction->scanned_at, 'UTC')->toIso8601String() : null,
                'consumed_at' => $transaction->consumed_at ? Carbon::parse($transaction->consumed_at, 'UTC')->toIso8601String() : null,
            ],
        ]);
    }

    private function currentVendorId(Request $request): ?string
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $vendorId = Vendors::query()
            ->where('user_id', $user->user_id)
            ->value('vendor_id');

        return $vendorId ? (string) $vendorId : null;
    }

    private function isAdmin(Request $request): bool
    {
        return (string) ($request->user()?->role ?? '') === 'admin';
    }

    private function normalizePayloadInput(mixed $payload): ?array
    {
        if (is_string($payload)) {
            $decoded = json_decode(trim($payload), true);
            return is_array($decoded) ? $decoded : null;
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function signPayload(array $payload): string
    {
        $signatureFields = [
            (string) ($payload['compartment_stock_id'] ?? ''),
            (string) ($payload['compartment_stock_product_id'] ?? ''),
            (string) ($payload['vendor_id'] ?? ''),
            (string) ($payload['issued_at'] ?? ''),
            (string) ($payload['expires_at'] ?? ''),
            (string) ($payload['nonce'] ?? ''),
        ];

        return hash_hmac('sha256', implode('|', $signatureFields), (string) config('app.key'));
    }

    private function fetchStockContextByProductId(string $compartment_stock_product_id): ?object
    {
        return DB::table('compartment_stock_products as csp')
            ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
            ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
            ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
            ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
            ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
            ->join('products as p', 'p.product_id', '=', 'csp.product_id')
            ->leftJoin('vendors as merchant_vendor', 'merchant_vendor.vendor_id', '=', 'tc.vendor_id')
            ->leftJoin('vendors as owner_vendor', 'owner_vendor.vendor_id', '=', 'vl.vendor_id')
            ->where('csp.compartment_stock_product_id', $compartment_stock_product_id)
            ->first([
                'csp.compartment_stock_product_id',
                'csp.compartment_stock_id',
                'csp.quantity',
                'csp.expiry_date',
                'p.product_id',
                'p.product_name',
                'cs.status as stock_status',
                'tc.tender_compartment_id',
                'tc.vendor_id as merchant_vendor_id',
                'merchant_vendor.vendor_name as merchant_vendor_name',
                'vl.vendor_id as rack_owner_vendor_id',
                'owner_vendor.vendor_name as rack_owner_vendor_name',
                'r.rack_id',
                'r.rack_name',
                'c.compartment_id',
                'c.label as compartment_name',
                'vl.id as vendor_location_id',
                'vl.location_name as vendor_location_name',
            ]);
    }

    private function fetchSessionContextById(string $compartment_stock_qr_session_id): ?object
    {
        $session = DB::table('compartment_stock_qr_sessions as qs')
            ->join('compartment_stock_products as csp', 'csp.compartment_stock_product_id', '=', 'qs.compartment_stock_product_id')
            ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'qs.compartment_stock_id')
            ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
            ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
            ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
            ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
            ->join('products as p', 'p.product_id', '=', 'csp.product_id')
            ->leftJoin('vendors as merchant_vendor', 'merchant_vendor.vendor_id', '=', 'qs.vendor_id')
            ->leftJoin('vendors as owner_vendor', 'owner_vendor.vendor_id', '=', 'qs.rack_owner_vendor_id')
            ->where('qs.compartment_stock_qr_session_id', $compartment_stock_qr_session_id)
            ->first([
                'qs.compartment_stock_qr_session_id',
                'qs.compartment_stock_id',
                'qs.compartment_stock_product_id',
                'qs.vendor_id',
                'qs.rack_owner_vendor_id',
                'qs.generated_by_user_id',
                'qs.nonce',
                'qs.payload_json',
                'qs.signature_hash',
                'qs.issued_at',
                'qs.expires_at',
                'qs.scanned_at',
                'qs.scanned_by_user_id',
                'qs.consumed_at',
                'qs.consumed_by_user_id',
                'qs.status',
                'csp.quantity as prepared_quantity',
                'csp.expiry_date',
                'csp.product_id',
                'p.product_name',
                'cs.status as stock_status',
                'merchant_vendor.vendor_name as merchant_vendor_name',
                'owner_vendor.vendor_name as rack_owner_vendor_name',
                'vl.id as vendor_location_id',
                'vl.location_name as vendor_location_name',
                'r.rack_id',
                'r.rack_name',
                'c.compartment_id',
                'c.label as compartment_name',
            ]);

        if ($session && is_string($session->payload_json)) {
            $session->payload_json = json_decode($session->payload_json, true) ?? [];
        }

        return $session;
    }

    private function synchronizeSessionStatus(object $session): object
    {
        if ((string) $session->status === 'active' && $session->expires_at) {
            if (Carbon::parse($session->expires_at, 'UTC')->isPast()) {
                CompartmentStockQrSession::query()
                    ->whereKey($session->compartment_stock_qr_session_id)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => Carbon::now('UTC'),
                    ]);

                $session->status = 'expired';
            }
        }

        return $session;
    }

    private function formatSessionData(object $session, ?object $context = null): array
    {
        $payload = is_array($session->payload_json ?? null)
            ? $session->payload_json
            : [];
        $source = $context ?? $session;

        return [
            'compartment_stock_qr_session_id' => (string) $session->compartment_stock_qr_session_id,
            'status' => (string) $session->status,
            'issued_at' => $session->issued_at
                ? Carbon::parse($session->issued_at, 'UTC')->toIso8601String()
                : null,
            'expires_at' => $session->expires_at
                ? Carbon::parse($session->expires_at, 'UTC')->toIso8601String()
                : null,
            'qr_value' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null,
            'payload' => $payload,
            'summary' => [
                'product_name' => (string) ($source->product_name ?? ''),
                'quantity' => isset($source->quantity)
                    ? (int) $source->quantity
                    : (isset($source->prepared_quantity) ? (int) $source->prepared_quantity : null),
                'vendor_location_name' => (string) ($source->vendor_location_name ?? ''),
                'rack_name' => (string) ($source->rack_name ?? ''),
                'compartment_name' => (string) ($source->compartment_name ?? ''),
                'stock_status' => (string) ($source->stock_status ?? ''),
                'merchant_vendor_name' => isset($source->merchant_vendor_name)
                    ? (string) $source->merchant_vendor_name
                    : null,
            ],
        ];
    }

    private function formatValidationData(object $session): array
    {
        return [
            'compartment_stock_qr_session_id' => (string) $session->compartment_stock_qr_session_id,
            'status' => (string) $session->status,
            'compartment_stock_id' => (string) $session->compartment_stock_id,
            'compartment_stock_product_id' => (string) $session->compartment_stock_product_id,
            'product_name' => (string) $session->product_name,
            'prepared_quantity' => $session->prepared_quantity !== null ? (int) $session->prepared_quantity : null,
            'vendor_location_name' => (string) $session->vendor_location_name,
            'rack_name' => (string) $session->rack_name,
            'compartment_name' => (string) $session->compartment_name,
            'merchant_vendor_name' => $session->merchant_vendor_name ? (string) $session->merchant_vendor_name : null,
            'stock_status' => (string) $session->stock_status,
            'issued_at' => $session->issued_at
                ? Carbon::parse($session->issued_at, 'UTC')->toIso8601String()
                : null,
            'expires_at' => $session->expires_at
                ? Carbon::parse($session->expires_at, 'UTC')->toIso8601String()
                : null,
            'scanned_at' => $session->scanned_at
                ? Carbon::parse($session->scanned_at, 'UTC')->toIso8601String()
                : null,
        ];
    }

    private function formatConfirmResult(?object $transaction, ?object $context): ?array
    {
        if (!$transaction) {
            return null;
        }

        return [
            'transaction_id' => (string) $transaction->compartment_stock_product_transaction_id,
            'compartment_stock_qr_session_id' => $transaction->compartment_stock_qr_session_id
                ? (string) $transaction->compartment_stock_qr_session_id
                : ($context ? (string) $context->compartment_stock_qr_session_id : null),
            'compartment_stock_id' => $transaction->compartment_stock_id
                ? (string) $transaction->compartment_stock_id
                : ($context ? (string) $context->compartment_stock_id : null),
            'compartment_stock_product_id' => $transaction->compartment_stock_product_id
                ? (string) $transaction->compartment_stock_product_id
                : ($context ? (string) $context->compartment_stock_product_id : null),
            'transaction_status' => (string) ($transaction->transaction_status ?? 'confirmed'),
            'confirmed_at' => $transaction->confirmed_at
                ? Carbon::parse($transaction->confirmed_at, 'UTC')->toIso8601String()
                : null,
            'stock_status' => (string) ($transaction->transaction_status ?? 'confirmed') === 'confirmed'
                ? 'completed'
                : ($context ? (string) $context->stock_status : 'completed'),
        ];
    }
}
