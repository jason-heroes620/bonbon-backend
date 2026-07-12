<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('confirmed_at')->index();
            }

            if (!Schema::hasColumn('event_registrations', 'checked_in_by_user_id')) {
                $table->uuid('checked_in_by_user_id')->nullable()->after('checked_in_at')->index();
            }

            if (!Schema::hasColumn('event_registrations', 'check_in_source')) {
                $table->string('check_in_source', 50)->nullable()->after('checked_in_by_user_id');
            }
        });

        Schema::table('event_check_ins', function (Blueprint $table) {
            if (!Schema::hasColumn('event_check_ins', 'event_registration_id')) {
                $table->uuid('event_registration_id')->nullable()->after('event_id');
                $table->unique('event_registration_id', 'uq_event_check_ins_registration');
            }

            if (!Schema::hasColumn('event_check_ins', 'checked_in_by_user_id')) {
                $table->uuid('checked_in_by_user_id')->nullable()->after('event_registration_id')->index();
            }

            if (!Schema::hasColumn('event_check_ins', 'check_in_source')) {
                $table->string('check_in_source', 50)->nullable()->after('checked_in_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_check_ins', function (Blueprint $table) {
            if (Schema::hasColumn('event_check_ins', 'check_in_source')) {
                $table->dropColumn('check_in_source');
            }

            if (Schema::hasColumn('event_check_ins', 'checked_in_by_user_id')) {
                $table->dropColumn('checked_in_by_user_id');
            }

            if (Schema::hasColumn('event_check_ins', 'event_registration_id')) {
                $table->dropUnique('uq_event_check_ins_registration');
                $table->dropColumn('event_registration_id');
            }
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('event_registrations', 'check_in_source')) {
                $table->dropColumn('check_in_source');
            }

            if (Schema::hasColumn('event_registrations', 'checked_in_by_user_id')) {
                $table->dropColumn('checked_in_by_user_id');
            }

            if (Schema::hasColumn('event_registrations', 'checked_in_at')) {
                $table->dropColumn('checked_in_at');
            }
        });
    }
};
