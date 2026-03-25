<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Memberships;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\User;
use App\Models\UserInterestList;
use App\Models\UserMemberships;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:register-interest-list', function () {
    $assert = function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    DB::beginTransaction();
    try {
        $freeMembership = Memberships::query()->firstOrCreate([
            'membership_code' => 'FREE-' . strtoupper(Str::random(6)),
        ], [
            'membership_name' => 'Free',
            'membership_description' => 'Free membership',
            'membership_type' => 'Free',
            'membership_price' => 0,
            'duration' => 365,
            'duration_unit' => 'days',
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => null,
            'is_active' => true,
            'sort_order' => 0,
            'best_value' => false,
        ]);

        $referrer = User::query()->create([
            'first_name' => 'Referrer',
            'last_name' => 'User',
            'email' => 'referrer+' . strtolower(Str::random(8)) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $referralCode = strtoupper(Str::random(8));
        ReferralCodes::query()->create([
            'user_id' => $referrer->user_id,
            'campaign_name' => 'Test',
            'referral_code' => $referralCode,
            'code_effective_date' => now()->toDateString(),
            'code_expiry_date' => now()->addYear()->toDateString(),
            'usage_count' => 0,
            'max_usage' => 0,
            'is_active' => true,
        ]);

        $hasFreeMembership = Memberships::query()
            ->whereRaw('LOWER(membership_type) = ?', ['free'])
            ->exists();

        $usersCountBase = User::query()->count();
        $interestListCountBase = UserInterestList::query()->count();
        $membershipCountBase = UserMemberships::query()->count();
        $referralsCountBase = Referrals::query()->count();

        $kernel = app(Kernel::class);

        $emailWithout = 'test-no-ref+' . strtolower(Str::random(8)) . '@example.com';
        $payloadWithout = [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => $emailWithout,
            'contact_no' => '0123456789',
            'referral_code' => null,
        ];

        $requestWithout = Request::create('/api/user-interest-list/register', 'POST', $payloadWithout, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $responseWithout = $kernel->handle($requestWithout);
        $kernel->terminate($requestWithout, $responseWithout);

        $assert($responseWithout->getStatusCode() === 200, 'Expected 200 for registerInterestList without referral code.');
        $assert(UserInterestList::query()->where('email', $emailWithout)->exists(), 'Expected interest list record to be created.');
        $assert(User::query()->where('email', $emailWithout)->doesntExist(), 'Expected no user to be created without referral code.');
        $assert(User::query()->count() === $usersCountBase, 'Expected no additional user to be created without referral code.');
        $assert(UserMemberships::query()->count() === $membershipCountBase, 'Expected no additional membership to be created without referral code.');
        $assert(Referrals::query()->count() === $referralsCountBase, 'Expected no additional referrals to be created without referral code.');
        $assert(UserInterestList::query()->count() === $interestListCountBase + 1, 'Expected interest list count to increase by 1.');

        $emailWith = 'test-ref+' . strtolower(Str::random(8)) . '@example.com';
        $payloadWith = [
            'first_name' => 'Referred',
            'last_name' => 'User',
            'email' => $emailWith,
            'contact_no' => '0123456789',
            'referral_code' => $referralCode,
        ];

        $requestWith = Request::create('/api/user-interest-list/register', 'POST', $payloadWith, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $responseWith = $kernel->handle($requestWith);
        $kernel->terminate($requestWith, $responseWith);

        $assert($responseWith->getStatusCode() === 200, 'Expected 200 for registerInterestList with referral code.');
        $assert(UserInterestList::query()->where('email', $emailWith)->exists(), 'Expected interest list record to be created (with referral code).');

        $createdUser = User::query()->where('email', $emailWith)->first();
        $assert((bool) $createdUser, 'Expected a user to be created when referral code is valid.');
        $assert((bool) $createdUser && $createdUser->is_active === false, 'Expected created user to be inactive.');
        $assert(User::query()->count() === $usersCountBase + 1, 'Expected users count to increase by 1 for valid referral code.');

        $membership = UserMemberships::query()->where('user_id', $createdUser->user_id)->first();
        if ($hasFreeMembership) {
            $assert((bool) $membership, 'Expected a membership to be created for the new user.');
            $assert(
                (bool) $membership
                    && Memberships::query()
                    ->where('membership_id', $membership->membership_id)
                    ->whereRaw('LOWER(membership_type) = ?', ['free'])
                    ->exists(),
                'Expected new user membership to use a Free membership.',
            );
            $assert(UserMemberships::query()->count() === $membershipCountBase + 1, 'Expected memberships count to increase by 1 for valid referral code.');
        } else {
            $assert(!$membership, 'Expected no membership to be created when no Free membership exists.');
            $assert(UserMemberships::query()->count() === $membershipCountBase, 'Expected memberships count not to change when no Free membership exists.');
        }

        $referral = Referrals::query()
            ->where('user_id', $referrer->user_id)
            ->where('referee_id', $createdUser->user_id)
            ->first();
        $assert((bool) $referral, 'Expected referral record to be created.');
        $assert((bool) $referral && $referral->referral_code === $referralCode, 'Expected referral record to store referral code.');
        $assert(Referrals::query()->count() === $referralsCountBase + 1, 'Expected referrals count to increase by 1 for valid referral code.');

        $emailInvalid = 'test-invalid+' . strtolower(Str::random(8)) . '@example.com';
        $payloadInvalid = [
            'first_name' => 'Invalid',
            'last_name' => 'Referral',
            'email' => $emailInvalid,
            'contact_no' => '0123456789',
            'referral_code' => 'INVALID',
        ];

        $requestInvalid = Request::create('/api/user-interest-list/register', 'POST', $payloadInvalid, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $responseInvalid = $kernel->handle($requestInvalid);
        $kernel->terminate($requestInvalid, $responseInvalid);

        $assert($responseInvalid->getStatusCode() === 200, 'Expected 200 for registerInterestList with invalid referral code.');
        $assert(UserInterestList::query()->where('email', $emailInvalid)->exists(), 'Expected interest list record to be created (invalid referral code still registers interest).');
        $assert(User::query()->where('email', $emailInvalid)->doesntExist(), 'Expected no user to be created for invalid referral code.');
        $assert(User::query()->count() === $usersCountBase + 1, 'Expected users count not to increase for invalid referral code.');
        $assert(
            UserMemberships::query()->count() === ($hasFreeMembership ? $membershipCountBase + 1 : $membershipCountBase),
            'Expected memberships count not to increase for invalid referral code.',
        );
        $assert(Referrals::query()->count() === $referralsCountBase + 1, 'Expected referrals count not to increase for invalid referral code.');
        $assert(UserInterestList::query()->count() === $interestListCountBase + 3, 'Expected interest list count to increase by 3 across scenarios.');

        $this->info('PASS: registerInterestList flow (without code, with valid code, with invalid code).');
    } finally {
        DB::rollBack();
    }
})->purpose('Non-destructive flow test for user-interest-list/register (runs inside a DB transaction and rolls back).');
