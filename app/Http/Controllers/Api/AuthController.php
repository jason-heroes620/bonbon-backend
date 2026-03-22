<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserReferralGifts;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', 'user')
            ->first();
        $user->membership = $this->getUserMembership($user->user_id);
        [$user->referral_gifts_earned, $user->referral_gifts_claimed] = $this->getUserReferralGiftCounts($user->user_id);

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'message' => 'Account is inactive or not found.',
            ], 403);
        }

        $deviceName = $validated['device_name'] ?? 'api';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->membership = $this->getUserMembership($user->user_id);
        [$user->referral_gifts_earned, $user->referral_gifts_claimed] = $this->getUserReferralGiftCounts($user->user_id);

        return response()->json([
            'user' => $user,
        ]);
    }

    public function register(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_no' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:255', 'same:password'],
            'referral_code' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validated->fails()) {

            return response()->json([
                'message' => 'Validation failed.',
                'error' => $validated->errors(),
            ], 422);
        }

        $referralCode = null;

        // check if referral code is valid
        if ($request->input('referral_code')) {
            $referralCode = ReferralCodes::where('referral_code', $request->input('referral_code'))->first();
            if (!$referralCode) {
                throw ValidationException::withMessages([
                    'referral_code' => ['The provided referral code is invalid.'],
                ]);
            }
        }

        $user = User::create([
            'first_name' => $request['first_name'],
            'last_name' => $request['last_name'],
            'email' => $request['email'],
            'contact_no' => $request['contact_no'],
            'password' => Hash::make($request['password']),
            'role' => 'user',
        ]);

        $token = $user->createToken('api')->plainTextToken;

        // create free membership for user
        $membership = Memberships::where('membership_code', 'MEMFREE')->first();
        UserMemberships::create([
            'user_id' => $user->user_id,
            'membership_id' => $membership->membership_id,
            'membership_start_date' => now(),
            'membership_end_date' => now()->addYear(),
            'membership_status' => 'active',
        ]);

        if ($referralCode) {
            $referrerMembershipType = $this->getUserMembershipType($referralCode->user_id);
            $isUnlimitedReferrer = in_array(
                strtoupper((string) $referrerMembershipType),
                ['KOL', 'FOBB'],
                true
            );

            $giftsEarned = (int) UserReferralGifts::query()
                ->where('user_id', $referralCode->user_id)
                ->count();

            if (!$isUnlimitedReferrer && $giftsEarned >= 2) {
                throw ValidationException::withMessages([
                    'referral_code' => ['The provided referral code has reached its maximum reward cycles.'],
                ]);
            }

            $cycle = $giftsEarned + 1;

            Referrals::create([
                'user_id' => $referralCode->user_id,
                'referee_id' => $user->user_id,
                'referral_code' => $referralCode->referral_code,
                'referral_date' => now(),
                'cycle' => $cycle,
                'referral_status' => 'pending',
            ]);
        }

        $user->membership = $this->getUserMembership($user->user_id);
        [$user->referral_gifts_earned, $user->referral_gifts_claimed] = $this->getUserReferralGiftCounts($user->user_id);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function claimReferralGift(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $membershipType = $this->getUserMembershipType($user->user_id);
        $isUnlimited = in_array(strtoupper((string) $membershipType), ['KOL', 'FOBB'], true);
        $maxClaims = $isUnlimited ? null : 2;

        $gift = null;
        $error = null;

        DB::transaction(function () use ($user, $maxClaims, &$gift, &$error) {
            $claimedCount = (int) UserReferralGifts::query()
                ->where('user_id', $user->user_id)
                ->whereNotNull('claimed_at')
                ->lockForUpdate()
                ->count();

            if ($maxClaims !== null && $claimedCount >= $maxClaims) {
                $error = [
                    'message' => 'Maximum referral gifts claimed.',
                    'status' => 400,
                ];
                return;
            }

            $gift = UserReferralGifts::query()
                ->where('user_id', $user->user_id)
                ->whereNull('claimed_at')
                ->orderBy('earned_at')
                ->lockForUpdate()
                ->first();

            if (!$gift) {
                $error = [
                    'message' => 'No unclaimed referral gifts available.',
                    'status' => 400,
                ];
                return;
            }

            $gift->update([
                'claimed_at' => now(),
            ]);
        });

        if ($error) {
            return response()->json([
                'message' => $error['message'],
            ], $error['status']);
        }

        if (!$gift) {
            return response()->json([
                'message' => 'Failed to claim referral gift.',
            ], 500);
        }

        [$earned, $claimed] = $this->getUserReferralGiftCounts($user->user_id);

        return response()->json([
            'message' => 'Referral gift claimed successfully.',
            'data' => [
                'earned' => $earned,
                'claimed' => $claimed,
                'gift' => [
                    'user_referral_gift_id' => $gift->user_referral_gift_id,
                    'earned_at' => $gift->earned_at,
                    'claimed_at' => $gift->claimed_at,
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function destroy(Request $request)
    {
        // $validated = $request->validate([
        //     'confirm' => ['required', 'accepted'],
        //     'password' => ['nullable', 'string'],
        // ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // if (!empty($validated['password']) && !Hash::check($validated['password'], $user->password)) {
        //     throw ValidationException::withMessages([
        //         'password' => ['The provided password is incorrect.'],
        //     ]);
        // }

        $user->tokens()->delete();
        $user->update([
            'is_active' => false,
        ]);
        // $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }

    public function deleteUser(Request $request)
    {
        $validated = $request->validate([
            'confirm' => ['required', 'accepted'],
            'password' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!empty($validated['password']) && !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->tokens()->delete();
        $user->update([
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Account deleted successfully.',
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

    private function getUserMembershipType($user_id): ?string
    {
        return UserMemberships::query()
            ->where('user_id', $user_id)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->value('memberships.membership_type');
    }

    private function getUserReferralGiftCounts(string $userId): array
    {
        $earned = (int) UserReferralGifts::query()->where('user_id', $userId)->count();
        $claimed = (int) UserReferralGifts::query()
            ->where('user_id', $userId)
            ->whereNotNull('claimed_at')
            ->count();

        return [$earned, $claimed];
    }
}
