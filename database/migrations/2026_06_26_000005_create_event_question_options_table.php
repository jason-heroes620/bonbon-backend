<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_question_options', function (Blueprint $table) {
            $table->uuid('event_question_option_id')->primary();
            $table->uuid('event_questionnaire_id')->index();
            $table->string('option_label', 255);
            $table->string('option_value', 100);
            $table->integer('sort_order');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['event_questionnaire_id', 'is_active', 'sort_order'], 'idx_eqo_question_active_order');
            $table->unique(['event_questionnaire_id', 'option_value'], 'uq_eqo_question_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_question_options');
    }
};

