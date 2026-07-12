<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompartmentStockQrSession extends Model
{
    use HasUuids;

    protected $table = 'compartment_stock_qr_sessions';
    protected $primaryKey = 'compartment_stock_qr_session_id';
    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'compartment_stock_id',
        'compartment_stock_product_id',
        'vendor_id',
        'rack_owner_vendor_id',
        'generated_by_user_id',
        'nonce',
        'payload_json',
        'signature_hash',
        'issued_at',
        'expires_at',
        'scanned_at',
        'scanned_by_user_id',
        'consumed_at',
        'consumed_by_user_id',
        'status',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'scanned_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
