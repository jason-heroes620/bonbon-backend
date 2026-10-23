<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes backing the product listing endpoint (Api\ProductsController::index).
     * They already existed on the primary database, so each block is guarded to keep
     * this migration idempotent on environments where they are already present.
     *
     * @var array<int, array{table: string, name: string, columns: array<int, string>}>
     */
    private array $indexes = [
        'product_images' => [
            'table' => 'product_images',
            'name' => 'idx_product_images_product_flags',
            'columns' => ['product_id', 'is_primary', 'is_active'],
        ],
        'compartment_stock_products' => [
            'table' => 'compartment_stock_products',
            'name' => 'idx_csp_stock',
            'columns' => ['compartment_stock_id'],
        ],
        'products' => [
            'table' => 'products',
            'name' => 'idx_products_listing',
            'columns' => ['is_active', 'is_visible', 'delivery'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            $table = $index['table'];
            $name = $index['name'];

            if (!Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name, $index) {
                $blueprint->index($index['columns'], $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            $table = $index['table'];
            $name = $index['name'];

            if (!Schema::hasTable($table) || !Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }
};
