<?php

namespace Tests\Feature;

use App\Models\Memberships;
use App\Models\MembershipTypes;
use App\Models\Payments;
use App\Models\Products;
use App\Models\ReferralCodes;
use App\Models\Referrals;
use App\Models\Taxes;
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
            'total_tax' => 0,
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
        $signature = base64_encode(hex2bin(hash('sha1', $merchantKey . $merchantCode . $paymentId . $refNo . '1000' . $currency . $status)));

        $callbackResponse = $this->post('/api/payments/backend-callback', [
            'MerchantCode' => $merchantCode,
            'PaymentId' => $paymentId,
            'RefNo' => $refNo,
            'Amount' => $amount,
            'Currency' => $currency,
            'Status' => $status,
            'Signature' => $signature,
            'TransId' => 'TEST-TRANS-1',
            'TransDate' => now()->format('Y-m-d H:i:s'),
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
}
