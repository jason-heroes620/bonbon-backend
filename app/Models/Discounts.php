<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Discounts extends Model
{
    use HasUuids;

    protected $table = 'discounts';
    protected $primaryKey = 'discount_id';

    protected $fillable = [
        'discount_code',
        'user_id',
        'discount_name',
        'discount_description',
        'discount_type',
        'discount_amount',
        'discount_start_date',
        'discount_end_date',
        'is_active',
        'applies_to',
        'discount_usage_limit',
        'is_unlimited',
    ];
    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
        'is_unlimited' => 'boolean',
    ];
}
