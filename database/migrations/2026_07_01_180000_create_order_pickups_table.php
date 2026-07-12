<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pickups', function (Blueprint $table) {
            $table->uuid('order_pickup_id')->primary();
            $table->uuid('order_id')->index();
            $table->uuid('user_id')->index();
            $table->uuid('vendor_id')->index();
            $table->integer('vendor_location_id')->index();
            $table->string('fulfillment_method', 30)->default('pickup');
            $table->string('pickup_status', 30)->default('pending_pickup')->index();
            $table->string('pickup_code', 64)->unique();
            $table->json('pickup_payload_json')->nullable();
            $table->string('pickup_signature_hash', 64)->nullable()->index();
            $table->dateTime('qr_issued_at')->nullable();
            $table->dateTime('qr_expires_at')->nullable();
            $table->dateTime('scanned_at')->nullable();
            $table->uuid('scanned_by_user_id')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->uuid('picked_up_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'pickup_status', 'created_at'], 'idx_order_pickups_user_status_created');
            $table->index(['vendor_id', 'vendor_location_id', 'pickup_status'], 'idx_order_pickups_vendor_location_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pickups');
    }
};
