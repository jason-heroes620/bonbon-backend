<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referrals;
use App\Models\UserMemberships;
use Illuminate\Http\Request;

class ReferralController extends Controller
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
            $totalReferee = Referrals::query()
                ->where('referral_code', $referralCode)
                ->where('referral_status', 'qualified')
                ->count();
        } else if ($membership_type === 'Standard') {
            // select max(cycle) of referral code user
            $maxCycle = Referrals::query()
                ->where('referral_code', $referralCode)
                ->max('cycle');

            // return total no. of referee per cycle
            $totalReferee = Referrals::query()
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
            $totalReferee = Referrals::query()
                ->where('referral_code', $referralCode)
                ->where('referral_status', 'qualified')
                ->count();
        }

        return response()->json([
            'membership_type' => $membership_type,
            'totalReferee' => $totalReferee,
            'qualified' => $qualified,
        ]);
    }

    // check if user membership belongs to KOL or FOBB
    private function checkMembershipType(string $referralCode): string
    {
        $normalizedReferralCode = strtoupper(trim($referralCode));
        if ($normalizedReferralCode === '') {
            return 'Free';
        }

        $membership_type = UserMemberships::query()
            ->join('users', 'user_memberships.user_id', '=', 'users.user_id')
            ->leftJoin('memberships', 'user_memberships.membership_id', '=', 'memberships.membership_id')
            ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.membership_type_id')
            ->whereRaw('UPPER(users.referral_code) = ?', [$normalizedReferralCode])
            ->where('user_memberships.membership_status', 'active')
            ->orderBy('user_memberships.membership_end_date', 'desc')
            ->value('membership_types.membership_type');

        return $membership_type ?: 'Free';
    }
}
