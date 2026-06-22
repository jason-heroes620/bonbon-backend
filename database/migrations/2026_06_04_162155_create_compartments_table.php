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
        Schema::create('compartments', function (Blueprint $table) {
            $table->uuid('compartment_id')->primary();
            $table->uuid('rack_id');
            $table->string('label');
            $table->unsignedTinyInteger('row_index')->default(1);
            $table->unsignedTinyInteger('column_index')->default(1);
            $table->string('size_dimensions')->nullable();
            $table->decimal('min_price', 10, 2)->default(0.00);
            $table->unsignedTinyInteger('min_month')->default(6);
            $table->enum('compartment_status', ['open', 'reviewing', 'allocated', 'closed'])->default('open');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compartments');
    }
};
