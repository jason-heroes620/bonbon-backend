<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\TransactionTypes;
use App\Models\User;
use App\Models\UserInterestList;
use App\Models\UserReferralGifts;
use App\Models\UserMemberships;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected CreditService $creditService;

    private const FREE_MEMBERSHIP_CODE = 'MEMFREE';
    private const UNLIMITED_REFERRER_MEMBERSHIP_TYPES = ['KOL', 'FOBB'];

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

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

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'message' => 'Account is inactive. Please verify your email.',
            ], 403);
        }

        $user->membership = $this->getUserMembership($user->user_id);
        [$user->referral_gifts_earned, $user->referral_gifts_claimed] = $this->getUserReferralGiftCounts($user->user_id);

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

    public function merchantLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', 'vendor')
            ->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'api';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function register(Request $request)
    {
        Log::info('register');
        Log::info($request->all());

        if (!$request->has('referral_code') && $request->has('referralCode')) {
            $request->merge([
                'referral_code' => $request->input('referralCode'),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_no' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:255', 'same:password'],
            'referral_code' => ['nullable', 'string', 'max:50'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'error' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $warnings = [];

        $inputReferralCode = $validated['referral_code'] ?? null;
        $inputReferralCode = $inputReferralCode !== null ? strtoupper(trim((string) $inputReferralCode)) : null;
        $inputReferralCode = $inputReferralCode === '' ? null : $inputReferralCode;

        $user = DB::transaction(function () use ($validated, $inputReferralCode, &$warnings) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'contact_no' => $validated['contact_no'],
                'password' => Hash::make($validated['password']),
                'is_active' => false,
                'role' => 'user',
            ]);
            Log::info($user);
            $credit = TransactionTypes::query()
                ->where('transaction_type', 'account_registration')
                ->first();
            if ($credit) {
                $this->creditService->addCredits(
                    $user,
                    (int) $credit->credit_amount,
                    (string) $credit->transaction_type,
                    null,
                    'Account registration'
                );
            } else {
                Log::warning('Missing transaction_types row for account_registration; skipping registration credits.');
            }

            $membership = Memberships::query()
                ->where('membership_code', self::FREE_MEMBERSHIP_CODE)
                ->first();
            if (!$membership) {
                throw ValidationException::withMessages([
                    'membership' => ['Free membership is not configured. Please contact support.'],
                ]);
            }

            UserMemberships::create([
                'user_id' => $user->user_id,
                'membership_id' => $membership->membership_id,
                'membership_start_date' => now(),
                'membership_end_date' => now()->addYear(),
                'membership_status' => 'active',
            ]);

            if ($inputReferralCode !== null) {
                try {
                    $referralCode = ReferralCodes::query()
                        ->whereRaw('UPPER(referral_code) = ?', [$inputReferralCode])
                        ->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('code_expiry_date')
                                ->orWhere('code_expiry_date', '>=', now()->toDateString());
                        })
                        ->first();

                    if (!$referralCode) {
                        $warnings[] = 'Referral code not found or inactive. Registration continued without referral.';
                    } else {
                        $referrerMembershipType = $this->getUserMembershipType($referralCode->user_id);
                        $isUnlimitedReferrer = in_array(
                            strtoupper((string) $referrerMembershipType),
                            self::UNLIMITED_REFERRER_MEMBERSHIP_TYPES,
                            true
                        );

                        $giftsEarned = (int) UserReferralGifts::query()
                            ->where('user_id', $referralCode->user_id)
                            ->count();

                        $referralStatus = 'pending';
                        if (!$isUnlimitedReferrer && $giftsEarned >= 2) {
                            $warnings[] = 'Referral code has reached its maximum reward cycles. Registration continued without referral.';
                            $referralStatus = 'pending';
                        }

                        $cycle = $giftsEarned + 1;
                        Referrals::query()->updateOrCreate([
                            'user_id' => $referralCode->user_id,
                            'referee_id' => $user->user_id,
                        ], [
                            'referral_code' => $referralCode->referral_code,
                            'referral_date' => now()->toDateString(),
                            'cycle' => $cycle,
                            'referral_status' => $referralStatus,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Referral handling failed during registration; continuing without referral.', [
                        'referral_code' => $inputReferralCode,
                        'error' => $e->getMessage(),
                    ]);
                    $warnings[] = 'Referral processing failed. Registration continued.';
                }
            }

            try {
                $email = strtolower(trim((string) $validated['email']));

                $interest = UserInterestList::query()
                    ->where('email', $email)
                    ->first();

                $interestReferralCode = $interest?->referral_code;
                if ($interestReferralCode) {
                    $interestReferralCode = strtoupper(trim((string) $interestReferralCode));
                    if ($interestReferralCode !== '') {
                        $owner = ReferralCodes::query()
                            ->whereRaw('UPPER(referral_code) = ?', [$interestReferralCode])
                            ->value('user_id');

                        if ($owner) {
                            Referrals::query()->firstOrCreate([
                                'user_id' => $owner,
                                'referee_id' => $user->user_id,
                            ], [
                                'referral_code' => $interestReferralCode,
                                'referral_date' => now()->toDateString(),
                                'referral_status' => 'pending',
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Interest list referral handling failed during registration; continuing.', [
                    'email' => $validated['email'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $warnings[] = 'Interest list referral processing failed. Registration continued.';
            }

            $user->sendEmailVerificationNotification();

            return $user;
        });


        // end 
        $user->membership = $this->getUserMembership($user->user_id);
        [$user->referral_gifts_earned, $user->referral_gifts_claimed] = $this->getUserReferralGiftCounts($user->user_id);

        return response()->json([
            'message' => 'Registration successful. Please verify your email to activate your account.',
            'user' => $user,
            'warnings' => $warnings,
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
        Log::info('User logged out successfully.');

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
