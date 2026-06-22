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
        Schema::create('compartment_stocks', function (Blueprint $table) {
            $table->uuid('compartment_stock_id')->primary();
            $table->uuid('tender_compartment_id');
            $table->enum('status', ['prepared', 'completed', 'remove'])->default('prepared');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartment_stocks');
    }
};
