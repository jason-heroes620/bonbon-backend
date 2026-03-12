<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    //
    protected $table = 'order_items';
    protected $primaryKey = 'order_item_id';
    protected $fillable = [
        'order_item_id',
        'order_id',
        'product_id',
        'quantity',
        'uom',
        'unit_price',
        'tax',
        'discount',
        'total_price',
    ];
    public $timestamps = true;
}
