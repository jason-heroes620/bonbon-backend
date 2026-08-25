<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;

    protected $table = 'carts';
    protected $primaryKey = 'cart_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cart_status',
        'currency_code',
        'fulfillment_method',
        'fulfillment_vendor_location_id',
        'shipping_address_id',
        'shipping_address_json',
        'shipping_provider',
        'shipping_service_code',
        'shipping_service_name',
        'shipping_fee',
        'shipping_quote_payload',
        'expires_at',
        'applied_user_voucher_id',
        'applied_voucher_id',
        'voucher_auto_apply_disabled',
    ];

    protected $casts = [
        'fulfillment_vendor_location_id' => 'integer',
        'shipping_address_json' => 'array',
        'shipping_fee' => 'decimal:2',
        'shipping_quote_payload' => 'array',
        'expires_at' => 'datetime',
        'voucher_auto_apply_disabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'cart_id');
    }
}
