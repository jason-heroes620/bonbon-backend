<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventQuestionOption extends Model
{
    use HasUuids;

    protected $table = 'event_question_options';
    protected $primaryKey = 'event_question_option_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'event_questionnaire_id',
        'question_template_id',
        'option_label',
        'option_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(EventQuestionnaire::class, 'event_questionnaire_id', 'event_questionnaire_id');
    }

    public function questionTemplate(): BelongsTo
    {
        return $this->belongsTo(QuestionTemplate::class, 'question_template_id', 'question_template_id');
    }
}
