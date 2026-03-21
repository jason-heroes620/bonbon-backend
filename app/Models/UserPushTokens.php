<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserPushTokens extends Model
{
    use HasUuids;

    protected $table = 'user_push_tokens';
    protected $primaryKey = 'user_push_token_id';

    protected $fillable = [
        'user_id',
        'expo_push_token',
        'device_name',
        'platform',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public $timestamps = true;
}

