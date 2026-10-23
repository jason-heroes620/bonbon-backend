<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'vendor_id' => ['nullable', 'string', 'max:36'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'fulfillment' => ['nullable', 'in:pickup,delivery'],
            'sort' => ['nullable', 'in:distance,price_asc,price_desc,name_asc'],
        ]);

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : 3.14708;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : 101.69532;
        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
        $categoryId = isset($validated['category_id']) ? trim((string) $validated['category_id']) : null;
        $vendorId = isset($validated['vendor_id']) ? trim((string) $validated['vendor_id']) : null;
        $minPrice = array_key_exists('min_price', $validated) && $validated['min_price'] !== null ? (float) $validated['min_price'] : null;
        $maxPrice = array_key_exists('max_price', $validated) && $validated['max_price'] !== null ? (float) $validated['max_price'] : null;
        $fulfillment = (string) ($validated['fulfillment'] ?? 'pickup');
        $sort = (string) ($validated['sort'] ?? 'distance');

        $query = $fulfillment === 'delivery'
            ? $this->buildDeliveryProductsQuery($search, $categoryId, $vendorId, $minPrice, $maxPrice, $latitude, $longitude)
            : $this->buildPickupProductsQuery($search, $categoryId, $vendorId, $minPrice, $maxPrice, $latitude, $longitude);

        if ($sort === 'price_asc') {
            $query->orderBy('unit_price', 'asc')->orderBy('product_name', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('unit_price', 'desc')->orderBy('product_name', 'asc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('product_name', 'asc')->orderBy('sort_distance_km', 'asc');
        } else {
            $query->orderBy('sort_distance_km', 'asc')->orderBy('product_name', 'asc');
        }

        // 2. Performance Win: Use simplePaginate if total counts aren't strictly mandatory 
        // or apply a fast-pagination strategy. 
        $products = $query->simplePaginate($perPage);

        $rows = collect($products->items());

        // 3. Resolve the store displayed for each listed product with one narrow,
        // index-driven query over the page's product ids only. Keeping this out of the
        // paginated query lets MySQL sort aggregated rows instead of materializing
        // every product/location pair.
        $stores = $this->fetchListingStores(
            $rows->pluck('product_id')->map(fn ($id) => (string) $id)->all(),
            $fulfillment,
            $latitude,
            $longitude
        );

        // 4. Cleaner array mapping with pre-calculated DB states
        $items = $rows->map(function ($row) use ($fulfillment, $stores) {
            $store = $stores[(string) $row->product_id] ?? null;

            [$city, $state] = $this->extractCityState(
                (string) ($store['address'] ?? ''),
                (string) ($store['location_name'] ?? '')
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
                    'location_name' => (string) ($store['location_name'] ?? ''),
                    'city'          => $city,
                    'state'         => $state,
                    'latitude'      => $store['latitude'] ?? null,
                    'longitude'     => $store['longitude'] ?? null,
                ],
                'distance_km'   => $store['distance_km'] ?? null,
                'is_available'  => (bool) $row->is_available,
                'thumbnail_url' => (string) ($row->mobile_image_url ?: $row->image_url) ?: null,
                'fulfillment' => $fulfillment,
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
        $validated = request()->validate([
            'fulfillment' => ['nullable', 'in:pickup,delivery'],
        ]);

        $fulfillment = (string) ($validated['fulfillment'] ?? 'pickup');

        $categories = Categories::query()
            ->where('is_active', true)
            ->whereExists(function ($q) use ($fulfillment) {
                $q->selectRaw('1')
                    ->from('product_categories')
                    ->join('products', 'products.product_id', '=', 'product_categories.product_id')
                    ->whereColumn('product_categories.category_id', 'categories.category_id')
                    ->where('products.is_active', '=', 1)
                    ->where('products.is_visible', '=', 1);

                if ($fulfillment === 'delivery') {
                    $q->join('vendors', 'vendors.vendor_id', '=', 'products.vendor_id')
                        ->where('products.delivery', '=', 1)
                        ->where('vendors.is_active', '=', 'active');
                    return;
                }

                $q->whereExists(function ($stockQuery) {
                    $stockQuery->selectRaw('1')
                        ->from('compartment_stock_products as csp')
                        ->join('compartment_stocks as cs', 'cs.compartment_stock_id', '=', 'csp.compartment_stock_id')
                        ->join('tender_compartments as tc', 'tc.tender_compartment_id', '=', 'cs.tender_compartment_id')
                        ->join('compartments as c', 'c.compartment_id', '=', 'tc.compartment_id')
                        ->join('racks as r', 'r.rack_id', '=', 'c.rack_id')
                        ->join('vendor_locations as vl', 'vl.id', '=', 'r.vendor_location_id')
                        ->join('vendors as v', 'v.vendor_id', '=', 'vl.vendor_id')
                        ->whereColumn('csp.product_id', 'products.product_id')
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
            'fulfillment' => ['nullable', 'in:pickup,delivery'],
        ]);

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
        $fulfillment = (string) ($validated['fulfillment'] ?? 'pickup');

        // 1. Single Query for Product + Active Vendor validation
        $product = DB::table('products as p')
            ->join('vendors as v', 'v.vendor_id', '=', 'p.vendor_id')
            ->where('p.product_id', $product_id)
            ->where('p.is_active', 1)
            ->where('p.is_visible', 1)
            ->where('v.is_active', 'active')
            ->select([
                'p.product_id',
                'p.vendor_id',
                'p.product_name',
                'p.product_description',
                'p.uom',
                'p.sale_price',
                'p.retail_price',
                'p.is_unlimited',
                'p.delivery',
                'p.requires_delivery_fee',
                'v.vendor_name',
            ])
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product or vendor not found.'], 404);
        }

        // 2. Fetch Images directly
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
                'image_url'        => $row->image_url ? (string) $row->image_url : null,
                'mobile_image_url' => $row->mobile_image_url ? (string) $row->mobile_image_url : null,
                'is_primary'       => (bool) $row->is_primary,
            ]);

        // 3. Conditional Location Fetching (Avoid unnecessary queries!)
        $pickupOptions = [];
        $deliveryLocations = [];

        if ($fulfillment === 'pickup') {
            $pickupOptions = $this->fetchPickupOptions($product, $latitude, $longitude, (string) $product->vendor_name);
        } else {
            $deliveryLocations = $this->fetchDeliveryLocations((string) $product->vendor_id, $latitude, $longitude, $product->product_id);
        }

        // Determine store/location focus
        $primaryOption = $fulfillment === 'pickup'
            ? ($pickupOptions[0] ?? null)
            : ($deliveryLocations[0] ?? null);

        $location = null;
        $distanceKm = null;

        if ($primaryOption) {
            $location = [
                'vendor_location_id' => $primaryOption['vendor_location_id'],
                'location_name'      => $primaryOption['vendor_location_name'] ?? $primaryOption['location_name'] ?? null,
                'city'               => $primaryOption['city'],
                'state'              => $primaryOption['state'],
                'latitude'           => $primaryOption['latitude'],
                'longitude'          => $primaryOption['longitude'],
                'address'            => $primaryOption['address'],
            ];
            $distanceKm = $primaryOption['distance_km'] ?? null;
        }

        // Price & Stock calculations
        $unitPrice = (float) ($product->sale_price > 0 ? $product->sale_price : $product->retail_price);

        // Sum stock without initializing Laravel Collections
        $stockQty = 0;
        foreach ($pickupOptions as $option) {
            $stockQty += (int) ($option['available_quantity'] ?? 0);
        }

        $isUnlimited = (bool) $product->is_unlimited;
        $isAvailable = $fulfillment === 'delivery'
            ? (bool) $product->delivery
            : ($isUnlimited || $stockQty > 0);

        return response()->json([
            'data' => [
                'product_id'          => (string) $product->product_id,
                'product_name'        => (string) $product->product_name,
                'product_description' => (string) ($product->product_description ?? ''),
                'uom'                 => (string) ($product->uom ?? 'unit'),
                'unit_price'          => round($unitPrice, 2),
                'stock_quantity'      => $stockQty,
                'is_unlimited'        => $isUnlimited,
                'is_available'        => $isAvailable,
                'vendor' => [
                    'vendor_id'   => (string) $product->vendor_id,
                    'vendor_name' => (string) $product->vendor_name,
                ],
                'store'              => $location,
                'distance_km'        => $distanceKm,
                'images'             => $images,
                'fulfillment'        => $fulfillment,
                'delivery'           => (bool) $product->delivery,
                'requires_delivery_fee' => (bool) ($product->requires_delivery_fee ?? true),
                'pickup_options'     => $pickupOptions,
                'delivery_locations' => $deliveryLocations,
            ],
        ]);
    }

    private function buildPickupProductsQuery(
        ?string $search,
        ?string $categoryId,
        ?string $vendorId,
        ?float $minPrice,
        ?float $maxPrice,
        float $latitude,
        float $longitude
    ) {
        // 1. Inlined distance expression (0 PDO bindings introduced)
        $distanceExpr = $this->distanceExpression('vl', $latitude, $longitude);

        $base = $this->validStockedProductsQuery();

        // 2. Apply bounding box (0 PDO bindings introduced when inlined)
        $this->applyBoundingBox($base, $latitude, $longitude, 50.0);

        // 3. Conditional filters using standard bindings
        $this->applyListingFilters($base, $search, $categoryId, $vendorId, $minPrice, $maxPrice);

        // 4. Collapse to one row per product *before* ordering and limiting. MIN() is
        // exact here because every wrapped column is constant for a given product, so
        // the query stays ONLY_FULL_GROUP_BY safe while letting the optimizer sort the
        // aggregated rows instead of materializing a wide derived table per row.
        // `sort_distance_km` is the nearest distance; the store shown to the user is
        // resolved separately by fetchListingStores().
        return $base
            ->groupBy('p.product_id')
            ->selectRaw('p.product_id')
            ->selectRaw('MIN(p.product_name) as product_name')
            ->selectRaw('MIN(v.vendor_id) as vendor_id')
            ->selectRaw('MIN(v.vendor_name) as vendor_name')
            ->selectRaw('MIN(primary_image.mobile_image_url) as mobile_image_url')
            ->selectRaw('MIN(primary_image.image_url) as image_url')
            ->selectRaw("MIN({$this->effectivePriceExpression('p')}) as unit_price")
            ->selectRaw("MIN({$distanceExpr}) as sort_distance_km")
            ->selectRaw('1 as is_available');
    }

    private function applyBoundingBox($query, float $lat, float $lng, float $radiusKm = 50.0)
    {
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * cos(deg2rad($lat)));

        $minLat = (float) ($lat - $latDelta);
        $maxLat = (float) ($lat + $latDelta);
        $minLng = (float) ($lng - $lngDelta);
        $maxLng = (float) ($lng + $lngDelta);

        // Using explicit bindings ensures values line up cleanly
        return $query->whereBetween('vl.latitude', [$minLat, $maxLat])
            ->whereBetween('vl.longitude', [$minLng, $maxLng]);
    }

    private function buildDeliveryProductsQuery(
        ?string $search,
        ?string $categoryId,
        ?string $vendorId,
        ?float $minPrice,
        ?float $maxPrice,
        float $latitude,
        float $longitude
    ) {
        $distanceExpr = $this->distanceExpression('vl', $latitude, $longitude);

        $base = DB::table('products as p')
            ->join('vendors as v', 'v.vendor_id', '=', 'p.vendor_id')
            ->join('vendor_locations as vl', 'vl.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('product_images as primary_image', function ($join) {
                $join->on('primary_image.product_id', '=', 'p.product_id')
                    ->where('primary_image.is_primary', '=', 1)
                    ->where('primary_image.is_active', '=', 1);
            })
            ->where('v.is_active', '=', 'active')
            ->where('p.is_active', '=', 1)
            ->where('p.is_visible', '=', 1)
            ->where('p.delivery', '=', 1);

        // Apply bounding box
        $this->applyBoundingBox($base, $latitude, $longitude, 50.0);

        // Filters
        $this->applyListingFilters($base, $search, $categoryId, $vendorId, $minPrice, $maxPrice);

        // Collapse to one row per product (see buildPickupProductsQuery for rationale).
        // A primary location is preferred when one exists for the product; otherwise the
        // nearest location wins. Both branches only aggregate the distance, so the
        // preference stays exact without a window function.
        return $base
            ->groupBy('p.product_id')
            ->selectRaw('p.product_id')
            ->selectRaw('MIN(p.product_name) as product_name')
            ->selectRaw('MIN(v.vendor_id) as vendor_id')
            ->selectRaw('MIN(v.vendor_name) as vendor_name')
            ->selectRaw('MIN(primary_image.mobile_image_url) as mobile_image_url')
            ->selectRaw('MIN(primary_image.image_url) as image_url')
            ->selectRaw("MIN({$this->effectivePriceExpression('p')}) as unit_price")
            ->selectRaw("COALESCE(MIN(CASE WHEN vl.is_primary = 1 THEN {$distanceExpr} END), MIN({$distanceExpr})) as sort_distance_km")
            ->selectRaw('1 as is_available');
    }

    private function applyListingFilters(
        $query,
        ?string $search,
        ?string $categoryId,
        ?string $vendorId,
        ?float $minPrice,
        ?float $maxPrice
    ): void {
        $effectivePriceExpr = $this->effectivePriceExpression('p');

        $query->when(!empty($vendorId), fn($q) => $q->where('v.vendor_id', '=', $vendorId))
            ->when(!empty($categoryId), function ($q) use ($categoryId) {
                $q->whereExists(function ($sq) use ($categoryId) {
                    $sq->selectRaw('1')
                        ->from('product_categories as pc')
                        ->whereColumn('pc.product_id', 'p.product_id')
                        ->where('pc.category_id', '=', $categoryId);
                });
            })
            ->when($minPrice !== null, fn($q) => $q->whereRaw("{$effectivePriceExpr} >= ?", [(float) $minPrice]))
            ->when($maxPrice !== null, fn($q) => $q->whereRaw("{$effectivePriceExpr} <= ?", [(float) $maxPrice]))
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('p.product_name', 'like', "%{$search}%")
                        ->orWhere('v.vendor_name', 'like', "%{$search}%")
                        ->orWhere('vl.location_name', 'like', "%{$search}%");
                });
            });
    }

    private function fetchPickupOptions(
        object $product,
        ?float $latitude,
        ?float $longitude,
        string $vendorName
    ): array {
        $hasCoordinates = $latitude !== null && $longitude !== null;

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

        // Re-enable distance logic conditionally
        if ($hasCoordinates) {
            $distanceExpr = $this->distanceExpression('vl', $latitude, $longitude);
            $query->selectRaw("{$distanceExpr} as distance_km");
        }

        // Sort by distance first (if coords given), then location name
        if ($hasCoordinates) {
            $query->orderBy('distance_km', 'asc');
        }

        $rows = $query->orderBy('vl.location_name')
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
                'compartment_stock_id'         => (string) $row->compartment_stock_id,
                'vendor_location_id'           => (int) $row->vendor_location_id,
                'vendor_location_name'         => (string) $row->vendor_location_name,
                'vendor_name'                  => $vendorName,
                'available_quantity'           => (int) $row->quantity,
                'address'                      => (string) ($row->address ?? ''),
                'city'                         => $city,
                'state'                        => $state,
                'latitude'                     => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude'                    => $row->longitude !== null ? (float) $row->longitude : null,
                'distance_km'                  => isset($row->distance_km) ? (float) $row->distance_km : null,
                'rack_id'                      => (string) $row->rack_id,
                'rack_name'                    => (string) $row->rack_name,
                'compartment_id'               => (string) $row->compartment_id,
                'compartment_name'             => (string) $row->compartment_name,
            ];
        })->all();
    }

    private function fetchDeliveryLocations(
        string $vendorId,
        ?float $latitude,
        ?float $longitude,
        string $productId,
        int $limit = 10
    ): array {
        $hasCoordinates = $latitude !== null && $longitude !== null;

        $query = DB::table('vendor_locations')
            ->leftJoin('product_inventories', 'vendor_locations.id', '=', 'product_inventories.vendor_location_id')
            ->where('vendor_id', $vendorId)
            ->where('product_inventories.product_id', $productId)
            ->where('product_inventories.quantity', '>', 0)
            ->select([
                'id as vendor_location_id',
                'location_name as vendor_location_name',
                'address',
                'latitude',
                'longitude',
                'is_primary',
                'product_inventories.quantity as available_quantity',
            ]);

        if ($hasCoordinates) {
            $distanceExpr = $this->distanceExpression('vendor_locations', $latitude, $longitude);
            $query->selectRaw("{$distanceExpr} as distance_km")
                // Standard columns in ORDER BY allow better query plan optimization
                ->orderBy('is_primary', 'desc')
                ->orderBy('distance_km', 'asc');
        } else {
            $query->orderBy('is_primary', 'desc')
                ->orderBy('location_name', 'asc');
        }

        // Limit output to avoid loading excessive store locations into the response
        $rows = $query->limit($limit)->get();

        return $rows->map(function ($row) {
            [$city, $state] = $this->extractCityState(
                (string) ($row->address ?? ''),
                (string) ($row->vendor_location_name ?? '')
            );

            return [
                'vendor_location_id'   => (int) $row->vendor_location_id,
                'vendor_location_name' => (string) $row->vendor_location_name,
                'address'              => (string) ($row->address ?? ''),
                'city'                 => $city,
                'state'                => $state,
                'latitude'             => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude'            => $row->longitude !== null ? (float) $row->longitude : null,
                'distance_km'          => isset($row->distance_km) ? (float) $row->distance_km : null,
                'is_primary'           => (bool) ($row->is_primary ?? false),
                'available_quantity'   => (int) $row->available_quantity,
            ];
        })->all(); // Direct array return without unnecessary ->values() call
    }

    /**
     * Resolve the single store (and its distance) shown for each product on the page.
     *
     * Runs one narrow query restricted to the page's product ids, so the paginated
     * listing query never has to rank every matching product/location pair.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, array{location_name: ?string, address: ?string, latitude: ?float, longitude: ?float, distance_km: ?float}>
     */
    private function fetchListingStores(
        array $productIds,
        string $fulfillment,
        float $latitude,
        float $longitude
    ): array {
        if (empty($productIds)) {
            return [];
        }

        $distanceExpr = $this->distanceExpression('vl', $latitude, $longitude);

        if ($fulfillment === 'delivery') {
            $query = DB::table('products as p')
                ->join('vendors as v', 'v.vendor_id', '=', 'p.vendor_id')
                ->join('vendor_locations as vl', 'vl.vendor_id', '=', 'v.vendor_id')
                ->whereIn('p.product_id', $productIds)
                ->where('v.is_active', '=', 'active')
                ->where('p.is_active', '=', 1)
                ->where('p.is_visible', '=', 1)
                ->where('p.delivery', '=', 1);

            $this->applyBoundingBox($query, $latitude, $longitude, 50.0);

            $query->select(['p.product_id', 'vl.location_name', 'vl.address', 'vl.latitude', 'vl.longitude'])
                ->selectRaw("{$distanceExpr} as distance_km")
                ->orderByRaw('CASE WHEN vl.is_primary = 1 THEN 0 ELSE 1 END asc')
                ->orderBy('distance_km', 'asc')
                ->orderBy('vl.location_name', 'asc');
        } else {
            $query = $this->validStockedProductsQuery()
                ->whereIn('csp.product_id', $productIds);

            $this->applyBoundingBox($query, $latitude, $longitude, 50.0);

            $query->select(['csp.product_id', 'vl.location_name', 'vl.address', 'vl.latitude', 'vl.longitude'])
                ->selectRaw("{$distanceExpr} as distance_km")
                ->orderBy('distance_km', 'asc')
                ->orderBy('vl.location_name', 'asc');
        }

        $stores = [];

        foreach ($query->get() as $row) {
            $key = (string) $row->product_id;

            // Rows arrive already ordered by the preferred store, so the first one wins.
            if (isset($stores[$key])) {
                continue;
            }

            $stores[$key] = [
                'location_name' => $row->location_name !== null ? (string) $row->location_name : null,
                'address'       => $row->address !== null ? (string) $row->address : null,
                'latitude'      => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude'     => $row->longitude !== null ? (float) $row->longitude : null,
                'distance_km'   => $row->distance_km !== null ? (float) $row->distance_km : null,
            ];
        }

        return $stores;
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
                    // Using on() for raw integer flags avoids extra PDO binding slots
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

        $query->where("{$stockAlias}.stock_status", '=', 'completed')
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

    private function distanceExpression(string $locationAlias, float $lat, float $lng): string
    {
        // Cast to float explicitly to prevent SQL injection, and inline the values.
        // This eliminates parameter binding misalignment completely!
        $lat = (float) $lat;
        $lng = (float) $lng;

        return "ST_Distance_Sphere(POINT({$lng}, {$lat}), POINT({$locationAlias}.longitude, {$locationAlias}.latitude)) / 1000";
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
