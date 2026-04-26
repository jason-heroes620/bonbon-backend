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
        Schema::create('lucky_draw_winners', function (Blueprint $table) {
            $table->id();
            $table->integer('session_id')->unsigned()->autoIncrement(false);
            $table->string('user_id');
            $table->string('email');
            $table->integer('winning_ticket_number')->unsigned()->autoIncrement(false);
            $table->timestamp('won_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lucky_draw_winners');
    }
};
