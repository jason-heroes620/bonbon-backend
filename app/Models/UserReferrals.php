<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserReferrals extends Model
{
    use HasUuids;
    protected $table = 'user_referrals';
    protected $primaryKey = 'user_referral_id';

    protected $fillable = [
        'user_id',
        'referral_user_id',
        'referral_code',
        'referral_date',
        'cycle',
        'referral_status',
        'qualifying_order_id',
        'qualfied_at',
    ];

    public $timestamps = true;
}
