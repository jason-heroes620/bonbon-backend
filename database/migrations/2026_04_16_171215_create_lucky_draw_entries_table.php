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
        Schema::create('lucky_draw_entries', function (Blueprint $table) {
            $table->id();
            $table->integer('session_id')->unsigned()->autoIncrement(false);
            $table->string('user_id');
            $table->string('email');
            $table->tinyInteger('weight', 2)->unsigned()->autoIncrement(false);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lucky_draw_entries');
    }
};
