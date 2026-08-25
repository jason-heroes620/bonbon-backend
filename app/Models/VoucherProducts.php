<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VoucherProducts extends Model
{
    use HasUuids;

    protected $table = 'voucher_products';
    protected $primaryKey = 'voucher_product_id';
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'voucher_id',
    ];

    public $timestamps = true;
}
