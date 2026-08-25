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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->uuid('vendor_id')->nullable();
            $table->string('product_name', 150);
            $table->string('product_code', 50)->nullable();
            $table->string('product_sku', 100)->nullable();
            $table->text('product_description');
            $table->integer('stock_quantity')->default(0)->autoIncrement(false);
            $table->string('uom', 50)->default('unit');
            $table->decimal('product_weight', 10, 2)->nullable();
            $table->decimal('product_length', 10, 2)->nullable();
            $table->decimal('product_width', 10, 2)->nullable();
            $table->decimal('product_height', 10, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_taxable')->default(false);
            $table->uuid('tax_rate_id')->notNullable();
            $table->decimal('retail_price', 10, 2)->default(0.00);
            $table->decimal('sale_price', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('delivery')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
