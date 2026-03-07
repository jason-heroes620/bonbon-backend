<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Vouchers extends Model
{
    use HasUuids;

    protected $table = 'vouchers';
    protected $primaryKey = 'voucher_id';
    protected $fillable = [
        'voucher_id',
        'vendor_id',
        'voucher_name',
        'voucher_short_description',
        'voucher_description',
        'duration',
        'what_you_get',
        'voucher_code',
        'voucher_discount',
        'voucher_type',
        'voucher_start_date',
        'voucher_expiry_date',
        'voucher_limit',
        'voucher_claim_per_user',
        'voucher_image_path',
        'voucher_status',
    ];

    protected $casts = [
        'voucher_status' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id', 'vendor_id');
    }
}
