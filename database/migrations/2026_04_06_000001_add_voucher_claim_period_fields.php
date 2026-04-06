<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('voucher_claim_period', 10)->nullable()->after('voucher_claim_per_user'); // 'week' | 'month'
            $table->integer('voucher_claim_per_period')->nullable()->after('voucher_claim_period');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['voucher_claim_period', 'voucher_claim_per_period']);
        });
    }
};
