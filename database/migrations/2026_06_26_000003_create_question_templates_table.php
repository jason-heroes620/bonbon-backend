<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_templates', function (Blueprint $table) {
            $table->uuid('question_template_id')->primary();
            $table->string('question_label', 255);
            $table->text('question_help_text')->nullable();
            $table->enum('question_type', ['short_text', 'long_text', 'single_select', 'multi_select', 'yes_no'])->index();
            $table->boolean('is_required_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by_user_id')->index();
            $table->timestamps();

            $table->index(['is_active', 'question_type'], 'idx_qt_active_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_templates');
    }
};

