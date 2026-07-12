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
        Schema::create('compartment_stock_product_transactions', function (Blueprint $table) {
            $table->uuid('compartment_stock_product_transaction_id')->primary();
            $table->integer('transaction_quantity', 4)->autoIncrement(false);
            $table->string('description', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartment_stock_product_transactions');
    }
};
