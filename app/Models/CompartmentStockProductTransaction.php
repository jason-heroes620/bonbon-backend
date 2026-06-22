<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompartmentStockProductTransaction extends Model
{
    use HasUuids;

    protected $table = 'compartment_stock_product_transactions';
    protected $primaryKey = 'compartment_stock_product_transaction_id';
    protected $keyType = 'uuid';
    public $incrementing = false;
    protected $fillable = [
        'transaction_quantity',
        'description',
    ];
}
