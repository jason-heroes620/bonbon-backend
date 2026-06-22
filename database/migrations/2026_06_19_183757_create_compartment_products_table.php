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
        Schema::create('compartment_stock_products', function (Blueprint $table) {
            $table->uuid('compartment_stock_product_id')->primary();
            $table->uuid('compartment_stock_id');
            $table->uuid('product_id');
            $table->date('expiry_date')->nullable();
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartment_products');
    }
};
