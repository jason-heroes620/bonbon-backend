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
use App\Models\MembershipTypes;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\Payments;
use App\Models\Products;
use App\Models\Taxes;
use App\Models\UserReferralGifts;
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

        if ($responseWithout->getStatusCode() !== 200) {
            $this->error('Failed: ' . $responseWithout->getContent());
        }

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

        $assert($responseInvalid->getStatusCode() === 400, 'Expected 400 for registerInterestList with invalid referral code.');
        $assert(UserInterestList::query()->where('email', $emailInvalid)->doesntExist(), 'Expected interest list record not to be created for invalid referral code.');
        $assert(User::query()->where('email', $emailInvalid)->doesntExist(), 'Expected no user to be created for invalid referral code.');
        $assert(User::query()->count() === $usersCountBase + 1, 'Expected users count not to increase for invalid referral code.');
        $assert(
            UserMemberships::query()->count() === ($hasFreeMembership ? $membershipCountBase + 1 : $membershipCountBase),
            'Expected memberships count not to increase for invalid referral code.',
        );
        $assert(Referrals::query()->count() === $referralsCountBase + 1, 'Expected referrals count not to increase for invalid referral code.');
        $assert(UserInterestList::query()->count() === $interestListCountBase + 2, 'Expected interest list count to increase by 2 across scenarios.');

        $this->info('PASS: registerInterestList flow (without code, with valid code, with invalid code).');
    } finally {
        DB::rollBack();
    }
})->purpose('Non-destructive flow test for user-interest-list/register (runs inside a DB transaction and rolls back).');

