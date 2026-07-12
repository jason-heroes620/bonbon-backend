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
        'compartment_stock_qr_session_id',
        'compartment_stock_id',
        'compartment_stock_product_id',
        'vendor_id',
        'rack_owner_vendor_id',
        'generated_by_user_id',
        'received_by_user_id',
        'transaction_type',
        'transaction_status',
        'prepared_quantity',
        'received_quantity',
        'quantity_delta',
        'actor_user_id',
        'actor_vendor_id',
        'event_source',
        'event_source_id',
        'vendor_location_id',
        'rack_id',
        'compartment_id',
        'product_id',
        'description',
        'confirmed_at',
    ];
}
