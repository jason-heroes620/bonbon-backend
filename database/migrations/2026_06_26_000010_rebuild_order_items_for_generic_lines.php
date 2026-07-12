<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            return;
        }

        Schema::create('order_items_new', function (Blueprint $table) {
            $table->uuid('order_item_id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('product_id')->nullable();
            $table->enum('line_type', ['product', 'event'])->default('product')->index();
            $table->uuid('source_id')->nullable()->index();
            $table->string('line_name', 255)->default('');
            $table->text('line_description')->nullable();
            $table->integer('quantity');
            $table->string('uom', 50);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->index(['order_id'], 'idx_order_items_order');
            $table->index(['line_type', 'source_id'], 'idx_order_items_type_source');
        });

        DB::statement("
            INSERT INTO order_items_new
                (order_item_id, order_id, product_id, line_type, source_id, line_name, line_description, quantity, uom, unit_price, tax, discount, total_price, created_at, updated_at)
            SELECT
                order_item_id,
                order_id,
                product_id,
                'product' as line_type,
                product_id as source_id,
                '' as line_name,
                null as line_description,
                quantity,
                uom,
                unit_price,
                tax,
                discount,
                total_price,
                created_at,
                updated_at
            FROM order_items
        ");

        Schema::rename('order_items', 'order_items_legacy');
        Schema::rename('order_items_new', 'order_items');
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_items_legacy')) {
            return;
        }

        Schema::dropIfExists('order_items');
        Schema::rename('order_items_legacy', 'order_items');
    }
};
