<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricingTier extends Model
{
    use HasUuids;

    protected $table = 'product_pricing_tiers';
    protected $primaryKey = 'product_pricing_tier_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'pricing_mode',
        'min_qty',
        'unit_price',
        'discount_percent',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_qty' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'product_id');
    }
}

