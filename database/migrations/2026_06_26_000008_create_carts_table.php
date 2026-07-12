<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('cart_id')->primary();
            $table->uuid('user_id')->index();
            $table->enum('cart_status', ['active', 'checked_out', 'abandoned'])->index();
            $table->char('currency_code', 3)->default('MYR');
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'cart_status'], 'idx_carts_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

