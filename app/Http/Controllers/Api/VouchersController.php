<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVoucherClaims;
use App\Models\UserVouchers;
use App\Models\User;
use App\Models\UserMemberships;
use App\Models\Vendors;
use App\Models\Vouchers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VouchersController extends Controller
{
    private function getActiveMembershipId(?string $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        return UserMemberships::query()
            ->where('user_id', $userId)
            ->where('membership_status', 'active')
            ->orderByDesc('membership_end_date')
            ->value('membership_id');
    }

    private function canUserAccessVoucher(string $voucherId, ?string $membershipId): bool
    {
        if (
            $membershipId && DB::table('voucher_memberships')
            ->where('voucher_id', $voucherId)
            ->where('membership_id', $membershipId)
            ->exists()
        ) {
            return true;
        }

        $hasRestrictions = DB::table('voucher_memberships')
            ->where('voucher_id', $voucherId)
            ->exists();

        return !$hasRestrictions;
    }

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

        $isExclusiveCase = "CASE
            WHEN NOT EXISTS (
                SELECT 1
                FROM voucher_memberships vm
                WHERE vm.voucher_id = vouchers.voucher_id
            ) THEN 0
            WHEN EXISTS (
                SELECT 1
                FROM voucher_memberships vm
                JOIN memberships m ON m.membership_id = vm.membership_id
                WHERE vm.voucher_id = vouchers.voucher_id
                  AND UPPER(m.membership_code) = 'MEMFREE'
            ) THEN 0
            ELSE 1
        END";

        $vouchers = Vouchers::query()
            ->leftJoin('vendors', 'vouchers.vendor_id', '=', 'vendors.vendor_id')
            ->select([
                'vouchers.voucher_id',
                'vouchers.voucher_name',
                'vouchers.voucher_short_description',
                'vouchers.voucher_image_path',
                'vendors.vendor_name as vendor_name',
            ])
            ->selectRaw("{$isExclusiveCase} as is_exclusive")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY ({$isExclusiveCase}) ORDER BY vouchers.created_at DESC) as is_exclusive_rank")
            ->where('voucher_status', true)
            ->where('voucher_expiry_date', '>=', today())
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
                        ->orWhere('voucher_short_description', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('is_exclusive_rank', 'asc')
            ->orderByDesc('is_exclusive')
            ->orderBy('vouchers.created_at', 'desc')
            ->paginate($perPage);


        $items = array_map(function ($item) {
            $item->is_exclusive = (bool) $item->is_exclusive;
            return $item;
        }, $vouchers->items());
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
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        // $membershipId = $this->getActiveMembershipId($request->user()?->user_id);
        // if (!$this->canUserAccessVoucher((string) $voucher_id, $membershipId)) {
        //     return response()->json(['message' => 'Voucher not available for your membership.'], 403);
        // }

        $voucher->vendor = Vendors::query()
            ->select(['vendor_name', 'profile_picture'])
            ->where('vendor_id', $voucher->vendor_id)
            ->first();
        $voucher->profile_picture = Storage::url($voucher->profile_picture);
        $voucherImages = $voucher->voucher_images ?? [];

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
            ->where('user_vouchers.user_id', $request->user()->user_id)
            ->where('is_valid', true)
            ->where('voucher_status', true)
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
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        $membershipId = $this->getActiveMembershipId($request->user()?->user_id);
        if (!$this->canUserAccessVoucher((string) $voucher_id, $membershipId)) {
            return response()->json(['message' => 'Voucher not available for your membership.'], 403);
        }

        $voucher->vendor = Vendors::query()
            ->select(['vendor_name', 'profile_picture'])
            ->where('vendor_id', $voucher->vendor_id)
            ->first();
        $voucher->profile_picture = Storage::url($voucher->profile_picture);

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
        UserVouchers::firstOrCreate([
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
            $membershipId = $this->getActiveMembershipId($request->user()?->user_id);
            if (!$this->canUserAccessVoucher((string) $voucher_id, $membershipId)) {
                return response()->json([
                    'data' => [
                        'is_valid' => false,
                    ],
                ]);
            }

            // voucher claim count
            $userRedeemCount = UserVoucherClaims::query()
                ->leftJoin('user_vouchers', 'user_voucher_claims.user_voucher_id', '=', 'user_vouchers.user_voucher_id')
                ->where('user_vouchers.voucher_id', $voucher_id)
                ->where('user_id', $request->user()->user_id)
                ->count();

            $voucher = Vouchers::query()
                ->where('voucher_id', $voucher_id)
                ->select('is_unlimited', 'voucher_limit', 'voucher_claim_per_user', 'voucher_claim_period', 'voucher_claim_per_period')
                ->first();

            $periodValid = true;
            if (!empty($voucher->voucher_claim_period) && !empty($voucher->voucher_claim_per_period)) {
                $start = null;
                if ($voucher->voucher_claim_period === 'week') {
                    $start = now()->startOfWeek();
                } elseif ($voucher->voucher_claim_period === 'month') {
                    $start = now()->startOfMonth();
                }
                if ($start) {
                    $userPeriodRedeemCount = UserVoucherClaims::query()
                        ->leftJoin('user_vouchers', 'user_voucher_claims.user_voucher_id', '=', 'user_vouchers.user_voucher_id')
                        ->where('user_vouchers.voucher_id', $voucher_id)
                        ->where('user_vouchers.user_id', $request->user()->user_id)
                        ->where('user_voucher_claims.created_at', '>=', $start)
                        ->count();
                    $periodValid = $userPeriodRedeemCount < (int) $voucher->voucher_claim_per_period;
                }
            }

            // if unlimited and user claim count is less than voucher limit, return true
            if ($voucher->is_unlimited) {
                if ($userRedeemCount < $voucher->voucher_claim_per_user && $periodValid) {
                    return response()->json([
                        'data' => [
                            'is_valid' => true,
                        ],
                    ]);
                }
            } else {
                // voucher is not unlimited, get total voucher claim count
                $totalVoucherRedeemCount = UserVoucherClaims::query()
                    ->leftJoin('user_vouchers', 'user_voucher_claims.user_voucher_id', '=', 'user_vouchers.user_voucher_id')
                    ->where('user_vouchers.voucher_id', $voucher_id)
                    ->count();

                if ($totalVoucherRedeemCount < $voucher->voucher_limit && $userRedeemCount < $voucher->voucher_claim_per_user && $periodValid) {
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
    }

    private function getVendorIds(Request $request): array
    {
        $userId = $request->user()?->user_id;
        if (!$userId) {
            return [];
        }

        return Vendors::query()
            ->where('user_id', $userId)
            ->pluck('vendor_id')
            ->map(fn($id) => (string) $id)
            ->values()
            ->all();
    }

    public function merchantVouchers(Request $request)
    {
        $vendorIds = $this->getVendorIds($request);
        if ($vendorIds === []) {
            return response()->json([
                'message' => 'Vendor not found.',
            ], 404);
        }

        $since = now()->subMonth();

        $totalVouchers = Vouchers::query()
            ->whereIn('vendor_id', $vendorIds)
            ->count();

        $totalClaimed = UserVouchers::query()
            ->join('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.voucher_id')
            ->whereIn('vouchers.vendor_id', $vendorIds)
            ->count();

        $totalRedeemed = UserVoucherClaims::query()
            ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
            ->join('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.voucher_id')
            ->whereIn('vouchers.vendor_id', $vendorIds)
            ->count();

        $recentClaims = UserVouchers::query()
            ->join('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.voucher_id')
            ->leftJoin('users', 'user_vouchers.user_id', '=', 'users.user_id')
            ->whereIn('vouchers.vendor_id', $vendorIds)
            ->where('user_vouchers.created_at', '>=', $since)
            ->orderByDesc('user_vouchers.created_at')
            ->get([
                'user_vouchers.user_voucher_id',
                'user_vouchers.voucher_id',
                'vouchers.voucher_name',
                'user_vouchers.user_id',
                'users.email as user_email',
                'user_vouchers.created_at',
            ]);

        $recentRedemptions = UserVoucherClaims::query()
            ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
            ->join('vouchers', 'user_vouchers.voucher_id', '=', 'vouchers.voucher_id')
            ->whereIn('vouchers.vendor_id', $vendorIds)
            ->where('user_voucher_claims.created_at', '>=', $since)
            ->orderByDesc('user_voucher_claims.created_at')
            ->get([
                'vouchers.voucher_name',
                'user_voucher_claims.claimed_at as created_at',
            ]);

        return response()->json([
            'data' => [
                'total_vouchers' => $totalVouchers,
                'total_claimed' => $totalClaimed,
                'total_redeemed' => $totalRedeemed,
                'claims_last_month' => $recentClaims,
                'redemptions_last_month' => $recentRedemptions,
            ],
        ]);
    }

    public function userVoucher(Request $request, string $voucher_id, string $user_id)
    {
        $vendorIds = $this->getVendorIds($request);
        if ($vendorIds === []) {
            return response()->json([
                'message' => 'Vendor not found.',
            ], 404);
        }

        $voucher = Vouchers::query()->where('voucher_id', $voucher_id)->first();
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        if (!in_array((string) $voucher->vendor_id, $vendorIds, true)) {
            return response()->json([
                'message' => 'Voucher does not belong to this vendor.',
            ], 403);
        }

        $user = User::query()
            ->select(['user_id', 'first_name', 'last_name', 'email', 'contact_no'])
            ->where('user_id', $user_id)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // $claimHistory = UserVouchers::query()
        //     ->where('voucher_id', $voucher_id)
        //     ->where('user_id', $user_id)
        //     ->orderByDesc('created_at')
        //     ->get([
        //         'user_voucher_id',
        //         'voucher_id',
        //         'user_id',
        //         'created_at',
        //     ]);

        // $claimedCount = $claimHistory->count();

        $redemptionHistory = UserVoucherClaims::query()
            ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
            ->where('user_vouchers.voucher_id', $voucher_id)
            ->where('user_vouchers.user_id', $user_id)
            ->orderByDesc('user_voucher_claims.created_at')
            ->get([
                'user_voucher_claims.user_voucher_claim_id',
                'user_voucher_claims.user_voucher_id',
                'user_voucher_claims.claimed_at',
                'user_voucher_claims.created_at',
            ]);

        $redeemedCount = $redemptionHistory->count();

        $claimLimit = (int) ($voucher->voucher_claim_per_user ?? 0);
        if ($claimLimit <= 0) {
            $claimLimit = 1;
        }

        $periodLimit = null;
        $periodRedeemedCount = null;
        $canRedeemInPeriod = true;
        if (!empty($voucher->voucher_claim_period) && !empty($voucher->voucher_claim_per_period)) {
            $start = null;
            if ($voucher->voucher_claim_period === 'week') {
                $start = now()->startOfWeek();
            } elseif ($voucher->voucher_claim_period === 'month') {
                $start = now()->startOfMonth();
            }

            if ($start) {
                $periodLimit = (int) $voucher->voucher_claim_per_period;
                $periodRedeemedCount = (int) UserVoucherClaims::query()
                    ->join('user_vouchers', 'user_vouchers.user_voucher_id', '=', 'user_voucher_claims.user_voucher_id')
                    ->where('user_vouchers.voucher_id', $voucher_id)
                    ->where('user_vouchers.user_id', $user_id)
                    ->where('user_voucher_claims.created_at', '>=', $start)
                    ->count();

                $canRedeemInPeriod = $periodRedeemedCount < $periodLimit;
            }
        }

        $expired = (string) $voucher->voucher_expiry_date < (string) now()->toDateString();
        $availableToRedeem = $claimLimit - $redeemedCount;

        $voucherStatus = 'redeemed';
        if ($expired) {
            $voucherStatus = 'expired';
        } elseif ($availableToRedeem > 0 && $canRedeemInPeriod) {
            $voucherStatus = 'redeem';
        }

        return response()->json([
            'data' => [
                'voucher_status' => $voucherStatus,
                'voucher' => $voucher,
                'user' => $user,
                'claim_limit' => $claimLimit,
                'redemption_count' => $redeemedCount,
                'period_claim_limit' => $periodLimit,
                'period_redemption_count' => $periodRedeemedCount,
                'redemption_history' => $redemptionHistory,
            ],
        ]);
    }

    public function redeem(Request $request, string $voucher_id, string $user_id)
    {
        $vendorIds = $this->getVendorIds($request);
        if ($vendorIds === []) {
            return response()->json([
                'message' => 'Vendor not found.',
            ], 404);
        }

        $voucher = Vouchers::query()->where('voucher_id', $voucher_id)->first();
        if (!$voucher) {
            return response()->json(['message' => 'Voucher not found.'], 404);
        }

        if (!in_array((string) $voucher->vendor_id, $vendorIds, true)) {
            return response()->json([
                'message' => 'Voucher does not belong to this vendor.',
            ], 403);
        }

        $userVoucher = UserVouchers::query()
            ->where('voucher_id', $voucher_id)
            ->where('user_id', $user_id)
            ->first();
        if (!$userVoucher) {
            return response()->json(['message' => 'User voucher not found.'], 404);
        }

        UserVoucherClaims::query()->create([
            'user_voucher_id' => $userVoucher->user_voucher_id,
            'claimed_at' => now(),
        ]);

        $claimLimit = (int) ($voucher->voucher_claim_per_user ?? 0);
        if ($claimLimit <= 0) {
            $claimLimit = 1;
        }
        $userClaimCount = UserVoucherClaims::query()
            ->leftJoin('user_vouchers', 'user_voucher_claims.user_voucher_id', '=', 'user_vouchers.user_voucher_id')
            ->where('user_vouchers.voucher_id', $voucher_id)
            ->where('user_vouchers.user_id', $user_id)
            ->count();

        if ($userClaimCount === $claimLimit) {
            Log::info('User has reached reached claim limit.');
            UserVouchers::query()->where('user_id', $user_id)
                ->where('voucher_id', $voucher_id)
                ->update(['is_valid' => false]);
        }

        return response()->json([
            'message' => 'Voucher redeemed successfully.',
        ]);
    }
}
