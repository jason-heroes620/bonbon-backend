<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\VendorLocation;
use App\Models\Vendors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VendorsController extends Controller
{
    public function vendors(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'search' => ['nullable', 'string', 'max:150'],
            'distance_km' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null || $value === '') {
                    return;
                }

                if (is_string($value) && strtolower($value) === 'all') {
                    return;
                }

                if (!is_numeric($value)) {
                    $fail('The ' . $attribute . ' must be a number or all.');
                    return;
                }

                if ((float) $value < 0) {
                    $fail('The ' . $attribute . ' must be at least 0.');
                }
            }],
            'categories' => ['nullable'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;

        $distanceKm = null;
        if (array_key_exists('distance_km', $validated) && $validated['distance_km'] !== null && strtolower((string)$validated['distance_km']) !== 'all') {
            $distanceKm = (float) $validated['distance_km'];
        }

        $categoryIds = $request->input('categories');
        if (is_string($categoryIds)) {
            $categoryIds = array_filter(array_map('trim', explode(',', $categoryIds)));
        } elseif (!is_array($categoryIds)) {
            $categoryIds = [];
        } else {
            $categoryIds = array_filter(array_map('strval', $categoryIds));
        }

        $vendors = VendorLocation::query()
            ->join('vendors', 'vendors.vendor_id', '=', 'vendor_locations.vendor_id')
            ->where('vendors.is_active', 'active')
            ->when($distanceKm !== null, function ($q) use ($latitude, $longitude, $distanceKm) {
                $this->applyBoundingBox($q, $latitude, $longitude, $distanceKm);
            })
            ->select([
                'vendors.vendor_id',
                'vendor_locations.id as vendor_location_id',
                'vendors.vendor_name',
                'vendors.profile_picture',
                'vendor_locations.location_name',
                'vendor_locations.address',
                'vendor_locations.latitude',
                'vendor_locations.longitude',
            ])
            ->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(vendor_locations.latitude)) * cos(radians(vendor_locations.longitude) - radians(?)) + sin(radians(?)) * sin(radians(vendor_locations.latitude)))) as distance_km',
                [$latitude, $longitude, $latitude]
            )
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereExists(function ($sq) use ($categoryIds) {
                    $sq->selectRaw('1')
                        ->from('vendor_categories')
                        ->whereColumn('vendor_categories.vendor_id', 'vendor_locations.vendor_id')
                        ->whereIn('vendor_categories.category_id', $categoryIds);
                });
            })
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('vendors.vendor_name', 'like', "%{$search}%")
                        ->orWhere('vendor_locations.location_name', 'like', "%{$search}%")
                        ->orWhere('vendor_locations.address', 'like', "%{$search}%");
                });
            })
            ->when($distanceKm !== null, function ($q) use ($distanceKm) {
                $q->havingRaw('distance_km <= ?', [$distanceKm]);
            })
            ->orderBy('distance_km', 'asc')
            ->orderBy('vendor_locations.id', 'desc')
            ->paginate($perPage);

        $items = collect($vendors->items())->map(function ($row) {
            [$city, $state] = $this->extractCityState(
                (string) ($row->address ?? ''),
                (string) ($row->location_name ?? '')
            );

            return [
                'vendor_id' => $row->vendor_id,
                'vendor_name' => $row->vendor_name,
                'profile_picture' => Storage::url($row->profile_picture),
                'city' => $city,
                'state' => $state,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                'distance_km' => $row->distance_km !== null ? (float) $row->distance_km : null,
            ];
        })->all();

        return response()->json([
            'data' => empty($items) ? [] : $items,
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
                'from' => $vendors->firstItem(),
                'to' => $vendors->lastItem(),
                'next_page_url' => $vendors->nextPageUrl(),
                'prev_page_url' => $vendors->previousPageUrl(),
            ],
        ]);
    }

    private function applyBoundingBox($query, float $latitude, float $longitude, float $maxDistanceKm): void
    {
        if ($maxDistanceKm <= 0) {
            return;
        }

        $latDelta = $maxDistanceKm / 111.045;
        $cosLat = cos(deg2rad($latitude));
        $lngDelta = $cosLat > 0.000001 ? ($maxDistanceKm / (111.045 * $cosLat)) : 180.0;

        $minLat = max(-90, $latitude - $latDelta);
        $maxLat = min(90, $latitude + $latDelta);
        $minLng = $longitude - $lngDelta;
        $maxLng = $longitude + $lngDelta;

        $query->whereBetween('vendor_locations.latitude', [$minLat, $maxLat]);

        if ($minLng < -180 || $maxLng > 180) {
            $wrappedMinLng = $minLng < -180 ? $minLng + 360 : $minLng;
            $wrappedMaxLng = $maxLng > 180 ? $maxLng - 360 : $maxLng;

            $query->where(function ($q) use ($wrappedMinLng, $wrappedMaxLng, $minLng, $maxLng) {
                if ($minLng < -180) {
                    $q->whereBetween('vendor_locations.longitude', [-180, $maxLng])
                        ->orWhereBetween('vendor_locations.longitude', [$wrappedMinLng, 180]);
                } else {
                    $q->whereBetween('vendor_locations.longitude', [$minLng, 180])
                        ->orWhereBetween('vendor_locations.longitude', [-180, $wrappedMaxLng]);
                }
            });
        } else {
            $query->whereBetween('vendor_locations.longitude', [$minLng, $maxLng]);
        }
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

    public function vendor(Request $request, string $vendor_id)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        $vendor = Vendors::query()
            ->where('vendor_id', $vendor_id)
            ->where('is_active', 'active')
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        $profilePictureUrl = null;
        if (!empty($vendor->profile_picture)) {
            $profilePictureUrl = str_starts_with($vendor->profile_picture, '/storage/')
                ? $vendor->profile_picture
                : Storage::url($vendor->profile_picture);
        }

        $locationsQuery = VendorLocation::query()
            ->where('vendor_id', $vendor->vendor_id)
            ->where('longitude',  $longitude)
            ->where('latitude', $latitude)
            ->select([
                'id',
                'vendor_id',
                'location_name',
                'address',
                'latitude',
                'longitude',
                'place_id',
                'is_primary',
                'contact_no',
            ]);

        $location = $locationsQuery
            ->orderBy('is_primary', 'desc')
            ->first();

        return response()->json([
            'data' => [
                'vendor' => $vendor,
                'profile_picture_url' => $profilePictureUrl,
                'location' => $location,
            ],
        ]);
    }

    public function vendorCategories(Request $request)
    {
        $vendorCategories = Categories::query()
            ->select([
                'category_id',
                'category_name',
            ])
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => $vendorCategories,
        ]);
    }

    public function merchantProfile(Request $request)
    {
        $vendor = Vendors::query()
            ->select('vendor_name', 'contact_no')
            ->where('user_id', $request->user()?->user_id)
            ->where('is_active', 'active')
            ->first();

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }
        Log::info('Merchant profile');
        Log::info($vendor);
        return response()->json(
            $vendor,
        );
    }
}
