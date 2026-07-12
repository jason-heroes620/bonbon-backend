<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'string', 'max:36'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:distance,price_asc,price_desc,name_asc'],
        ]);

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : 3.14708;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : 101.69532;
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
        $categoryId = isset($validated['category_id']) ? trim((string) $validated['category_id']) : null;
        $minPrice = array_key_exists('min_price', $validated) && $validated['min_price'] !== null ? (float) $validated['min_price'] : null;
        $maxPrice = array_key_exists('max_price', $validated) && $validated['max_price'] !== null ? (float) $validated['max_price'] : null;
        $sort = (string) ($validated['sort'] ?? 'distance');

        $distanceExpr = $this->distanceExpression('vl');
        $effectivePriceExpr = $this->effectivePriceExpression('p');

        $base = $this->validStockedProductsQuery()
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('p.product_name', 'like', "%{$search}%")
                        ->orWhere('v.vendor_name', 'like', "%{$search}%")
                        ->orWhere('vl.location_name', 'like', "%{$search}%");
                });
            })
            ->when(!empty($categoryId), function ($q) use ($categoryId) {
                $q->whereExists(function ($sq) use ($categoryId) {
                    $sq->selectRaw('1')
                        ->from('product_categories')
                        ->whereColumn('product_categories.product_id', 'p.product_id')
                        ->where('product_categories.category_id', '=', $categoryId);
                });
            })
            ->when($minPrice !== null, function ($q) use ($minPrice, $effectivePriceExpr) {
                $q->whereRaw("{$effectivePriceExpr} >= ?", [$minPrice]);
            })
            ->when($maxPrice !== null, function ($q) use ($maxPrice, $effectivePriceExpr) {
                $q->whereRaw("{$effectivePriceExpr} <= ?", [$maxPrice]);
            });

        // 1. Calculate distance once in the subquery select, then pass the alias/bindings cleanly
        $query = DB::query()->fromSub(
            $base
                ->select([
                    'p.product_id',
                    'p.product_name',
                    'p.is_unlimited',
                    'v.vendor_id',
                    'v.vendor_name',
                    'vl.location_name',
                    'vl.address',
                    'vl.latitude as store_latitude',
                    'vl.longitude as store_longitude',
                    'primary_image.mobile_image_url',
                    'primary_image.image_url',
                ])
                ->selectRaw("{$effectivePriceExpr} as unit_price")
                ->selectRaw("{$distanceExpr} as distance_km", [$latitude, $longitude, $latitude])
                // Push availability boolean conversion directly to database
                ->selectRaw("(p.is_unlimited = 1 OR SUM(csp.quantity) OVER (PARTITION BY p.product_id) > 0) as is_available")
                ->selectRaw(
                    "ROW_NUMBER() OVER (PARTITION BY p.product_id ORDER BY {$distanceExpr} ASC, vl.location_name ASC) as rn",
                    [$latitude, $longitude, $latitude]
                ),
            't'
        )->where('rn', '=', 1);

        if ($sort === 'price_asc') {
            $query->orderBy('unit_price', 'asc')->orderBy('product_name', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('unit_price', 'desc')->orderBy('product_name', 'asc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('product_name', 'asc')->orderBy('distance_km', 'asc');
        } else {
            $query->orderBy('distance_km', 'asc')->orderBy('product_name', 'asc');
        }

        // 2. Performance Win: Use simplePaginate if total counts aren't strictly mandatory 
        // or apply a fast-pagination strategy. 
        $products = $query->simplePaginate($perPage);

        // 3. Cleaner array mapping with pre-calculated DB states
        $items = collect($products->items())->map(function ($row) {
            [$city, $state] = $this->extractCityState(
                (string) ($row->address ?? ''),
                (string) ($row->location_name ?? '')
            );

            return [
                'product_id'   => (string) $row->product_id,
                'product_name' => (string) $row->product_name,
                'unit_price'   => $row->unit_price !== null ? (float) $row->unit_price : 0.0,
                'vendor' => [
                    'vendor_id'   => (string) $row->vendor_id,
                    'vendor_name' => (string) $row->vendor_name,
                ],
                'store' => [
                    'location_name' => (string) ($row->location_name ?? ''),
                    'city'          => $city,
                    'state'         => $state,
                    'latitude'      => $row->store_latitude !== null ? (float) $row->store_latitude : null,
                    'longitude'     => $row->store_longitude !== null ? (float) $row->store_longitude : null,
                ],
                'distance_km'   => $row->distance_km !== null ? (float) $row->distance_km : null,
                'is_available'  => (bool) $row->is_available,
                'thumbnail_url' => (string) ($row->mobile_image_url ?: $row->image_url) ?: null,
            ];
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page'  => $products->currentPage(),
                'per_page'      => $products->perPage(),
                'from'          => $products->firstItem(),
                'to'            => $products->lastItem(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }

    public function categories()
    {
        $categories = Categories::query()
            ->where('is_active', true)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('product_categories')
                    ->join('products', 'products.product_id', '=', 'product_categories.product_id')
                    ->whereColumn('product_categories.category_id', 'categories.category_id')
                    ->whereExists(function ($stockQuery) {
                        $stockQuery->selectRaw('1')
                            ->from('compartment_stock_products as csp')
                            ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
                            ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
                            ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
                            ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
                            ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
                            ->join('vendors as v', 'v.vendor_id', '=', 'vl.vendor_id')
                            ->whereColumn('csp.product_id', 'products.product_id')
                            ->where('products.is_active', '=', 1)
                            ->where('products.is_visible', '=', 1)
                            ->where('v.is_active', '=', 'active')
                            ->where('csp.quantity', '>', 0);

                        $this->applySellableTenderWindow($stockQuery, 'tc', 'cs');
                    });
            })
            ->orderBy('category_name')
            ->get(['category_id', 'category_name']);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function show(Request $request, string $product_id)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        $product = Products::query()
            ->whereKey($product_id)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found.',
            ], 404);
        }

        $vendorRow = DB::table('vendors')
            ->where('vendor_id', $product->vendor_id)
            ->select(['vendor_id', 'vendor_name', 'is_active'])
            ->first();

        if (!$vendorRow || (string) $vendorRow->is_active !== 'active') {
            return response()->json([
                'message' => 'Vendor not found.',
            ], 404);
        }

        $images = DB::table('product_images')
            ->where('product_id', $product->product_id)
            ->where('is_active', 1)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get([
                'product_image_id',
                'image_url',
                'mobile_image_url',
                'is_primary',
            ])
            ->map(fn($row) => [
                'product_image_id' => (string) $row->product_image_id,
                'image_url' => $row->image_url ? (string) $row->image_url : null,
                'mobile_image_url' => $row->mobile_image_url ? (string) $row->mobile_image_url : null,
                'is_primary' => (bool) $row->is_primary,
            ])
            ->values()
            ->all();

        $pickupOptions = $this->fetchPickupOptions($product, $latitude, $longitude, (string) $vendorRow->vendor_name);

        $location = null;
        $distanceKm = null;
        if (!empty($pickupOptions)) {
            $primaryOption = $pickupOptions[0];
            $location = [
                'vendor_location_id' => $primaryOption['vendor_location_id'],
                'location_name' => $primaryOption['vendor_location_name'],
                'city' => $primaryOption['city'],
                'state' => $primaryOption['state'],
                'latitude' => $primaryOption['latitude'],
                'longitude' => $primaryOption['longitude'],
                'address' => $primaryOption['address'],
            ];
            // $distanceKm = $primaryOption['distance_km'];
        }

        $unitPrice = (float) ($product->sale_price > 0 ? $product->sale_price : $product->retail_price);
        $stockQty = collect($pickupOptions)->sum(fn($option) => (int) ($option['available_quantity'] ?? 0));
        $isUnlimited = (bool) ($product->is_unlimited ?? false);
        $isAvailable = $isUnlimited || (($stockQty ?? 0) > 0);

        return response()->json([
            'data' => [
                'product_id' => (string) $product->product_id,
                'product_name' => (string) $product->product_name,
                'product_description' => (string) ($product->product_description ?? ''),
                'uom' => (string) ($product->uom ?? 'unit'),
                'unit_price' => round($unitPrice, 2),
                'stock_quantity' => $stockQty,
                'is_unlimited' => $isUnlimited,
                'is_available' => $isAvailable,
                'vendor' => [
                    'vendor_id' => (string) $vendorRow->vendor_id,
                    'vendor_name' => (string) $vendorRow->vendor_name,
                ],
                'store' => $location,
                'distance_km' => $distanceKm,
                'images' => $images,
                'pickup_options' => $pickupOptions,
            ],
        ]);
    }

    private function fetchPickupOptions(Products $product, ?float $latitude, ?float $longitude, string $vendorName): array
    {
        $query = $this->validStockedProductsQuery()
            ->where('csp.product_id', $product->product_id)
            ->select([
                'csp.compartment_stock_product_id',
                'csp.compartment_stock_id',
                'csp.quantity',
                'vl.id as vendor_location_id',
                'vl.location_name as vendor_location_name',
                'vl.address',
                'vl.latitude',
                'vl.longitude',
                'r.rack_id',
                'r.rack_name',
                'c.compartment_id',
                'c.label as compartment_name',
            ]);

        // if ($latitude !== null && $longitude !== null) {
        //     $distanceExpr = $this->distanceExpression('vl');
        //     $query->selectRaw("{$distanceExpr} as distance_km", [$latitude, $longitude, $latitude])
        //         ->orderBy('distance_km', 'asc');
        // }

        $rows = $query
            ->orderBy('vl.location_name')
            ->orderBy('r.rack_name')
            ->orderBy('c.label')
            ->get();

        return $rows->map(function ($row) use ($vendorName) {
            [$city, $state] = $this->extractCityState(
                (string) ($row->address ?? ''),
                (string) ($row->vendor_location_name ?? '')
            );

            return [
                'compartment_stock_product_id' => (string) $row->compartment_stock_product_id,
                'compartment_stock_id' => (string) $row->compartment_stock_id,
                'vendor_location_id' => (int) $row->vendor_location_id,
                'vendor_location_name' => (string) $row->vendor_location_name,
                'vendor_name' => $vendorName,
                'available_quantity' => (int) $row->quantity,
                'address' => (string) ($row->address ?? ''),
                'city' => $city,
                'state' => $state,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                // 'distance_km' => $row->distance_km !== null ? (float) $row->distance_km : null,
                'rack_id' => (string) $row->rack_id,
                'rack_name' => (string) $row->rack_name,
                'compartment_id' => (string) $row->compartment_id,
                'compartment_name' => (string) $row->compartment_name,
            ];
        })->values()->all();
    }

    private function validStockedProductsQuery()
    {
        $query = DB::table('compartment_stock_products as csp')
            ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
            ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
            ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
            ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
            ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
            ->join('vendors as v', 'v.vendor_id', '=', 'vl.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'csp.product_id')
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'p.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->where('v.is_active', '=', 'active')
            ->where('p.is_active', '=', 1)
            ->where('p.is_visible', '=', 1)
            ->where('csp.quantity', '>', 0);

        $this->applySellableTenderWindow($query, 'tc', 'cs');

        return $query;
    }

    private function applySellableTenderWindow($query, string $tenderAlias, string $stockAlias): void
    {
        $now = now();

        $query->where("{$stockAlias}.status", '=', 'completed')
            ->where("{$tenderAlias}.tender_status", '=', 'paid')
            ->whereNotNull("{$tenderAlias}.tender_start_date")
            ->whereNotNull("{$tenderAlias}.tender_end_date")
            ->where("{$tenderAlias}.tender_start_date", '<=', $now)
            ->where("{$tenderAlias}.tender_end_date", '>=', $now);
    }

    private function effectivePriceExpression(string $productAlias): string
    {
        return "CASE WHEN {$productAlias}.sale_price > 0 THEN {$productAlias}.sale_price ELSE {$productAlias}.retail_price END";
    }

    private function distanceExpression(string $locationAlias): string
    {
        return "(6371 * acos(cos(radians(?)) * cos(radians({$locationAlias}.latitude)) * cos(radians({$locationAlias}.longitude) - radians(?)) + sin(radians(?)) * sin(radians({$locationAlias}.latitude))))";
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
}
