<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDiscounts extends Model
{
    use HasUuids;

    protected $table = 'product_discounts';
    protected $primaryKey = 'product_discount_id';
    protected $fillable = [
        'product_id',
        'discount_type',
        'discount_amount',
        'discount_start_date',
        'discount_end_date',
        'is_active',
    ];
    public $timestamps = true;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'product_id');
    }
}
