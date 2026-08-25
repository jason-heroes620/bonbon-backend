<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->uuid('applied_user_voucher_id')->nullable()->after('expires_at');
            $table->uuid('applied_voucher_id')->nullable()->after('applied_user_voucher_id');
            $table->boolean('voucher_auto_apply_disabled')->default(false)->after('applied_voucher_id');

            $table->index('applied_user_voucher_id', 'idx_carts_applied_user_voucher');
            $table->index('applied_voucher_id', 'idx_carts_applied_voucher');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('applied_user_voucher_id')->nullable()->after('discount_code');
            $table->uuid('applied_voucher_id')->nullable()->after('applied_user_voucher_id');
            $table->decimal('applied_voucher_discount', 10, 2)->default(0)->after('applied_voucher_id');
            $table->dateTime('voucher_redeemed_at')->nullable()->after('applied_voucher_discount');

            $table->index('applied_user_voucher_id', 'idx_orders_applied_user_voucher');
            $table->index('applied_voucher_id', 'idx_orders_applied_voucher');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_applied_user_voucher');
            $table->dropIndex('idx_orders_applied_voucher');
            $table->dropColumn([
                'applied_user_voucher_id',
                'applied_voucher_id',
                'applied_voucher_discount',
                'voucher_redeemed_at',
            ]);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex('idx_carts_applied_user_voucher');
            $table->dropIndex('idx_carts_applied_voucher');
            $table->dropColumn([
                'applied_user_voucher_id',
                'applied_voucher_id',
                'voucher_auto_apply_disabled',
            ]);
        });
    }
};
