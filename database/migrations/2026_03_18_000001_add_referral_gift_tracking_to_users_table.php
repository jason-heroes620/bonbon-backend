<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referral_gifts_earned')) {
                $table->unsignedTinyInteger('referral_gifts_earned')->default(0);
            }

            if (!Schema::hasColumn('users', 'referral_gifts_claimed')) {
                $table->unsignedTinyInteger('referral_gifts_claimed')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referral_gifts_earned')) {
                $table->dropColumn('referral_gifts_earned');
            }

            if (Schema::hasColumn('users', 'referral_gifts_claimed')) {
                $table->dropColumn('referral_gifts_claimed');
            }
        });
    }
};

