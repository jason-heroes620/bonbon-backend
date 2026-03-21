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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_name', 100);
            $table->string('event_image_path', 255)->nullable();
            $table->date('event_start_date');
            $table->date('event_end_date');
            $table->time('event_start_time');
            $table->time('event_end_time');
            $table->string('event_location', 100);
            $table->text('event_description');
            $table->string('location_name', 150);
            $table->decimal('location_latitude', 10, 8);
            $table->decimal('location_longitude', 11, 8);
            $table->string('place_id', 255);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
