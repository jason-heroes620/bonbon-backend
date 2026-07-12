<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItems extends Model
{
    use HasUuids;
    protected $table = 'order_items';
    protected $primaryKey = 'order_item_id';
    protected $fillable = [
        'order_id',
        'product_id',
        'line_type',
        'source_id',
        'line_name',
        'line_description',
        'quantity',
        'uom',
        'unit_price',
        'tax',
        'discount',
        'total_price',
    ];
    public $timestamps = true;

    protected $casts = [
        'unit_price' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id', 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'product_id');
    }
}
