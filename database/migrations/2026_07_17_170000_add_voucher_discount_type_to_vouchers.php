<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vouchers', 'voucher_discount_type')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->string('voucher_discount_type', 1)
                    ->nullable()
                    ->after('voucher_discount');
            });
        }

        if (Schema::hasColumn('vouchers', 'voucher_type')) {
            DB::table('vouchers')
                ->whereNull('voucher_discount_type')
                ->whereIn('voucher_type', ['F', 'P'])
                ->update([
                    'voucher_discount_type' => DB::raw('voucher_type'),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vouchers', 'voucher_discount_type')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('voucher_discount_type');
            });
        }
    }
};
