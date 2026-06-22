<?php

namespace Tests\Feature;

use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\Payments;
use App\Models\Products;
use App\Models\ReferralCodes;
use App\Models\ReferralEarnings;
use App\Models\Referrals;
use App\Models\Taxes;
use App\Models\TransactionTypes;
use App\Models\User;
use App\Models\UserReferralGifts;
use App\Models\UserMemberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_membership_payment_flow(): void
    {
        config([
            'services.ipay88.key' => 'test-merchant-key',
            'services.ipay88.code' => 'test-merchant-code',
        ]);

        TransactionTypes::create([
            'transaction_type' => 'account_registration',
            'transaction_name' => 'Account registration',
            'credit_amount' => 0,
            'effective_date' => now()->toDateString(),
            'expire_date' => null,
            'is_active' => true,
        ]);

        TransactionTypes::create([
            'transaction_type' => 'standard_membership',
            'transaction_name' => 'standard_membership',
            'credit_amount' => 0,
            'effective_date' => now()->toDateString(),
            'expire_date' => null,
            'is_active' => true,
        ]);

        $tax = Taxes::create([
            'tax_name' => 'Test Tax',
            'tax_rate' => 0,
            'is_active' => true,
        ]);

        $membershipTypeFree = MembershipTypes::create([
            'membership_type' => 'Free',
            'is_active' => true,
        ]);

        $membershipTypeStandard = MembershipTypes::create([
            'membership_type' => 'Standard',
            'is_active' => true,
        ]);

        $freeMembership = Memberships::create([
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

        $standardMembership = Memberships::create([
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

        $product = Products::create([
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

        $referrer = User::create([
            'first_name' => 'Referrer',
            'last_name' => 'User',
            'email' => 'referrer@example.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        UserMemberships::create([
            'user_id' => $referrer->user_id,
            'membership_id' => $standardMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        ReferralCodes::create([
            'user_id' => $referrer->user_id,
            'campaign_name' => 'Test Campaign',
            'referral_code' => '123456',
            'code_effective_date' => now()->toDateString(),
            'code_expiry_date' => now()->addYear()->toDateString(),
            'usage_count' => 0,
            'max_usage' => 0,
            'is_active' => true,
        ]);

        $registerResponse = $this->postJson('/api/register', [
            'first_name' => 'Dummy',
            'last_name' => 'User',
            'email' => 'dummy@example.com',
            'contact_no' => '0123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => '123456',
        ]);

        $registerResponse->assertOk();

        $dummyUser = User::query()->where('email', 'dummy@example.com')->firstOrFail();

        $this->assertDatabaseHas('referrals', [
            'user_id' => $referrer->user_id,
            'referee_id' => $dummyUser->user_id,
            'referral_code' => '123456',
            'referral_status' => 'pending',
        ]);

        UserMemberships::query()
            ->where('user_id', $dummyUser->user_id)
            ->update(['membership_status' => 'inactive']);

        UserMemberships::create([
            'user_id' => $dummyUser->user_id,
            'membership_id' => $standardMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        $this->actingAs($dummyUser, 'sanctum');

        $createPaymentResponse = $this->postJson('/api/payments/create', [
            'amount' => 10.00,
            'subtotal' => 10.00,
            'total_charges' => 0,
            'discount_value' => 0,
            'total_payment' => 10.00,
            'products' => [
                [
                    'product_id' => $product->product_id,
                    'quantity' => 1,
                    'uom' => $product->uom,
                    'unit_price' => 10.00,
                    'tax' => 0,
                    'discount_value' => 0,
                    'amount' => 10.00,
                ],
            ],
        ]);

        $createPaymentResponse->assertOk();
        $refNo = (string) $createPaymentResponse->json('refNo');
        $this->assertNotEmpty($refNo);

        $merchantKey = config('services.ipay88.key');
        $merchantCode = config('services.ipay88.code');
        $paymentId = '1';
        $amount = '10.00';
        $currency = 'MYR';
        $status = '1';
        $signature = base64_encode(hash('sha1', $merchantKey . $merchantCode . $paymentId . $refNo . '1000' . $currency . $status, true));

        $callbackResponse = $this->post('/api/payments/backend-callback', [
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
        ]);

        $callbackResponse->assertOk();
        $this->assertSame('RECEIVEOK', $callbackResponse->getContent());

        $this->assertDatabaseHas('orders', [
            'order_no' => $refNo,
            'order_status' => 'completed',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_no' => $refNo,
            'transaction_id' => 'TEST-TRANS-1',
            'payment_status' => 1,
        ]);

        $this->assertDatabaseHas('referrals', [
            'user_id' => $referrer->user_id,
            'referee_id' => $dummyUser->user_id,
            'referral_status' => 'qualified',
            'qualifying_order_no' => $refNo,
        ]);

        $payment = Payments::query()->where('order_no', $refNo)->firstOrFail();
        $this->assertSame(10.00, (float) $payment->payment_amount);

        $referral = Referrals::query()
            ->where('user_id', $referrer->user_id)
            ->where('referee_id', $dummyUser->user_id)
            ->firstOrFail();
        $this->assertNotNull($referral->qualified_at);

        $referrer->refresh();
        $this->assertSame(
            0,
            (int) UserReferralGifts::query()->where('user_id', $referrer->user_id)->count()
        );
        $this->assertSame(
            0,
            (int) UserReferralGifts::query()
                ->where('user_id', $referrer->user_id)
                ->whereNotNull('claimed_at')
                ->count()
        );
    }

    public function test_kol_referral_earnings_row_created_and_threshold_applies(): void
    {
        config([
            'services.ipay88.key' => 'test-merchant-key',
            'services.ipay88.code' => 'test-merchant-code',
        ]);

        TransactionTypes::create([
            'transaction_type' => 'account_registration',
            'transaction_name' => 'Account registration',
            'credit_amount' => 0,
            'effective_date' => now()->toDateString(),
            'expire_date' => null,
            'is_active' => true,
        ]);

        TransactionTypes::create([
            'transaction_type' => 'standard_membership',
            'transaction_name' => 'standard_membership',
            'credit_amount' => 0,
            'effective_date' => now()->toDateString(),
            'expire_date' => null,
            'is_active' => true,
        ]);

        $tax = Taxes::create([
            'tax_name' => 'Test Tax',
            'tax_rate' => 0,
            'is_active' => true,
        ]);

        $membershipTypeKol = MembershipTypes::create([
            'membership_type' => 'KOL',
            'is_active' => true,
        ]);

        $membershipTypeFree = MembershipTypes::create([
            'membership_type' => 'Free',
            'is_active' => true,
        ]);

        $membershipTypeStandard = MembershipTypes::create([
            'membership_type' => 'Standard',
            'is_active' => true,
        ]);

        Memberships::create([
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

        $kolMembership = Memberships::create([
            'membership_code' => 'MEMKOL',
            'membership_name' => 'KOL Membership',
            'membership_description' => 'KOL Membership',
            'membership_type_id' => $membershipTypeKol->membership_type_id,
            'membership_type' => 'KOL',
            'membership_price' => 10,
            'duration' => 1,
            'duration_unit' => 'years',
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => null,
            'is_active' => true,
            'sort_order' => 0,
            'best_value' => true,
        ]);

        $standardMembership = Memberships::create([
            'membership_code' => 'MEMSTD2',
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

        $product = Products::create([
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

        $referrer = User::create([
            'first_name' => 'Kol',
            'last_name' => 'User',
            'email' => 'kol@example.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        UserMemberships::create([
            'user_id' => $referrer->user_id,
            'membership_id' => $kolMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        ReferralCodes::create([
            'user_id' => $referrer->user_id,
            'campaign_name' => 'Test Campaign',
            'referral_code' => 'KOLCODE',
            'code_effective_date' => now()->toDateString(),
            'code_expiry_date' => now()->addYear()->toDateString(),
            'usage_count' => 0,
            'max_usage' => 0,
            'is_active' => true,
        ]);

        $registerResponse = $this->postJson('/api/register', [
            'first_name' => 'Dummy',
            'last_name' => 'User',
            'email' => 'dummy-kol@example.com',
            'contact_no' => '0123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => 'KOLCODE',
        ]);

        $registerResponse->assertOk();

        $dummyUser = User::query()->where('email', 'dummy-kol@example.com')->firstOrFail();

        UserMemberships::query()
            ->where('user_id', $dummyUser->user_id)
            ->update(['membership_status' => 'inactive']);

        UserMemberships::create([
            'user_id' => $dummyUser->user_id,
            'membership_id' => $standardMembership->membership_id,
            'membership_start_date' => now()->toDateString(),
            'membership_end_date' => now()->addYear()->toDateString(),
            'membership_status' => 'active',
        ]);

        $now = now();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');

        for ($i = 0; $i < 50; $i++) {
            ReferralEarnings::create([
                'user_id' => $referrer->user_id,
                'referral_id' => (string) \Illuminate\Support\Str::uuid(),
                'month' => $month,
                'year' => $year,
                'amount' => 5,
            ]);
        }

        $this->actingAs($dummyUser, 'sanctum');

        $createPaymentResponse = $this->postJson('/api/payments/create', [
            'amount' => 10.00,
            'subtotal' => 10.00,
            'total_charges' => 0,
            'discount_value' => 0,
            'total_payment' => 10.00,
            'products' => [
                [
                    'product_id' => $product->product_id,
                    'quantity' => 1,
                    'uom' => $product->uom,
                    'unit_price' => 10.00,
                    'tax' => 0,
                    'discount_value' => 0,
                    'amount' => 10.00,
                ],
            ],
        ]);

        $createPaymentResponse->assertOk();
        $refNo = (string) $createPaymentResponse->json('refNo');
        $this->assertNotEmpty($refNo);

        $merchantKey = config('services.ipay88.key');
        $merchantCode = config('services.ipay88.code');
        $paymentId = '1';
        $amount = '10.00';
        $currency = 'MYR';
        $status = '1';
        $signature = base64_encode(hash('sha1', $merchantKey . $merchantCode . $paymentId . $refNo . '1000' . $currency . $status, true));

        $callbackResponse = $this->post('/api/payments/backend-callback', [
            'MerchantCode' => $merchantCode,
            'PaymentId' => $paymentId,
            'RefNo' => $refNo,
            'Amount' => $amount,
            'Currency' => $currency,
            'Status' => $status,
            'Signature' => $signature,
            'TransId' => 'TEST-TRANS-2',
            'TranDate' => now()->format('Y-m-d H:i:s'),
            'S_bankname' => 'Test Bank',
            'CCName' => 'Test Card',
            'CCNo' => '4111111111111111',
        ]);

        $callbackResponse->assertOk();
        $this->assertSame('RECEIVEOK', $callbackResponse->getContent());

        $this->assertDatabaseHas('referral_earnings', [
            'user_id' => $referrer->user_id,
            'month' => $month,
            'year' => $year,
            'amount' => 10,
        ]);
    }
}
