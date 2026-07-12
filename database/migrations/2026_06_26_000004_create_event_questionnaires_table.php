<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_questionnaires', function (Blueprint $table) {
            $table->uuid('event_questionnaire_id')->primary();
            $table->uuid('event_id')->index();
            $table->uuid('question_template_id')->nullable()->index();
            $table->string('question_label_snapshot', 255);
            $table->text('question_help_text_snapshot')->nullable();
            $table->enum('question_type_snapshot', ['short_text', 'long_text', 'single_select', 'multi_select', 'yes_no'])->index();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['event_id', 'is_active', 'sort_order'], 'idx_eq_event_active_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_questionnaires');
    }
};

