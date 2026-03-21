<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserReferralGifts extends Model
{
    use HasUuids;

    protected $table = 'user_referral_gifts';
    protected $primaryKey = 'user_referral_gift_id';

    protected $fillable = [
        'user_id',
        'earned_at',
        'claimed_at',
    ];

    public $timestamps = true;
}

