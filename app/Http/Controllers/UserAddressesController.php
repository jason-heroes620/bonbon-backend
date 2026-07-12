<?php

namespace App\Http\Controllers;

use App\Models\UserAddresses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserAddressesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $addresses = UserAddresses::query()
            ->where('user_id', $user->user_id)
            ->orderByDesc('is_primary')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (UserAddresses $address) => $this->formatAddress($address))
            ->values();

        return response()->json([
            'data' => $addresses,
        ]);
    }

    public function show(Request $request, string $user_address_id)
    {
        $address = $this->findUserAddress($request, $user_address_id);
        if (!$address) {
            return response()->json([
                'message' => 'Address not found.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatAddress($address),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $this->validateAddressPayload($request);
        $addressPayload = $this->buildAddressPayload($validated);

        $address = DB::transaction(function () use ($user, $validated, $addressPayload) {
            $shouldBePrimary = (bool) ($validated['is_primary'] ?? false);
            $hasExisting = UserAddresses::query()
                ->where('user_id', $user->user_id)
                ->exists();

            if (!$hasExisting) {
                $shouldBePrimary = true;
            }

            if ($shouldBePrimary) {
                UserAddresses::query()
                    ->where('user_id', $user->user_id)
                    ->update(['is_primary' => false]);
            }

            return UserAddresses::query()->create([
                'user_address_id' => (string) Str::uuid(),
                'user_id' => (string) $user->user_id,
                'address' => $addressPayload,
                'is_primary' => $shouldBePrimary,
            ]);
        });

        return response()->json([
            'message' => 'Address added successfully.',
            'data' => $this->formatAddress($address),
        ], 201);
    }

    public function update(Request $request, string $user_address_id)
    {
        $address = $this->findUserAddress($request, $user_address_id);
        if (!$address) {
            return response()->json([
                'message' => 'Address not found.',
            ], 404);
        }

        $validated = $this->validateAddressPayload($request);
        $addressPayload = $this->buildAddressPayload($validated);

        DB::transaction(function () use ($request, $address, $validated, $addressPayload) {
            $shouldBePrimary = (bool) ($validated['is_primary'] ?? false);

            if ($shouldBePrimary) {
                UserAddresses::query()
                    ->where('user_id', $request->user()->user_id)
                    ->where('user_address_id', '!=', $address->user_address_id)
                    ->update(['is_primary' => false]);
            }

            $address->update([
                'address' => $addressPayload,
                'is_primary' => $shouldBePrimary,
            ]);
        });

        $address->refresh();

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => $this->formatAddress($address),
        ]);
    }

    public function destroy(Request $request, string $user_address_id)
    {
        $address = $this->findUserAddress($request, $user_address_id);
        if (!$address) {
            return response()->json([
                'message' => 'Address not found.',
            ], 404);
        }

        $userId = (string) $request->user()->user_id;
        $wasPrimary = (bool) $address->is_primary;

        DB::transaction(function () use ($address, $userId, $wasPrimary) {
            $address->delete();

            if (!$wasPrimary) {
                return;
            }

            $nextAddress = UserAddresses::query()
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->first();

            if ($nextAddress) {
                $nextAddress->update([
                    'is_primary' => true,
                ]);
            }
        });

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }

    private function findUserAddress(Request $request, string $userAddressId): ?UserAddresses
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        return UserAddresses::query()
            ->where('user_id', $user->user_id)
            ->where('user_address_id', $userAddressId)
            ->first();
    }

    private function validateAddressPayload(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:20'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
    }

    private function buildAddressPayload(array $validated): array
    {
        return [
            'label' => $validated['label'],
            'recipient_name' => $validated['recipient_name'],
            'contact_no' => $validated['contact_no'],
            'line1' => $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'postcode' => $validated['postcode'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'country' => $validated['country'] ?? 'Malaysia',
        ];
    }

    private function formatAddress(UserAddresses $address): array
    {
        $payload = is_array($address->address) ? $address->address : [];

        return [
            'user_address_id' => (string) $address->user_address_id,
            'label' => (string) ($payload['label'] ?? ''),
            'recipient_name' => (string) ($payload['recipient_name'] ?? ''),
            'contact_no' => (string) ($payload['contact_no'] ?? ''),
            'line1' => (string) ($payload['line1'] ?? ''),
            'line2' => isset($payload['line2']) ? (string) $payload['line2'] : null,
            'postcode' => (string) ($payload['postcode'] ?? ''),
            'city' => (string) ($payload['city'] ?? ''),
            'state' => (string) ($payload['state'] ?? ''),
            'country' => (string) ($payload['country'] ?? 'Malaysia'),
            'is_primary' => (bool) $address->is_primary,
            'address' => $payload,
            'created_at' => optional($address->created_at)?->toIso8601String(),
            'updated_at' => optional($address->updated_at)?->toIso8601String(),
        ];
    }
}
