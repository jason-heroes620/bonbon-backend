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
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->uuid('gallery_image_id')->primary();
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->string('original_path');
            $table->string('original_url');
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('optimized_path')->nullable();
            $table->string('optimized_url')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('image_width')->nullable();
            $table->integer('image_height')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_gallery_images_status_created');
            $table->index('is_active', 'idx_gallery_images_active');
            $table->index('uploaded_by', 'idx_gallery_images_uploader');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
