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
        Schema::create('user_memberships', function (Blueprint $table) {
            $table->uuid('user_membership_id')->primary();
            $table->uuid('user_id');
            $table->uuid('membership_id');
            $table->date('membership_start_date');
            $table->date('membership_end_date')->nullable();
            $table->string('membership_status', 10)->default('active');
            $table->string('inactive_reason', 255)->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->tinyinteger('max_vouchers')->default(5);
            $table->tinyinteger('redeemed_vouchers_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_memberships');
    }
};