Artisan::command('test:payment-success-callback', function () {
    $assert = function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    DB::beginTransaction();
    try {
        config([
            'services.ipay88.key' => 'test-merchant-key',
            'services.ipay88.code' => 'test-merchant-code',
        ]);

        $tax = Taxes::query()->create([
            'tax_name' => 'Test Tax',
            'tax_rate' => 0,
            'is_active' => true,
        ]);

        $membershipTypeFree = MembershipTypes::query()->create([
            'membership_type' => 'Free',
            'is_active' => true,
        ]);

        $membershipTypeStandard = MembershipTypes::query()->create([
            'membership_type' => 'Standard',
            'is_active' => true,
        ]);

        $freeMembership = Memberships::query()->create([
            'membership_code' => 'MEMFREE',
            'membership_name' => 'Free Membership',
            'membership_description' => 'Free Membership',
            'membership_type_id' => $membershipTypeFree->membership_type_id,
            'membership_type' => 'Free',
            'membership_price' => 0,
            'duration' => 1,
            'duration_unit' => 'years',
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => null,
            'is_active' => true,
            'sort_order' => 0,
            'best_value' => false,
        ]);

        $standardMembership = Memberships::query()->create([
            'membership_code' => 'MEMSTD',
            'membership_name' => 'Standard Membership',
            'membership_description' => 'Standard Membership',
            'membership_type_id' => $membershipTypeStandard->membership_type_id,
            'membership_type' => 'Standard',
            'membership_price' => 10,
            'duration' => 1,
            'duration_unit' => 'years',
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => null,
            'is_active' => true,
            'sort_order' => 1,
            'best_value' => true,
        ]);

        $product = Products::query()->create([
            'product_code' => $standardMembership->membership_code,
            'product_name' => 'Standard Membership Product',
            'product_sku' => null,
            'product_description' => 'Standard Membership Product',
            'stock_quantity' => 0,
            'uom' => 'unit',
            'product_weight' => null,
            'product_dimensions' => null,
            'is_featured' => false,
            'is_visible' => false,
            'is_taxable' => false,
            'tax_rate_id' => $tax->tax_rate_id,
            'retail_price' => 10,
            'sale_price' => 10,
            'is_active' => true,
            'is_unlimited' => true,
        ]);

        $referrer = User::query()->create([
            'first_name' => 'Referrer',
            'last_name' => 'User',
            'email' => 'referrer+' . strtolower(Str::random(8)) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        UserMemberships::query()->create([
            'user_id' => $referrer->user_id,
            'membership_id' => $freeMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        $referralCode = '123456';
        ReferralCodes::query()->create([
            'user_id' => $referrer->user_id,
            'campaign_name' => 'Test Campaign',
            'referral_code' => $referralCode,
            'code_effective_date' => now()->toDateString(),
            'code_expiry_date' => now()->addYear()->toDateString(),
            'usage_count' => 0,
            'max_usage' => 0,
            'is_active' => true,
        ]);

        $buyer = User::query()->create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'email' => 'buyer+' . strtolower(Str::random(8)) . '@example.com',
            'contact_no' => '0123456789',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        UserMemberships::query()->create([
            'user_id' => $buyer->user_id,
            'membership_id' => $freeMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        Referrals::query()->create([
            'user_id' => $referrer->user_id,
            'referee_id' => $buyer->user_id,
            'referral_code' => $referralCode,
            'referral_date' => now()->toDateString(),
            'referral_status' => 'pending',
        ]);

        $refNo = now()->format('ymd') . '-' . strtoupper(Str::random(6));

        $order = Orders::query()->create([
            'user_id' => $buyer->user_id,
            'order_no' => $refNo,
            'order_date' => now()->toDateString(),
            'order_description' => 'Test Order',
            'total_price' => 10,
            'total_charges' => 0,
            'total_discount' => 0,
            'total_payment' => 10,
            'shipping_method' => null,
            'shipping_address' => null,
            'billing_address' => null,
            'discount_code' => null,
            'wallet_credit_used' => 0,
            'order_status' => 'pending',
        ]);

        OrderItems::query()->create([
            'order_id' => $order->order_id,
            'product_id' => $product->product_id,
            'quantity' => 1,
            'uom' => $product->uom,
            'unit_price' => 10,
            'tax' => 0,
            'discount' => 0,
            'total_price' => 10,
        ]);

        $merchantKey = config('services.ipay88.key');
        $merchantCode = config('services.ipay88.code');
        $paymentId = '1';
        $amount = '10.00';
        $currency = 'MYR';
        $status = '1';
        $signature = base64_encode(hash('sha1', $merchantKey . $merchantCode . $paymentId . $refNo . '1000' . $currency . $status, true));

        $kernel = app(Kernel::class);
        $callbackRequest = Request::create('/api/payments/backend-callback', 'POST', [
            'MerchantCode' => $merchantCode,
            'PaymentId' => $paymentId,
            'RefNo' => $refNo,
            'Amount' => $amount,
            'Currency' => $currency,
            'Status' => $status,
            'Signature' => $signature,
            'TransId' => 'TEST-TRANS-1',
            'TranDate' => now()->format('Y-m-d H:i:s'),
            'S_bankname' => 'Test Bank',
            'CCName' => 'Test Card',
            'CCNo' => '4111111111111111',
        ], [], [], [
            'HTTP_ACCEPT' => 'text/plain',
        ]);

        $callbackResponse = $kernel->handle($callbackRequest);
        $kernel->terminate($callbackRequest, $callbackResponse);

        $assert($callbackResponse->getStatusCode() === 200, 'Expected 200 from backend callback.');
        $assert($callbackResponse->getContent() === 'RECEIVEOK', 'Expected RECEIVEOK response from backend callback.');
        $assert(Orders::query()->where('order_no', $refNo)->where('order_status', 'completed')->exists(), 'Expected order to be marked completed.');
        $assert(Payments::query()->where('order_no', $refNo)->where('payment_status', 1)->exists(), 'Expected payment record to be created.');
        $assert(Referrals::query()->where('user_id', $referrer->user_id)->where('referee_id', $buyer->user_id)->where('referral_status', 'qualified')->exists(), 'Expected referral to be qualified.');
        $assert(UserReferralGifts::query()->where('user_id', $referrer->user_id)->count() === 0, 'Expected no referral gifts to be created at 1 qualified referral.');

        $this->info('PASS: backend-callback successful payment flow.');
    } finally {
        DB::rollBack();
    }
})->purpose('Non-destructive success test for /api/payments/backend-callback (runs inside a DB transaction and rolls back).');
