<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMemberships;
use App\Models\UserReferrals;
use Illuminate\Http\Request;

class UserReferralController extends Controller
{
    // get referral list by referral code
    public function referral(Request $request, string $referralCode)
    {
        // get the referral list by referral code then check no. of referee, if per cycle reach 5, user is qualified for referral gift
        // if not, user is not qualified for referral gift
        // return the referral list with qualified status


        $qualified = false;

        // check if referral code belongs to KOL or FOBB
        $membership_type = $this->checkMembershipType($referralCode);
        if ($membership_type === 'KOL' || $membership_type === 'FOBB') {
            // return total no. of referee
            $totalReferee = UserReferrals::query()
                ->where('referral_code', $referralCode)
                ->where('referral_status', 'qualified')
                ->count();
        } else if ($membership_type === 'Standard') {
            // select max(cycle) of referral code user
            $maxCycle = UserReferrals::query()
                ->where('referral_code', $referralCode)
                ->max('cycle');

            // return total no. of referee per cycle
            $totalReferee = UserReferrals::query()
                ->where('referral_code', $referralCode)
                ->where('cycle', $maxCycle)
                ->where('referral_status', 'qualified')
                ->count();
            if ($totalReferee === 5) {
                $qualified = true;
            }
        } else {
            // Free membership
            // return total no. of referee
            $totalReferee = UserReferrals::query()
                ->where('referral_code', $referralCode)
                ->count();
        }

        return response()->json([
            'membership_type' => $membership_type,
            'totalReferee' => $totalReferee,
            'qualified' => $qualified,
        ]);
    }

    // check if user membership belongs to KOL or FOBB
    private function checkMembershipType(string $referralCode): bool
    {
        $user_id = User::query()
            ->where('referral_code', $referralCode)
            ->first()->user_id;

        // check user membership type
        $membership_type = UserMemberships::query()
            ->leftJoin('memberships', 'user_memberships.membership_id', '=', 'memberships.membership_id')
            ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.membership_type_id')
            ->where('user_id', $user_id)
            ->where('membership_status', 'active')
            ->orderBy('user_memberships.membership_end_date', 'desc')
            ->first()->membership_type;

        return $membership_type;
    }
}
