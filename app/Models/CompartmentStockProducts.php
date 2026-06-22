<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompartmentStockProducts extends Model
{
    use HasUuids;

    protected $table = 'compartment_stock_products';
    protected $primaryKey = 'compartment_stock_product_id';
    protected $keyType = 'uuid';
    public $incrementing = false;
    protected $fillable = [
        'compartment_stock_id',
        'product_id',
        'expiry_date',
        'quantity',
    ];
}
