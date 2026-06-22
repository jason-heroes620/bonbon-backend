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
        Schema::create('racks', function (Blueprint $table) {
            $table->uuid('rack_id')->primary();
            $table->uuid('vendor_location_id');
            $table->string('rack_name');
            $table->string('rack_type')->nullable();
            $table->string('rack_capacity')->nullable();
            $table->string('rack_rows');
            $table->string('rack_columns');
            $table->enum('rack_status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
