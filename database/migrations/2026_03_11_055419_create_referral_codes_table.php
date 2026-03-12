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
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->uuid('referral_code_id')->primary();
            $table->uuid('user_id')->notNullable();
            $table->string('campaign_name', 200);
            $table->string('referral_code', 50);
            $table->date('code_effective_date');
            $table->date('code_expiry_date')->nullable();
            $table->integer('usage_count')->default(0)->autoIncrement(false);
            $table->integer('max_usage')->default(0)->autoIncrement(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
