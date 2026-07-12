<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistration extends Model
{
    use HasUuids;

    protected $table = 'event_registrations';
    protected $primaryKey = 'event_registration_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'user_id',
        'cart_item_id',
        'order_id',
        'payment_id',
        'registration_status',
        'seat_hold_expires_at',
        'membership_type_at_registration',
        'price_before_discount',
        "quantity",
        'discount_amount',
        'price_paid',
        'joined_at',
        'confirmed_at',
        'checked_in_at',
        'checked_in_by_user_id',
        'check_in_source',
        'expired_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'seat_hold_expires_at' => 'datetime',
        'joined_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'expired_at' => 'datetime',
        'price_before_discount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'price_paid' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Events::class, 'event_id', 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class, 'event_registration_id', 'event_registration_id');
    }
}
