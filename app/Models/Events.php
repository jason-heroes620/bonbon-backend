<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
        'is_published',
        'is_active',
        'event_image_path',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_published' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function event_images()
    {
        return $this->hasMany(EventImages::class, 'event_id', 'event_id');
    }
}
