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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->integer('credit_amount')->unsigned()->autoIncrement(false);
            $table->string('transaction_type', 100); // registration, membership, check_in etc
            $table->string('reference_id', 36)->nullable(); // ID of the related model (e.g., event_id or voucher_id)
            $table->string('reference_type', 100)->nullable(); // The model class name (e.g., Event, Voucher)
            $table->string('transaction_description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
