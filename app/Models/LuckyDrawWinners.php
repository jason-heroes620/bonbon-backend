<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawWinners extends Model
{
    protected $table = 'lucky_draw_winners';
    protected $primaryKey = 'id';
    protected $fillable = ['session_id', 'user_id', 'email', 'winning_ticket_number', 'won_at'];

    public $timestamps = true;
}
