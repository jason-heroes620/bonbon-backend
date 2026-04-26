<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCheckIn extends Model
{
    protected $table = 'event_check_ins';
    protected $primaryKey = 'event_check_in_id';
    protected $fillable = [
        'user_id',
        'event_id',
    ];
}
