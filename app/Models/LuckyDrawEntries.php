<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawEntries extends Model
{
    protected $table = 'lucky_draw_entries';
    protected $primaryKey = 'id';
    protected $fillable = ['session_id', 'user_id', 'email', 'weight', 'is_winner'];
    public $timestamps = true;
}
