<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionTemplate extends Model
{
    use HasUuids;

    protected $table = 'question_templates';
    protected $primaryKey = 'question_template_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'question_label',
        'question_help_text',
        'question_type',
        'is_required_default',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_required_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function eventQuestionnaires(): HasMany
    {
        return $this->hasMany(EventQuestionnaire::class, 'question_template_id', 'question_template_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(EventQuestionOption::class, 'question_template_id', 'question_template_id')
            ->orderBy('sort_order');
    }
}
