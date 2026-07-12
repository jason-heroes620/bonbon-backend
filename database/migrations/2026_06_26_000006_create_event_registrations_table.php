<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuid('event_registration_id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('user_id')->index();
            $table->uuid('cart_item_id')->nullable()->index();
            $table->uuid('order_id')->nullable()->index();
            $table->uuid('payment_id')->nullable()->index();
            $table->enum('registration_status', ['draft', 'pending_payment', 'confirmed', 'expired', 'cancelled'])->index();
            $table->dateTime('seat_hold_expires_at')->nullable()->index();
            $table->string('membership_type_at_registration', 100)->nullable();
            $table->decimal('price_before_discount', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('price_paid', 10, 2);
            $table->dateTime('joined_at')->index();
            $table->dateTime('confirmed_at')->nullable()->index();
            $table->dateTime('expired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['event_id', 'registration_status'], 'idx_er_event_status');
            $table->index(['user_id', 'registration_status'], 'idx_er_user_status');
            $table->index(['event_id', 'registration_status', 'seat_hold_expires_at'], 'idx_er_event_seat_hold');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
