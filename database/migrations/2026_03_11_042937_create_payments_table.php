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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('payment_id')->primary();
            $table->uuid('order_id', 36);
            $table->string('order_no', 50);
            $table->string('transaction_id', 50);
            $table->string('ref_no', 50);
            $table->text('payment_description');
            $table->string('payment_method', 50);
            $table->decimal('payment_amount', 10, 2);
            $table->date('payment_date');
            $table->string('issuing_bank', 150);
            $table->string('payment_ref', 50);
            $table->string('bank_ref', 50);
            $table->string('cc_name', 200);
            $table->string('cc_number', 50);
            $table->integer('payment_status', 3)->autoIncrement(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
