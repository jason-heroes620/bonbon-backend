<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('mobile_image_url')->nullable()->after('image_path');
            $table->string('mobile_image_path')->nullable()->after('mobile_image_url');
            $table->unsignedInteger('image_width')->nullable()->after('is_primary');
            $table->unsignedInteger('image_height')->nullable()->after('image_width');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('image_height');
            $table->unsignedBigInteger('mobile_file_size_bytes')->nullable()->after('file_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_image_url',
                'mobile_image_path',
                'image_width',
                'image_height',
                'file_size_bytes',
                'mobile_file_size_bytes',
            ]);
        });
    }
};
