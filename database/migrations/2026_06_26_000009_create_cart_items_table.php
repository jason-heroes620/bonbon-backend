<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('cart_item_id')->primary();
            $table->uuid('cart_id')->index();
            $table->enum('line_type', ['product', 'event'])->index();
            $table->uuid('source_id')->index();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['cart_id'], 'idx_cart_items_cart');
            $table->index(['line_type', 'source_id'], 'idx_cart_items_type_source');
            $table->unique(['cart_id', 'line_type', 'source_id'], 'uq_cart_items_cart_type_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

