<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_question_options', function (Blueprint $table) {
            $table->uuid('question_template_id')->nullable()->after('event_questionnaire_id')->index();
            $table->uuid('event_questionnaire_id')->nullable()->change();
            $table->unique(
                ['question_template_id', 'option_value'],
                'uq_eqo_template_value'
            );
            $table->index(
                ['question_template_id', 'is_active', 'sort_order'],
                'idx_eqo_template_active_order'
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_question_options', function (Blueprint $table) {
            $table->dropIndex('idx_eqo_template_active_order');
            $table->dropUnique('uq_eqo_template_value');
            $table->dropColumn('question_template_id');
            $table->uuid('event_questionnaire_id')->nullable(false)->change();
        });
    }
};
