<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventQuestionnaire extends Model
{
    use HasUuids;

    protected $table = 'event_questionnaires';
    protected $primaryKey = 'event_questionnaire_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'question_template_id',
        'question_label_snapshot',
        'question_help_text_snapshot',
        'question_type_snapshot',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Events::class, 'event_id', 'event_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuestionTemplate::class, 'question_template_id', 'question_template_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(EventQuestionOption::class, 'event_questionnaire_id', 'event_questionnaire_id');
    }
}

