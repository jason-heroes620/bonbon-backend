<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'requires_delivery_fee')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('requires_delivery_fee')->default(true)->after('delivery');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'requires_delivery_fee')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('requires_delivery_fee');
            });
        }
    }
};
