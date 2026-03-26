<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVouchers;
use App\Models\Vendors;
use App\Models\Vouchers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VouchersController extends Controller
{
    public function vouchers(Request $request)
    {
        $userId = $request->user()?->user_id;
        $perPage = 10;

        $filter = $request->input('filter');
        $categoryIds = [];
        if (is_array($filter) && array_key_exists('categories', $filter)) {
            $categoryIds = $filter['categories'];
        } elseif ($request->has('categories')) {
            $categoryIds = $request->input('categories');
        }
        if (is_string($categoryIds)) {
            $categoryIds = array_filter(array_map('trim', explode(',', $categoryIds)));
        } elseif (!is_array($categoryIds)) {
            $categoryIds = [];
        } else {
            $categoryIds = array_filter(array_map('strval', $categoryIds));
        }

        $search = $request->input('search');
        $search = is_string($search) ? trim($search) : null;

        $vouchers = Vouchers::query()
            ->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select([
                'vouchers.voucher_id',
                'vouchers.voucher_name',
                'vouchers.voucher_short_description',
                'vouchers.voucher_image_path',
                'vendors.vendor_name as vendor_name',
            ])
            ->where('voucher_status', true)
            ->when($userId, function ($q) use ($userId) {
                $q->whereNotIn('voucher_id', function ($sq) use ($userId) {
                    $sq->select('voucher_id')
                        ->from('user_vouchers')
                        ->where('user_id', $userId);
                });
            })
            ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                $q->whereIn('voucher_id', function ($sq) use ($categoryIds) {
                    $sq->select('voucher_id')
                        ->from('voucher_categories')
                        ->whereIn('category_id', $categoryIds);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('voucher_name', 'like', "%{$search}%")
                        ->orWhere('voucher_short_description', 'like', "%{$search}%");
                });
            })
            ->orderBy('vouchers.created_at', 'desc')
            ->paginate($perPage);


        $items = $vouchers->items();
        return response()->json([
            'data' => empty($items) ? [] : $items,
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
                'next_page_url' => $vouchers->nextPageUrl(),
                'prev_page_url' => $vouchers->previousPageUrl(),
            ],
        ]);
    }

    public function voucher(Request $request, $voucher_id)
    {
        $voucher = Vouchers::query()
            ->where('voucher_id', $voucher_id)
            ->where('voucher_status', true)
            ->first();
        $voucher->vendor = Vendors::query()
            ->select(['vendor_name', 'profile_picture'])
            ->where('vendor_id', $voucher->vendor_id)
            ->first();
        $voucher->profile_picture = Storage::url($voucher->profile_picture);
        $voucherImages = $voucher->voucher_images ?? [];
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        return response()->json([
            'data' => $voucher,
            'voucher_images' => $voucherImages,
        ]);
    }

    public function myVouchers(Request $request)
    {
        $perPage = 10;
        $vouchers = UserVouchers::query()
            ->leftJoin('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.voucher_id')
            ->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select([
                'vouchers.voucher_id',
                'vouchers.voucher_name',
                'vouchers.voucher_short_description',
                'vouchers.voucher_image_path',
                'vendors.vendor_name as vendor_name',
            ])
            ->where('user_id', $request->user()->user_id)
            ->where('is_valid', true)
            ->orderBy('user_vouchers.created_at', 'desc')
            ->paginate($perPage);

        $items = $vouchers->items();
        return response()->json([
            'data' => empty($items) ? [] : $items,
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
                'next_page_url' => $vouchers->nextPageUrl(),
                'prev_page_url' => $vouchers->previousPageUrl(),
            ],
        ]);
    }

    public function claim(Request $request, $voucher_id)
    {
        $voucher = Vouchers::query()
            ->where('voucher_id', $voucher_id)
            ->where('voucher_status', true)
            ->first();
        $voucher->vendor = Vendors::query()
            ->select(['vendor_name', 'profile_picture'])
            ->where('vendor_id', $voucher->vendor_id)
            ->first();
        $voucher->profile_picture = Storage::url($voucher->profile_picture);
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        // Check if voucher is already redeemed
        $redeemHistory = UserVouchers::query()
            ->where('voucher_id', $voucher_id)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if ($redeemHistory) {
            Log::info(
                'Voucher already redeemed.',
                [
                    'voucher_id' => $voucher_id,
                    'user_id' => $request->user()->user_id,
                ]
            );
            return response()->json(['message' => 'Voucher already redeemed.'], 400);
        }

        // Check if voucher is expired
        if ($voucher->voucher_expiry_date < now()->toDateString()) {
            return response()->json(['message' => 'Voucher expired.'], 400);
        }

        // Create redeem history
        UserVouchers::create([
            'voucher_id' => $voucher_id,
            'user_id' => $request->user()->user_id,
        ]);

        return response()->json([
            'success' => 'Voucher claimed successfully.',
        ]);
    }

    public function history(Request $request, $voucher_id)
    {
        $history = UserVouchers::query()
            ->where('voucher_id', $voucher_id)
            ->get();

        return response()->json([
            'data' => $history,
        ]);
    }

    public function checkIfVoucherIsValid(Request $request, $voucher_id)
    {
        try {
            // voucher claim count
            $userClaimCount = UserVouchers::query()
                ->where('voucher_id', $voucher_id)
                ->where('user_id', $request->user()->user_id)
                ->count();

            $voucher = Vouchers::query()
                ->where('voucher_id', $voucher_id)
                ->select('is_unlimited', 'voucher_limit', 'voucher_claim_per_user')
                ->first();

            // if unlimited and user claim count is less than voucher limit, return true
            if ($voucher->is_unlimited) {
                if ($userClaimCount < $voucher->voucher_claim_per_user) {
                    return response()->json([
                        'data' => [
                            'is_valid' => true,
                        ],
                    ]);
                }
            } else {
                // voucher is not unlimited, get total voucher claim count
                $totalVoucherClaim = UserVouchers::query()
                    ->where('voucher_id', $voucher_id)
                    ->count();

                if ($totalVoucherClaim < $voucher->voucher_limit && $userClaimCount < $voucher->voucher_claim_per_user) {
                    return response()->json([
                        'data' => [
                            'is_valid' => true,
                        ],
                    ]);
                } else {
                    return response()->json([
                        'data' => [
                            'is_valid' => false,
                        ],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error(
                'Error checking voucher validity.',
                [
                    'voucher_id' => $voucher_id,
                    'user_id' => $request->user()->user_id,
                    'error' => $e->getMessage(),
                ]
            );
            return response()->json(['message' => 'Error checking voucher validity.'], 500);
        }
        // return response()->json([
        //     'data' => [
        //         'is_valid' => false,
        //     ],
        // ]);
    }
}
