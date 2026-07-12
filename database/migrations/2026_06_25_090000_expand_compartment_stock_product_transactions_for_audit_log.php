<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('compartment_stock_product_transactions', 'transaction_type')) {
            DB::statement("ALTER TABLE compartment_stock_product_transactions MODIFY transaction_type VARCHAR(50) NOT NULL");
        }

        if (Schema::hasColumn('compartment_stock_product_transactions', 'transaction_status')) {
            DB::statement("ALTER TABLE compartment_stock_product_transactions MODIFY transaction_status VARCHAR(30) NOT NULL");
        }

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('compartment_stock_product_transactions', 'quantity_delta')) {
                $table->integer('quantity_delta')->nullable()->after('received_quantity');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'actor_user_id')) {
                $table->uuid('actor_user_id')->nullable()->after('quantity_delta');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'actor_vendor_id')) {
                $table->uuid('actor_vendor_id')->nullable()->after('actor_user_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'event_source')) {
                $table->string('event_source', 50)->nullable()->after('actor_vendor_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'event_source_id')) {
                $table->uuid('event_source_id')->nullable()->after('event_source');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'vendor_location_id')) {
                $table->integer('vendor_location_id')->nullable()->after('event_source_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'rack_id')) {
                $table->uuid('rack_id')->nullable()->after('vendor_location_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'compartment_id')) {
                $table->uuid('compartment_id')->nullable()->after('rack_id');
            }

            if (!Schema::hasColumn('compartment_stock_product_transactions', 'product_id')) {
                $table->uuid('product_id')->nullable()->after('compartment_id');
            }
        });

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('compartment_stock_product_transactions', 'event_source') &&
                Schema::hasColumn('compartment_stock_product_transactions', 'event_source_id')) {
                $table->index(['event_source', 'event_source_id'], 'idx_cspt_event_source');
            }

            if (Schema::hasColumn('compartment_stock_product_transactions', 'compartment_id') &&
                Schema::hasColumn('compartment_stock_product_transactions', 'product_id') &&
                Schema::hasColumn('compartment_stock_product_transactions', 'confirmed_at')) {
                $table->index(['compartment_id', 'product_id', 'confirmed_at'], 'idx_cspt_compartment_product_confirmed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_cspt_event_source');
            $table->dropIndex('idx_cspt_compartment_product_confirmed_at');
        });

        Schema::table('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_delta',
                'actor_user_id',
                'actor_vendor_id',
                'event_source',
                'event_source_id',
                'vendor_location_id',
                'rack_id',
                'compartment_id',
                'product_id',
            ]);
        });
    }
};

