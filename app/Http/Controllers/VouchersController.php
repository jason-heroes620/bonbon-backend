<?php

namespace App\Http\Controllers;

use App\Models\VoucherImages;
use App\Models\VoucherCategories;
use App\Models\VoucherMemberships;
use App\Models\VoucherProducts;
use App\Models\Vouchers;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VouchersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('vouchers/vouchers');
    }

    public function showAll(Request $request)
    {
        $query = Vouchers::query();
        $query->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select('vouchers.*', 'vendors.vendor_name');

        $user = $request->user();
        if ($user && $user->role === 'vendor') {
            $query->where('vendors.user_id', $user->user_id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('voucher_name', 'like', "%{$search}%")
                    ->orWhere('vendors.vendor_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('filters')) {
            foreach ($request->filters as $column => $value) {
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
        }
        if ($request->has('sort')) {
            $query->orderBy($request->sort['field'], $request->sort['direction']);
        } else {
            $query->orderBy('vouchers.created_at', 'desc');
        }
        $perPage = $request->per_page ?? 10;
        $vouchers = $query->paginate($perPage);

        return response()->json([
            'data' => $vouchers->items(),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'vendor'], true)) {
            abort(403);
        }

        $query = Vouchers::query()
            ->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select([
                'vendors.vendor_name',
                'vouchers.voucher_name',
                'vouchers.voucher_value',
                'vouchers.voucher_description',
                'vouchers.tnc',
                'vouchers.voucher_expiry_date',
                'vouchers.created_at',
            ])
            ->where('vouchers.voucher_expiry_date', '>=', now())
            ->where('vouchers.voucher_status', true)
            ->orderByDesc('vouchers.created_at');

        if ($user->role === 'vendor') {
            $query->where('vendors.user_id', $user->user_id);
        }

        $rows = $query->get();

        $filename = 'vouchers_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'vendor_name',
                'voucher_name',
                'voucher_value',
                'voucher_description',
                'tnc',
                'voucher_expiry_date',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row->voucher_name ?? ''),
                    (string) ($row->voucher_value ?? ''),
                    $this->cleanHtmlForExcel($row->voucher_description ?? null),
                    $this->cleanHtmlForExcel($row->tnc ?? null),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('vouchers/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|uuid',
            'voucher_name' => 'required|string|max:200',
            'voucher_short_description' => 'nullable|string|max:100',
            'voucher_description' => 'required|string',
            'voucher_value' => 'nullable|string|max:200',
            'what_you_get' => 'required|string',
            'voucher_code' => 'nullable|string|max:255',
            'voucher_discount' => 'nullable|numeric|min:0',
            'voucher_start_date' => 'required|date',
            'voucher_expiry_date' => 'required|date',
            'voucher_limit' => 'nullable|integer|min:0',
            'voucher_claim_per_user' => 'nullable|integer|min:1',
            'voucher_claim_period' => 'nullable|string|in:week,month',
            'voucher_claim_per_period' => 'nullable|integer|min:1',
            'membership_ids_present' => 'nullable|boolean',
            'membership_ids' => 'nullable|array',
            'membership_ids.*' => 'uuid|exists:memberships,membership_id',
            'applicable_product_ids' => 'nullable|array',
            'applicable_product_ids.*' => 'uuid|exists:products,product_id',
            'categories' => 'required|array',
            'categories.*' => 'uuid|exists:categories,category_id',
            'voucher_status' => 'nullable|boolean',
            'voucher_image' => 'nullable|image|max:4096',
            'voucher_images' => 'nullable|array',
            'voucher_images.*' => 'image|max:4096',
            'is_unlimited' => 'nullable|boolean',
            'tnc' => 'nullable|string',
            'how_to_use' => 'nullable|string',
            'voucher_discount_type' => 'nullable|string|in:F,P',
            'min_purchase' => 'nullable|numeric',
        ]);

        $user = $request->user();
        if ($user && $user->role === 'vendor') {
            $ownsVendor = \App\Models\Vendors::query()
                ->where('vendor_id', $validated['vendor_id'])
                ->where('user_id', $user->user_id)
                ->exists();
            if (!$ownsVendor) {
                abort(403);
            }
        }

        $applicableProductIds = $this->normalizeUuidArray($validated['applicable_product_ids'] ?? []);
        $this->assertVoucherProductsBelongToVendor($validated['vendor_id'], $applicableProductIds);

        if (empty($validated['voucher_code'])) {
            $validated['voucher_code'] = $this->generateVoucherCode();
        }
        $validated['voucher_start_date'] = date('Y-m-d', strtotime($validated['voucher_start_date']));
        $validated['voucher_expiry_date'] = date('Y-m-d', strtotime($validated['voucher_expiry_date']));

        $voucher = Vouchers::create($validated);

        if (array_key_exists('membership_ids_present', $validated)) {
            $membershipIds = $validated['membership_ids'] ?? [];
            if (!is_array($membershipIds)) {
                $membershipIds = [];
            }
            $membershipIds = array_values(array_unique(array_map('strval', $membershipIds)));

            if (!empty($membershipIds)) {
                VoucherMemberships::insert(
                    array_map(
                        fn($membershipId) => [
                            'voucher_id' => $voucher->voucher_id,
                            'membership_id' => $membershipId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        $membershipIds
                    )
                );
            }
        }

        if ($request->has('categories')) {
            foreach ((array) $request->categories as $category) {
                VoucherCategories::create([
                    'voucher_id' => $voucher->voucher_id,
                    'category_id' => $category,
                ]);
            }
        }

        $this->syncApplicableProducts($voucher->voucher_id, $applicableProductIds);

        if ($request->hasFile('voucher_image')) {
            $path = $request->file('voucher_image')->store("vouchers/{$voucher->voucher_id}", 'public');
            $voucher->update([
                'voucher_image_path' => Storage::url($path),
            ]);
        }

        if ($request->hasFile('voucher_images')) {
            foreach ((array) $request->file('voucher_images') as $image) {
                if (!$image) {
                    continue;
                }
                $path = $image->store("vouchers/{$voucher->voucher_id}/images", 'public');
                VoucherImages::create([
                    'voucher_id' => $voucher->voucher_id,
                    'voucher_image_path' => Storage::url($path),
                ]);
            }
        }

        return redirect()->route('vouchers.index')->with([
            'success' => 'Voucher created successfully',
        ]);
    }

    public function edit(Vouchers $voucher)
    {
        $user = request()->user();
        if ($user && $user->role === 'vendor') {
            $ownsVoucher = \App\Models\Vendors::query()
                ->where('vendor_id', $voucher->vendor_id)
                ->where('user_id', $user->user_id)
                ->exists();
            if (!$ownsVoucher) {
                abort(403);
            }
        }

        $voucherImages = VoucherImages::query()
            ->where('voucher_id', $voucher->voucher_id)
            ->orderBy('created_at', 'desc')
            ->get(['voucher_image_id', 'voucher_image_path']);

        $voucher->setAttribute('voucher_images', $voucherImages);
        $voucher->setAttribute(
            'categories',
            VoucherCategories::query()
                ->where('voucher_id', $voucher->voucher_id)
                ->pluck('category_id')
                ->toArray(),
        );
        $voucher->setAttribute(
            'membership_ids',
            DB::table('voucher_memberships')
                ->where('voucher_id', $voucher->voucher_id)
                ->pluck('membership_id')
                ->toArray(),
        );
        $voucher->setAttribute(
            'applicable_product_ids',
            VoucherProducts::query()
                ->where('voucher_id', $voucher->voucher_id)
                ->pluck('product_id')
                ->toArray(),
        );

        return Inertia::render('vouchers/edit', [
            'voucher' => $voucher,
        ]);
    }

    public function update(Request $request, Vouchers $voucher)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|uuid',
            'voucher_name' => 'required|string|max:200',
            'voucher_short_description' => 'nullable|string|max:100',
            'voucher_description' => 'required|string',
            'voucher_value' => 'nullable|string|max:200',
            'what_you_get' => 'required|string',
            'voucher_code' => 'sometimes|required|string|max:255',
            'voucher_discount' => 'nullable|numeric|min:0',
            'voucher_start_date' => 'required|date',
            'voucher_expiry_date' => 'required|date',
            'voucher_limit' => 'nullable|integer|min:0',
            'voucher_claim_per_user' => 'nullable|integer|min:1',
            'voucher_claim_period' => 'nullable|string|in:week,month',
            'voucher_claim_per_period' => 'nullable|integer|min:1',
            'membership_ids_present' => 'nullable|boolean',
            'membership_ids' => 'nullable|array',
            'membership_ids.*' => 'uuid|exists:memberships,membership_id',
            'applicable_product_ids' => 'nullable|array',
            'applicable_product_ids.*' => 'uuid|exists:products,product_id',
            'categories' => 'required|array',
            'categories.*' => 'uuid|exists:categories,category_id',
            'voucher_status' => 'nullable|boolean',
            'voucher_image' => 'nullable|image|max:4096',
            'voucher_images' => 'nullable|array',
            'voucher_images.*' => 'image|max:4096',
            'delete_voucher_image_ids' => 'nullable|array',
            'delete_voucher_image_ids.*' => 'integer',
            'is_unlimited' => 'nullable|boolean',
            'tnc' => 'nullable|string',
            'how_to_use' => 'nullable|string',
            'voucher_discount_type' => 'nullable|string|in:F,P',
            'min_purchase' => 'nullable|numeric',
        ]);

        $user = $request->user();
        if ($user && $user->role === 'vendor') {
            $ownsVendor = \App\Models\Vendors::query()
                ->where('vendor_id', $validated['vendor_id'])
                ->where('user_id', $user->user_id)
                ->exists();
            if (!$ownsVendor) {
                abort(403);
            }
        }

        $applicableProductIds = $this->normalizeUuidArray($validated['applicable_product_ids'] ?? []);
        $this->assertVoucherProductsBelongToVendor($validated['vendor_id'], $applicableProductIds);

        $validated['voucher_start_date'] = date('Y-m-d', strtotime($validated['voucher_start_date']));
        $validated['voucher_expiry_date'] = date('Y-m-d', strtotime($validated['voucher_expiry_date']));

        $voucher->update($validated);

        if (array_key_exists('membership_ids_present', $validated)) {
            $membershipIds = $validated['membership_ids'] ?? [];
            if (!is_array($membershipIds)) {
                $membershipIds = [];
            }
            $membershipIds = array_values(array_unique(array_map('strval', $membershipIds)));

            DB::table('voucher_memberships')->where('voucher_id', $voucher->voucher_id)->delete();

            if (!empty($membershipIds)) {
                DB::table('voucher_memberships')->insert(
                    array_map(
                        fn($membershipId) => [
                            'voucher_id' => $voucher->voucher_id,
                            'membership_id' => $membershipId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        $membershipIds
                    )
                );
            }
        }

        if ($request->has('categories')) {
            VoucherCategories::query()->where('voucher_id', $voucher->voucher_id)->delete();
            foreach ((array) $request->categories as $category) {
                VoucherCategories::create([
                    'voucher_id' => $voucher->voucher_id,
                    'category_id' => $category,
                ]);
            }
        }

        $this->syncApplicableProducts($voucher->voucher_id, $applicableProductIds);

        if ($request->hasFile('voucher_image')) {
            if (!empty($voucher->voucher_image_path)) {
                $this->deletePublicUrlFile($voucher->voucher_image_path);
            }

            $path = $request->file('voucher_image')->store("vouchers/{$voucher->voucher_id}", 'public');
            $voucher->update([
                'voucher_image_path' => Storage::url($path),
            ]);
        }

        $deleteIds = $request->input('delete_voucher_image_ids');
        if (is_array($deleteIds) && !empty($deleteIds)) {
            $imagesToDelete = VoucherImages::query()
                ->where('voucher_id', $voucher->voucher_id)
                ->whereIn('voucher_image_id', $deleteIds)
                ->get();

            foreach ($imagesToDelete as $image) {
                $this->deletePublicUrlFile($image->voucher_image_path);
                $image->delete();
            }
        }

        if ($request->hasFile('voucher_images')) {
            foreach ((array) $request->file('voucher_images') as $image) {
                if (!$image) {
                    continue;
                }
                $path = $image->store("vouchers/{$voucher->voucher_id}/images", 'public');
                VoucherImages::create([
                    'voucher_id' => $voucher->voucher_id,
                    'voucher_image_path' => Storage::url($path),
                ]);
            }
        }

        return redirect()->route('vouchers.index')->with([
            'success' => 'Voucher updated successfully',
        ]);
    }

    private function deletePublicUrlFile(?string $url): void
    {
        if (!$url) {
            return;
        }
        $relative = ltrim(str_replace('/storage/', '', $url), '/');
        if ($relative !== $url) {
            Storage::disk('public')->delete($relative);
        }
    }

    private function generateVoucherCode()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }

    private function cleanHtmlForExcel(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $value = trim((string) $html);
        if ($value === '') {
            return '';
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $value);
        $value = preg_replace('/<\s*\/\s*p\s*>/i', "\n", $value);
        $value = preg_replace('/<\s*\/\s*div\s*>/i', "\n", $value);
        $value = preg_replace('/<\s*\/\s*h[1-6]\s*>/i', "\n", $value);
        $value = preg_replace('/<\s*li(\s+[^>]*)?>/i', "- ", $value);
        $value = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $value);

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace("/[ \t]+/", ' ', $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value);
    }

    private function normalizeUuidArray($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $values)));
    }

    private function assertVoucherProductsBelongToVendor(string $vendorId, array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $matchingCount = Products::query()
            ->whereIn('product_id', $productIds)
            ->where('vendor_id', $vendorId)
            ->count();

        if ($matchingCount !== count($productIds)) {
            abort(422, 'Applicable products must belong to the selected vendor.');
        }
    }

    private function syncApplicableProducts(string $voucherId, array $productIds): void
    {
        VoucherProducts::query()
            ->where('voucher_id', $voucherId)
            ->delete();

        if (empty($productIds)) {
            return;
        }

        VoucherProducts::insert(
            array_map(
                fn(string $productId) => [
                    'voucher_product_id' => (string) Str::uuid(),
                    'voucher_id' => $voucherId,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $productIds,
            ),
        );
    }
}
