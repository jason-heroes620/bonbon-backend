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
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('vendor_id')->primary();
            $table->string('vendor_name', 150);
            $table->uuid('user_id');
            $table->string('email', 200)->unique();
            $table->string('contact_no', 25);
            $table->string('first_name', 150);
            $table->string('last_name', 150);
            $table->string('business_registration_number', 100);
            $table->text('company_profile')->nullable();
            $table->text('our_services')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('website', 200)->nullable();
            $table->json('social_medias')->nullable();
            $table->enum('is_active', ['active', 'inactive'])->default('inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
