<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    //
    protected $table = 'orders';
    protected $primaryKey = 'order_id';
    protected $fillable = [
        'order_id',
        'user_id',
        'order_no',
        'order_date',
        'total_price',
        'total_tax',
        'total_discount',
        'total_payment',
        'shipping_method',
        'shipping_address',
        'billing_address',
        'discount_code',
        'order_status',
    ];

    public $timestamps = true;
}
