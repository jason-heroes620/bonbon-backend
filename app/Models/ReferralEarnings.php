<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReferralEarnings extends Model
{
    use HasUuids;

    protected $table = 'referral_earnings';
    protected $primaryKey = 'referral_earning_id';

    protected $fillable = [
        'user_id',
        'referral_id',
        'month',
        'year',
        'amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'amount' => 'integer',
    ];
}

