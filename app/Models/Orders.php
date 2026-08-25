<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasUuids;

    protected $table = 'orders';
    protected $primaryKey = 'order_id';
    protected $fillable = [
        'user_id',
        'order_no',
        'order_date',
        'order_description',
        'total_price',
        'total_charges',
        'total_discount',
        'total_payment',
        'shipping_method',
        'fulfillment_vendor_location_id',
        'shipping_address',
        'shipping_address_id',
        'shipping_address_json',
        'shipping_provider',
        'shipping_service_code',
        'shipping_service_name',
        'shipping_quote_payload',
        'delivery_order_id',
        'delivery_order_no',
        'delivery_order_tracking_no',
        'delivery_tracking_no',
        'billing_address',
        'discount_code',
        'applied_user_voucher_id',
        'applied_voucher_id',
        'applied_voucher_discount',
        'voucher_redeemed_at',
        'wallet_credit_used',
        'order_status',
        'delivery_status',
        'delivery_received_at',
    ];

    public $timestamps = true;

    protected $appends = [
        'total_charges',
    ];

    protected $casts = [
        'fulfillment_vendor_location_id' => 'integer',
        'shipping_address_json' => 'array',
        'shipping_quote_payload' => 'array',
        'applied_voucher_discount' => 'decimal:2',
        'voucher_redeemed_at' => 'datetime',
    ];

    public function getTotalChargesAttribute()
    {
        return $this->attributes['total_charges'] ?? null;
    }

    public function setTotalChargesAttribute($value): void
    {
        $this->attributes['total_charges'] = $value;
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'order_id');
    }
}
