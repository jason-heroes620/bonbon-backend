<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('registration_type', ['free', 'paid'])->default('free')->after('place_id');
            $table->decimal('base_price', 10, 2)->default(0.00)->after('registration_type');
            $table->boolean('is_unlimited_seats')->default(true)->after('base_price');
            $table->integer('seat_limit')->nullable()->after('is_unlimited_seats');
            $table->integer('seat_hold_minutes')->default(15)->after('seat_limit');
            $table->dateTime('rsvp_open_at')->nullable()->after('seat_hold_minutes');
            $table->dateTime('rsvp_close_at')->nullable()->after('rsvp_open_at');
            $table->boolean('require_questionnaire')->default(false)->after('rsvp_close_at');

            $table->index(['registration_type'], 'idx_events_registration_type');
            $table->index(['is_active', 'is_published', 'event_start_date'], 'idx_events_active_published_start');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_active_published_start');
            $table->dropIndex('idx_events_registration_type');

            $table->dropColumn([
                'registration_type',
                'base_price',
                'is_unlimited_seats',
                'seat_limit',
                'seat_hold_minutes',
                'rsvp_open_at',
                'rsvp_close_at',
                'require_questionnaire',
            ]);
        });
    }
};

