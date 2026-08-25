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
        Schema::create('product_inventories', function (Blueprint $table) {
            $table->uuid('product_inventory_id')->primary();
            $table->string('product_id');
            $table->string('vendor_location_id');
            $table->integer('quantity')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'vendor_location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_inventories');
    }
};
