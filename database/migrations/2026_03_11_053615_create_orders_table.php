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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->uuid('user_id');
            $table->string('order_no', 20);
            $table->date('order_date');
            $table->decimal('total_price', 10, 2);
            $table->decimal('total_tax', 10, 2);
            $table->decimal('total_discount', 10, 2);
            $table->decimal('total_payment', 10, 2);
            $table->string('shipping_method', 50);
            $table->string('shipping_address', 255);
            $table->string('billing_address', 255);
            $table->string('discount_code', 50)->nullable();
            $table->decimal('wallet_credit_used', 10, 2)->default(0.00);
            $table->enum('order_status', ['pending', 'processing', 'shipped', 'completed', 'refunded'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
