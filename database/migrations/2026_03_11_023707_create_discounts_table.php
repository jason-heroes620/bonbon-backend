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
        Schema::create('discounts', function (Blueprint $table) {
            $table->uuid('discount_id')->primary();
            $table->string('discount_code', 10);
            $table->string('discount_name', 150);
            $table->string('discount_description', 250);
            $table->enum('discount_type', ['P', 'F']);
            $table->decimal('discount_amount', 10, 2);
            $table->date('discount_start_date');
            $table->date('discount_end_date');
            $table->boolean('is_active')->default(true);
            $table->integer('discount_usage_limit', 5)->default(0)->unsigned()->autoIncrement(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
