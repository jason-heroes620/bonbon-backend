<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMemberships;

class UserController extends Controller
{
    public function show($user_id)
    {
        $user = User::find($user_id);
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

    private function getUserMembership($user_id)
    {
        $membership = UserMemberships::where('user_id', $user_id)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->select('memberships.membership_type', 'user_memberships.membership_status', 'user_memberships.membership_end_date')
            ->where('user_memberships.membership_status', 'active')
            ->first();

        return $membership;
    }
}
