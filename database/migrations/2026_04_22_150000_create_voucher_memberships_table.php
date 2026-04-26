<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_memberships', function (Blueprint $table) {
            $table->id('voucher_membership_id');
            $table->uuid('voucher_id')->index();
            $table->uuid('membership_id')->index();
            $table->timestamps();

            $table->unique(['voucher_id', 'membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_memberships');
    }
};
