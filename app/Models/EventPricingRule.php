<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPricingRule extends Model
{
    use HasUuids;

    protected $table = 'event_pricing_rules';
    protected $primaryKey = 'event_pricing_rule_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'membership_type_id',
        'pricing_rule_type',
        'pricing_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'pricing_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Events::class, 'event_id', 'event_id');
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipTypes::class, 'membership_type_id', 'membership_type_id');
    }
}

