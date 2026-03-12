<?php

namespace App\Http\Controllers;

use App\Models\Vouchers;
use Illuminate\Http\Request;
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
        $query->join('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select('vouchers.*', 'vendors.vendor_name');

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
            'duration' => 'nullable|string|max:100',
            'what_you_get' => 'required|string',
            'voucher_code' => 'nullable|string|max:255',
            'voucher_discount' => 'nullable|numeric|min:0',
            'voucher_type' => 'nullable|string|max:100',
            'voucher_start_date' => 'required|date',
            'voucher_expiry_date' => 'required|date',
            'voucher_limit' => 'nullable|integer|min:0',
            'voucher_claim_per_user' => 'nullable|integer|min:1',
            'voucher_status' => 'nullable|boolean',
            'voucher_image' => 'nullable|image|max:4096',
        ]);

        if (empty($validated['voucher_code'])) {
            $validated['voucher_code'] = $this->generateVoucherCode();
        }
        $validated['voucher_start_date'] = date('Y-m-d', strtotime($validated['voucher_start_date']));
        $validated['voucher_expiry_date'] = date('Y-m-d', strtotime($validated['voucher_expiry_date']));

        $voucher = Vouchers::create($validated);

        if ($request->hasFile('voucher_image')) {
            $path = $request->file('voucher_image')->store("vouchers/{$voucher->voucher_id}", 'public');
            $voucher->update([
                'voucher_image_path' => Storage::url($path),
            ]);
        }

        return redirect()->route('vouchers.index')->with([
            'success' => 'Voucher created successfully',
        ]);
    }

    public function edit(Vouchers $voucher)
    {
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
            'duration' => 'nullable|string|max:100',
            'what_you_get' => 'required|string',
            'voucher_code' => 'sometimes|required|string|max:255',
            'voucher_discount' => 'nullable|numeric|min:0',
            'voucher_type' => 'nullable|string|max:100',
            'voucher_start_date' => 'required|date',
            'voucher_expiry_date' => 'required|date',
            'voucher_limit' => 'nullable|integer|min:0',
            'voucher_claim_per_user' => 'nullable|integer|min:1',
            'voucher_status' => 'nullable|boolean',
            'voucher_image' => 'nullable|image|max:4096',
        ]);

        $validated['voucher_start_date'] = date('Y-m-d', strtotime($validated['voucher_start_date']));
        $validated['voucher_expiry_date'] = date('Y-m-d', strtotime($validated['voucher_expiry_date']));

        $voucher->update($validated);

        if ($request->hasFile('voucher_image')) {
            if (!empty($voucher->voucher_image_path)) {
                $relative = ltrim(str_replace('/storage/', '', $voucher->voucher_image_path), '/');
                if ($relative !== $voucher->voucher_image_path) {
                    Storage::disk('public')->delete($relative);
                }
            }

            $path = $request->file('voucher_image')->store("vouchers/{$voucher->voucher_id}", 'public');
            $voucher->update([
                'voucher_image_path' => Storage::url($path),
            ]);
        }

        return redirect()->route('vouchers.index')->with([
            'success' => 'Voucher updated successfully',
        ]);
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
}
