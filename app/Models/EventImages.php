<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventImages extends Model
{
    //
    protected $table = 'event_images';
    protected $primaryKey = 'event_image_id';
    protected $fillable = [
        'event_id',
        'event_image_path',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
