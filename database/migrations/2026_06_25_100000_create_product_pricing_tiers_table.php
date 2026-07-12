<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_pricing_tiers', function (Blueprint $table) {
            $table->uuid('product_pricing_tier_id')->primary();
            $table->uuid('product_id');
            $table->enum('pricing_mode', ['unit_price', 'percentage_discount']);
            $table->integer('min_qty')->default(1)->autoIncrement(false);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'min_qty'], 'idx_ppt_product_active_min_qty');
            $table->unique(['product_id', 'min_qty'], 'uq_ppt_product_min_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_pricing_tiers');
    }
};
