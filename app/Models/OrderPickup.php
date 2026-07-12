<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPickup extends Model
{
    use HasUuids;

    protected $table = 'order_pickups';
    protected $primaryKey = 'order_pickup_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'vendor_id',
        'vendor_location_id',
        'fulfillment_method',
        'pickup_status',
        'pickup_code',
        'pickup_payload_json',
        'pickup_signature_hash',
        'qr_issued_at',
        'qr_expires_at',
        'scanned_at',
        'scanned_by_user_id',
        'picked_up_at',
        'picked_up_by_user_id',
    ];

    protected $casts = [
        'pickup_payload_json' => 'array',
        'qr_issued_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'scanned_at' => 'datetime',
        'picked_up_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderPickupItem::class, 'order_pickup_id', 'order_pickup_id');
    }
}
