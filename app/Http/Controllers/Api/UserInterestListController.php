<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserInterestList;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendFoundingMemberQueuedEmail;

class UserInterestListController extends Controller
{
    public function registerInterestList(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'contact_no' => ['required', 'string', 'max:20'],
            'referral_code' => ['nullable', 'string', 'max:10'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        // check if email exist in user table
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return response()->json([
                'message' => 'Email already registered.',
            ], 400);
        }

        $checking = $this->startReferralProcedure([
            ...$validated,
            'email' => $email,
        ]);

        if (!$checking) {
            return response()->json([
                'data' => [
                    'error' => 'error',
                    'message' => 'Email is already registered or referral code not found',
                ]
            ], 400);
        }

        return response()->json([
            'data' => [
                'success' => 'success',
                'message' => 'Interest list registered.',
            ]
        ], 200);
    }

    public function getListCount(Request $request)
    {
        $count = UserInterestList::query()->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function startReferralProcedure(array $validated)
    {
        // check if email exist in user_interest_list table and user table
        $user = User::query()->where('email', $validated['email'])->first();
        if ($user) {
            return false;
        }

        $record = UserInterestList::query()->where('email', $validated['email'])->first();
        if ($record) {
            return false;
        }

        // if referral code is provided, check if it exists referral_codes table 
        if (isset($validated['referral_code']) && $validated['referral_code'] !== '') {
            $referralRecord = ReferralCodes::query()
                ->where('referral_code', $validated['referral_code'])
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('code_expiry_date')->orWhere('code_expiry_date', '>=', now()->toDateString());
                })
                ->first();

            if (!$referralRecord) {
                return false;
            }
        }

        try {
            $record = UserInterestList::query()->firstOrCreate([
                'email' => $validated['email'],
            ], [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'contact_no' => $validated['contact_no'],
                'referral_code' => $validated['referral_code'],
            ]);

            $privateLaunchDate = new DateTimeImmutable("2026-04-20");
            SendFoundingMemberQueuedEmail::dispatch(
                $validated['email'],
                trim($validated['first_name'] . ' ' . $validated['last_name']),
                $privateLaunchDate->format('d M Y')
            )->delay(now()->addMinute());

            if (!isset($referralRecord)) {
                return false;
            }

            $referrer = User::query()->find($referralRecord->user_id);
            if (!$referrer) {
                return false;
            }

            if (strtolower((string) $referrer->email) === strtolower((string) $validated['email'])) {
                return false;
            }

            DB::transaction(function () use ($validated, $referrer) {
                $newUser = User::query()->firstOrCreate([
                    'email' => $validated['email'],
                ], [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'contact_no' => $validated['contact_no'],
                    'password' => bcrypt($validated['email']),
                    'role' => 'user',
                    'is_active' => false,
                ]);

                $freeMembershipId = Memberships::query()
                    ->whereRaw('LOWER(membership_type) = ?', ['free'])
                    ->value('membership_id');

                if ($freeMembershipId) {
                    UserMemberships::query()->firstOrCreate([
                        'user_id' => $newUser->user_id,
                    ], [
                        'membership_id' => $freeMembershipId,
                        'membership_start_date' => now()->toDateString(),
                        'membership_end_date' => now()->addYear()->toDateString(),
                        'membership_status' => 'active',
                        'inactive_reason' => null,
                        'auto_renew' => false,
                    ]);
                }

                Referrals::query()->firstOrCreate([
                    'user_id' => $referrer->user_id,
                    'referee_id' => $newUser->user_id,
                ], [
                    'referral_code' => $validated['referral_code'],
                    'referral_date' => now()->toDateString(),
                    'referral_status' => 'pending',
                ]);
            });
            return true;
        } catch (\Exception $e) {
            // log error
            logger()->error('Error while processing referral code: ' . $e->getMessage());
        }
        return false;
    }
}
