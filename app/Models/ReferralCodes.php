<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReferralCodes extends Model
{
    use HasUuids;

    protected $table = 'referral_codes';
    protected $primaryKey = 'referral_code_id';

    protected $fillable = [
        'user_id',
        'campaign_name',
        'referral_code',
        'code_effective_date',
        'code_expiry_date',
        'usage_count',
        'max_usage',
        'is_active',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
        'code_effective_date' => 'date',
        'code_expiry_date' => 'date',
    ];
}
