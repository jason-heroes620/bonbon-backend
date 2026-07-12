<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_answers', function (Blueprint $table) {
            $table->uuid('event_registration_answer_id')->primary();
            $table->uuid('event_registration_id')->index();
            $table->uuid('event_questionnaire_id')->index();
            $table->uuid('event_question_option_id')->nullable()->index();
            $table->text('answer_text')->nullable();
            $table->string('answer_value', 255)->nullable();
            $table->timestamps();

            $table->index(['event_registration_id', 'event_questionnaire_id'], 'idx_era_registration_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_answers');
    }
};

