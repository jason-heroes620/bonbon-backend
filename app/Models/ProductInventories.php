<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventories extends Model
{
    use HasUuids;

    protected $table = 'product_inventories';
    protected $primaryKey = 'product_inventory_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'vendor_location_id',
        'quantity',
        'safety_stock',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'safety_stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id', 'product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(VendorLocation::class, 'vendor_location_id', 'id');
    }
}
