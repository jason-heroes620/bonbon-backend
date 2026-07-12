<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetails extends Model
{
    protected $table = 'user_details';
    protected $primaryKey = 'user_detail_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'user_id',
        'birth_year',
    ];
}
