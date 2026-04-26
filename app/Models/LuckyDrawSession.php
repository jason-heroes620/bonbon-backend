<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LuckyDrawSession extends Model
{
    use HasUuids;

    protected $table = 'lucky_draw_session';
    protected $primaryKey = 'id';
    protected $fillable = ['session_name', 'session_status', 'winners_count', 'session_start_time', 'session_end_time'];

    public $timestamps = true;
}
