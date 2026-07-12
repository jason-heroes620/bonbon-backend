<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\ProductImages;
use App\Models\Products;
use App\Models\Taxes;
use App\Models\Vendors;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductsController extends Controller
{
    public function index()
    {
        return Inertia::render('products/products');
    }

    public function showAll(Request $request)
    {
        // if role is vendor, filter by vendor_id
        $query = Products::query()
            ->with('primaryImage')
            ->withCount([
                'pricingTiers as active_pricing_tiers_count' => function ($q) {
                    $q->where('is_active', true);
                },
            ]);

        if ($request->user()->role === 'vendor') {
            // get vendor_id from user
            $vendorId = Vendors::query()->where('user_id', $request->user()->user_id)->value('vendor_id');
            $query = $query->where('vendor_id', $vendorId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('product_sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($column, $value);
                }
            }
        }

        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('products.created_at', 'desc');
        }

        $perPage = $request->per_page ?? 10;
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        $taxRates = Taxes::select('tax_rate_id as value', 'tax_name as label')
            ->where('is_active', true)
            ->orderBy('tax_name', 'asc')
            ->get();

        return Inertia::render('products/create', [
            'categories' => $categories,
            'taxRates' => $taxRates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,category_id'],
            'product_name' => ['required', 'string', 'max:150'],
            'product_code' => ['nullable', 'string', 'max:50'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'product_description' => ['required', 'string'],
            'uom' => ['nullable', 'string', 'max:50'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_weight' => ['nullable', 'numeric', 'min:0'],
            'product_dimensions' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'is_taxable' => ['required', 'boolean'],
            'tax_rate_id' => ['required', 'uuid'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_unlimited' => ['required', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:ratio=1/1,max_width=2048,max_height=2048'],
        ]);

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['images']);

        $primaryCategoryId = count($categoryIds) > 0 ? $categoryIds[0] : null;
        $validated['category_id'] = $primaryCategoryId;

        // if user role is a vendor, add vendor_id to validated
        if ($request->user()->role === 'vendor') {
            // get vendor_id from user
            $vendorId = Vendors::query()->where('user_id', $request->user()->user_id)->value('vendor_id');
            $validated['vendor_id'] = $vendorId;
        }

        $product = null;

        DB::transaction(function () use ($validated, $categoryIds, $request, &$product) {
            $product = Products::create($validated);
            $product->categories()->sync($categoryIds);
            $this->syncProductImages($request, $product);
        });

        return redirect()->route('products.index')->with([
            'success' => 'Product created successfully',
        ]);
    }

    public function edit(Products $product)
    {
        $categories = Categories::select('category_id as value', 'category_name as label')
            ->where('is_active', true)
            ->orderBy('category_name', 'asc')
            ->get();

        $taxRates = Taxes::select('tax_rate_id as value', 'tax_name as label')
            ->where('is_active', true)
            ->orderBy('tax_name', 'asc')
            ->get();

        return Inertia::render('products/edit', [
            'product' => $product->load([
                'images' => fn($q) => $q
                    ->where('is_active', true)
                    ->orderByDesc('is_primary')
                    ->orderBy('created_at'),
            ]),
            'categories' => $categories,
            'taxRates' => $taxRates,
            'selectedCategoryIds' => $product->categories()->pluck('categories.category_id')->toArray(),
        ]);
    }

    public function update(Request $request, Products $product)
    {
        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,category_id'],
            'product_name' => ['required', 'string', 'max:150'],
            'product_code' => ['nullable', 'string', 'max:50'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'uom' => ['nullable', 'string', 'max:50'],
            'product_description' => ['required', 'string'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_weight' => ['nullable', 'numeric', 'min:0'],
            'product_dimensions' => ['nullable', 'string', 'max:100'],
            'is_featured' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'is_taxable' => ['required', 'boolean'],
            'tax_rate_id' => ['required', 'uuid'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_unlimited' => ['required', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:ratio=1/1,max_width=2048,max_height=2048'],
            'removed_image_ids' => ['nullable', 'array'],
            'removed_image_ids.*' => ['uuid'],
        ]);

        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['images'], $validated['removed_image_ids']);

        $primaryCategoryId = count($categoryIds) > 0 ? $categoryIds[0] : null;
        $validated['category_id'] = $primaryCategoryId;

        DB::transaction(function () use ($validated, $categoryIds, $request, $product) {
            $product->update($validated);
            $product->categories()->sync($categoryIds);
            $this->syncProductImages($request, $product, $request->input('removed_image_ids', []));
        });

        return redirect()->route('products.index')->with([
            'success' => 'Product updated successfully',
        ]);
    }

    public function destroy(Products $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with([
            'success' => 'Product deleted successfully',
        ]);
    }

    public function getProductList()
    {
        $products = Products::query()
            ->select('product_id', 'product_name', 'product_code', 'product_sku')
            ->where('is_active', true)
            ->orderBy('product_name', 'asc')
            ->get()
            ->map(function ($p) {
                $parts = [$p->product_name];
                $meta = array_values(array_filter([$p->product_code, $p->product_sku]));
                if (!empty($meta)) {
                    $parts[] = '(' . implode(' / ', $meta) . ')';
                }
                return [
                    'value' => $p->product_id,
                    'label' => implode(' ', $parts),
                ];
            });

        return response()->json($products);
    }

    private function syncProductImages(Request $request, Products $product, array $removedImageIds = []): void
    {
        $activeImages = $product->images()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        if (!empty($removedImageIds)) {
            $imagesToRemove = $product->images()
                ->whereIn('product_image_id', $removedImageIds)
                ->get();

            foreach ($imagesToRemove as $image) {
                $this->deleteProductImageFiles($image);
                $image->delete();
            }

            $activeImages = $activeImages
                ->reject(fn($image) => in_array($image->product_image_id, $removedImageIds, true))
                ->values();
        }

        $uploads = $request->file('images', []);
        foreach ($uploads as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $stored = $this->storeProductImage(
                $product,
                $file,
                $activeImages->isEmpty() && $index === 0,
            );
            $activeImages->push($stored);
        }

        if ($activeImages->isNotEmpty() && !$activeImages->contains(fn($image) => (bool) $image->is_primary)) {
            $firstImage = $activeImages->first();
            $product->images()->where('product_image_id', $firstImage->product_image_id)->update([
                'is_primary' => true,
            ]);
        }
    }

    private function deleteProductImageFiles(ProductImages $image): void
    {
        $paths = array_filter([
            $image->image_path,
            $image->mobile_image_path,
        ]);

        if (!empty($paths)) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function storeProductImage(Products $product, UploadedFile $file, bool $isPrimary): ProductImages
    {
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $originalPath = "products/{$product->product_id}/original/{$uuid}.{$extension}";

        Storage::disk('public')->putFileAs(
            "products/{$product->product_id}/original",
            $file,
            "{$uuid}.{$extension}",
        );

        [$width, $height] = array_pad((array) getimagesize($file->getRealPath()), 2, null);
        $optimized = $this->buildMobileOptimizedImage($file);
        $mobilePath = "products/{$product->product_id}/mobile/{$uuid}.{$optimized['extension']}";
        Storage::disk('public')->put($mobilePath, $optimized['binary']);

        return ProductImages::create([
            'product_id' => $product->product_id,
            'image_url' => Storage::url($originalPath),
            'image_path' => $originalPath,
            'mobile_image_url' => Storage::url($mobilePath),
            'mobile_image_path' => $mobilePath,
            'is_active' => true,
            'is_primary' => $isPrimary,
            'image_width' => $width ? (int) $width : null,
            'image_height' => $height ? (int) $height : null,
            'file_size_bytes' => $file->getSize(),
            'mobile_file_size_bytes' => strlen($optimized['binary']),
        ]);
    }

    private function buildMobileOptimizedImage(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new \RuntimeException('Unable to access uploaded image.');
        }

        $imageInfo = getimagesize($realPath);
        if ($imageInfo === false) {
            throw new \RuntimeException('Unable to read uploaded image.');
        }

        [$srcWidth, $srcHeight] = $imageInfo;
        $mime = (string) ($imageInfo['mime'] ?? '');
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($realPath),
            'image/png' => imagecreatefrompng($realPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($realPath) : false,
            default => false,
        };

        if ($source === false) {
            throw new \RuntimeException('Unsupported product image format.');
        }

        $bestBinary = null;
        $bestExtension = function_exists('imagewebp') ? 'webp' : 'jpg';
        $maxBytes = 200 * 2048;
        $scaleSteps = [1, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4];
        $qualitySteps = function_exists('imagewebp')
            ? [82, 76, 70, 64, 58, 52, 46]
            : [82, 76, 70, 64, 58, 52, 46];

        foreach ($scaleSteps as $scale) {
            $targetWidth = max(1, (int) round($srcWidth * $scale));
            $targetHeight = max(1, (int) round($srcHeight * $scale));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $srcWidth,
                $srcHeight,
            );

            foreach ($qualitySteps as $quality) {
                ob_start();
                if (function_exists('imagewebp')) {
                    imagewebp($canvas, null, $quality);
                    $extension = 'webp';
                } else {
                    $white = imagecreatetruecolor($targetWidth, $targetHeight);
                    $background = imagecolorallocate($white, 255, 255, 255);
                    imagefilledrectangle($white, 0, 0, $targetWidth, $targetHeight, $background);
                    imagecopy($white, $canvas, 0, 0, 0, 0, $targetWidth, $targetHeight);
                    imagejpeg($white, null, $quality);
                    imagedestroy($white);
                    $extension = 'jpg';
                }
                $binary = (string) ob_get_clean();

                if ($bestBinary === null || strlen($binary) < strlen($bestBinary)) {
                    $bestBinary = $binary;
                    $bestExtension = $extension;
                }

                if (strlen($binary) <= $maxBytes) {
                    imagedestroy($canvas);
                    imagedestroy($source);

                    return [
                        'binary' => $binary,
                        'extension' => $extension,
                    ];
                }
            }

            imagedestroy($canvas);
        }

        imagedestroy($source);

        return [
            'binary' => (string) $bestBinary,
            'extension' => $bestExtension,
        ];
    }
}
