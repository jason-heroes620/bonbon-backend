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
        Schema::create('user_pets', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->string('pet_name');
            $table->string('pet_type')->nullable();
            $table->string('pet_breed')->nullable();
            $table->date('pet_birth_date')->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('allergy_notes')->nullable();
            $table->string('pet_image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_pets');
    }
};
