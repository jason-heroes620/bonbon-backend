<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransactions;
use App\Models\Memberships;
use App\Models\User;
use App\Models\UserMemberships;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected int $max_vouchers = 5;

    public function show(string $user_id)
    {
        $user = User::find($user_id, 'user_id');
        $user->membership = $this->getUserMembership($user->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }
        return response()->json([
            'user' => $user,
        ]);
    }

    public function mePoints(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'message' => 'User points retrieved successfully.',
            'data' => [
                'balance' => $user->credit_balance,
            ],
        ]);
    }

    public function mePointsTransactions(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        // request pagination parameter
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);

        $start_date = $request->input('start_date', null);
        $end_date = $request->input('end_date', null);
        if ($start_date && $end_date) {
            $transactions = CreditTransactions::query()
                ->where('user_id', $user->user_id)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->latest()
                ->paginate($limit, ['credit_amount', 'created_at', 'transaction_description'], 'page', $page);
        } else {
            $transactions = CreditTransactions::query()
                ->where('user_id', $user->user_id)
                ->latest()
                ->paginate($limit, ['credit_amount', 'created_at', 'transaction_description'], 'page', $page);
        }

        return response()->json([
            'message' => 'User points transactions retrieved successfully.',
            'data' => $transactions,
        ]);
    }

    public function trial(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // update user membership to trial membership
        try {
            $membership = Memberships::query()
                ->select(['membership_id', 'membership_type'])
                ->where('membership_type', 'Trial')
                ->first();

            if (!$membership) {
                return response()->json([
                    'message' => 'Trial membership not found.',
                ], 404);
            }

            UserMemberships::query()
                ->where('user_id', $user->user_id)
                ->where('membership_status', 'active')
                ->update([
                    'membership_status' => 'inactive',
                    'membership_end_date' => now()->subDay(1)->toDateString(),
                ]);

            UserMemberships::create([
                'user_id' => $user->user_id,
                'membership_id' => $membership->membership_id,
                'membership_status' => 'active',
                'membership_start_date' => now()->toDateString(),
            ]);

            $membership->max_vouchers = $this->max_vouchers;

            return response()->json([
                'message' => 'User membership updated successfully.',
                'membership' => $membership,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getUserMembership(string $user_id)
    {
        $membership = UserMemberships::query()
            ->where('user_id', $user_id)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->select(
                'memberships.membership_type',
                'user_memberships.membership_status',
                'user_memberships.membership_end_date',
                'user_memberships.max_vouchers',
                'user_memberships.redeemed_vouchers_count',
            )
            ->where('user_memberships.membership_status', 'active')
            ->first();

        return $membership;
    }
}
