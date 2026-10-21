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
        Schema::create('gallery_image_users', function (Blueprint $table) {
            $table->uuid('gallery_image_user_id')->primary();
            $table->uuid('gallery_image_id');
            $table->uuid('user_id')->nullable();
            $table->string('email')->index();
            $table->uuid('tagged_by')->nullable();
            $table->timestamps();

            $table->unique(['gallery_image_id', 'email'], 'idx_giu_unique_image_email');
            $table->index(['user_id', 'gallery_image_id'], 'idx_giu_user_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_image_users');
    }
};
