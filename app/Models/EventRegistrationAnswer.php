<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationAnswer extends Model
{
    use HasUuids;

    protected $table = 'event_registration_answers';
    protected $primaryKey = 'event_registration_answer_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'event_registration_id',
        'event_questionnaire_id',
        'event_question_option_id',
        'answer_text',
        'answer_value',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id', 'event_registration_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EventQuestionnaire::class, 'event_questionnaire_id', 'event_questionnaire_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(EventQuestionOption::class, 'event_question_option_id', 'event_question_option_id');
    }
}

