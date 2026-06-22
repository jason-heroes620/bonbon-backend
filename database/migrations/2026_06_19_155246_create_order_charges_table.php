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
        Schema::create('order_charges', function (Blueprint $table) {
            $table->uuid('order_charge_id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('charge_id')->index();
            $table->string('charge_name');
            $table->string('charge_type', 1);
            $table->decimal('charge_rate', 10, 2);
            $table->decimal('charge_amount', 10, 2);
            $table->tinyInteger('sort_order', 2)->autoIncrement(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_charges');
    }
};
