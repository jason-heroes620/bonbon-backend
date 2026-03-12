<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memberships extends Model
{
    use HasUuids;

    protected $table = 'memberships';
    protected $primaryKey = 'membership_id';
    protected $fillable = [
        'membership_code',
        'membership_name',
        'membership_description',
        'membership_type_id',
        'membership_type',
        'membership_price',
        'duration',
        'duration_unit',
        'membership_start_date',
        'membership_end_date',
        'is_active',
        'sort_order',
        'best_value',
    ];
    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
        'best_value' => 'boolean',
    ];

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipTypes::class, 'membership_type_id', 'membership_type_id');
    }
}
