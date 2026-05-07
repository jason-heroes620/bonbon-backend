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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('voucher_id')->primary();
            $table->uuid('vendor_id');
            $table->string('voucher_name', 200);
            $table->string('voucher_short_description', 100)->nullable();
            $table->text('voucher_description')->nullable();
            $table->string('voucher_value', 200)->nullable();
            $table->text('what_you_get')->nullable();
            $table->string('voucher_code');
            $table->decimal('voucher_discount', 8, 2)->nullable();
            $table->string('voucher_type', 100)->nullable();
            $table->date('voucher_start_date');
            $table->date('voucher_expiry_date');
            $table->integer('voucher_limit')->default(0);
            $table->integer('voucher_claim_per_user')->default(1);
            $table->string('voucher_image_path')->nullable();
            $table->boolean('voucher_status')->default(false);
            $table->boolean('is_unlimited')->default(false);
            $table->text('tnc')->nullable();
            $table->text('how_to_use')->nullable();
            $table->integer('voucher_claim_points')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
