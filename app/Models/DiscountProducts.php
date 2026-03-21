<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountProducts extends Model
{
    protected $table = 'discount_products';
    protected $primaryKey = 'discount_product_id';

    protected $fillable = [
        'discount_id',
        'product_id',
    ];
    public $timestamps = true;
}
