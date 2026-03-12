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
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('membership_id')->primary();
            $table->string('membership_name');
            $table->enum('membership_type', ['monthly', 'yearly']);
            $table->decimal('membership_price', 10, 2);
            $table->integer('duration');
            $table->enum('duration_unit', ['days', 'months', 'years']);
            $table->date('membership_start_date');
            $table->date('membership_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order');
            $table->boolean('best_value')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
