<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderPickupItem extends Model
{
    use HasUuids;

    protected $table = 'order_pickup_items';
    protected $primaryKey = 'order_pickup_item_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'order_pickup_id',
        'order_item_id',
        'product_id',
        'compartment_stock_id',
        'compartment_stock_product_id',
        'rack_id',
        'compartment_id',
        'ordered_quantity',
        'picked_up_quantity',
        'product_name',
        'vendor_name',
        'vendor_location_name',
        'rack_name',
        'compartment_name',
    ];
}
