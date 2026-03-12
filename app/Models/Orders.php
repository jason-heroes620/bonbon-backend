<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasUuids;

    protected $table = 'orders';
    protected $primaryKey = 'order_id';
    protected $fillable = [
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
        'wallet_credit_used',
        'order_status',
    ];

    public $timestamps = true;

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'order_id');
    }
}
