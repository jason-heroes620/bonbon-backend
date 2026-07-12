<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Events extends Model
{
    use HasUuids;

    protected $table = 'events';
    protected $primaryKey = 'event_id';

    protected $fillable = [
        'event_name',
        'event_start_date',
        'event_end_date',
        'event_start_time',
        'event_end_time',
        'event_location',
        'event_description',
        'location_name',
        'location_latitude',
        'location_longitude',
        'place_id',
        'registration_type',
        'base_price',
        'is_unlimited_seats',
        'seat_limit',
        'seat_hold_minutes',
        'rsvp_open_at',
        'rsvp_close_at',
        'require_questionnaire',
        'is_published',
        'is_active',
        'event_image_path',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_published' => 'boolean',
        'is_active' => 'boolean',
        'is_unlimited_seats' => 'boolean',
        'require_questionnaire' => 'boolean',
        'base_price' => 'decimal:2',
        'rsvp_open_at' => 'datetime',
        'rsvp_close_at' => 'datetime',
    ];

    public function event_images()
    {
        return $this->hasMany(EventImages::class, 'event_id', 'event_id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(EventPricingRule::class, 'event_id', 'event_id');
    }

    public function questionnaires(): HasMany
    {
        return $this->hasMany(EventQuestionnaire::class, 'event_id', 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id', 'event_id');
    }
}
