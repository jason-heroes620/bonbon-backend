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
        Schema::create('lucky_draw_session', function (Blueprint $table) {
            $table->id();
            $table->string('session_name');
            $table->enum('session_status', ['pending', 'completed']);
            $table->smallInteger('winners_count', 3)->default(0)->unsigned()->autoIncrement(false);
            $table->datetime('session_start_time');
            $table->datetime('session_end_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lucky_draw_session');
    }
};
