<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Referrals extends Model
{
    use HasUuids;

    protected $table = 'referrals';
    protected $primaryKey = 'referral_id';
    protected $fillable = [
        'user_id',
        'referee_id',
        'referral_code',
        'referral_date',
        'cycle',
        'referral_status',
        'qualifying_order_no',
        'qualified_at',
    ];

    public $timestamps = true;
}
