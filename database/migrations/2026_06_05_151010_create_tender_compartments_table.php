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
        Schema::create('tender_compartments', function (Blueprint $table) {
            $table->uuid('tender_compartment_id')->primary();
            $table->uuid('compartment_id');
            $table->string('vendor_id');
            $table->decimal('bid_price', 10, 2);
            $table->integer('durations');
            $table->enum('tender_status', ['pending', 'selected', 'paid', 'expired', 'rejected'])->default('pending');
            $table->datetime('selected_at')->nullable();
            $table->datetime('unallocated_at')->nullable();
            $table->string('unallocated_by')->nullable();
            $table->string('unallocated_reason')->nullable();
            $table->datetime('tender_start_date')->nullable();
            $table->datetime('tender_end_date')->nullable();
            $table->string('product_description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_compartments');
    }
};
