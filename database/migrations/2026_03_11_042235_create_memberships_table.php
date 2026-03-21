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
        if (Schema::hasTable('memberships')) {
            return;
        }

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('membership_id')->primary();
            $table->string('membership_code', 20);
            $table->string('membership_name', 100);
            $table->string('membership_description', 255)->nullable();
            $table->string('membership_type', 10);
            $table->decimal('membership_price', 10, 2);
            $table->integer('duration', 4)->autoIncrement(false);
            $table->enum('duration_unit', ['days', 'months', 'years']);
            $table->date('membership_start_date');
            $table->date('membership_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('sort_order', 3)->autoIncrement(false);
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
