<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUuids;

    protected $table = 'cart_items';
    protected $primaryKey = 'cart_item_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'cart_id',
        'line_type',
        'source_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'total_price',
        'metadata_json',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata_json' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'cart_id');
    }
}

