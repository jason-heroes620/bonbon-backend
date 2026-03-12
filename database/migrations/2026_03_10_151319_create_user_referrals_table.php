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
        Schema::create('user_referrals', function (Blueprint $table) {
            $table->integer('user_referral_id')->primary();
            $table->uuid('user_id');
            $table->uuid('referral_user_id');
            $table->string('referral_code', 10);
            $table->date('referral_date');
            $table->integer('cycle', 3)->default(1)->unsigned()->autoIncrement(false);
            $table->enum('referral_status', ['pending', 'qualified', 'rewarded', 'revoked'])->default('pending');
            $table->uuid('qualifying_order_id', 36)->nullable();
            $table->date('qualfied_at', 36)->nullable();
            $table->date('rewarded_at', 36)->nullable();
            $table->date('revoked_at', 36)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_referrals');
    }
};
