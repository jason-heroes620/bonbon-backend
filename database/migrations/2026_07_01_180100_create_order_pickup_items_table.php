<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pickup_items', function (Blueprint $table) {
            $table->uuid('order_pickup_item_id')->primary();
            $table->uuid('order_pickup_id')->index();
            $table->uuid('order_item_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('compartment_stock_id')->index();
            $table->uuid('compartment_stock_product_id')->index();
            $table->uuid('rack_id')->nullable()->index();
            $table->uuid('compartment_id')->nullable()->index();
            $table->integer('ordered_quantity');
            $table->integer('picked_up_quantity')->default(0);
            $table->string('product_name', 255);
            $table->string('vendor_name', 255)->nullable();
            $table->string('vendor_location_name', 255)->nullable();
            $table->string('rack_name', 255)->nullable();
            $table->string('compartment_name', 255)->nullable();
            $table->timestamps();

            $table->unique(['order_pickup_id', 'order_item_id'], 'uq_order_pickup_items_pickup_order_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pickup_items');
    }
};
