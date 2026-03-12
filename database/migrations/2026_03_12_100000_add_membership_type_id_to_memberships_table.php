<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            if (!Schema::hasColumn('memberships', 'membership_type_id')) {
                $table->uuid('membership_type_id')->nullable()->after('membership_description');
            }
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('membership_type_id')
                ->references('membership_type_id')
                ->on('membership_types');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['membership_type_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('membership_type_id');
        });
    }
};
