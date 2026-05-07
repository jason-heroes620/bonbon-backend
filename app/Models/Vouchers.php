<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'voucher_value',
        'what_you_get',
        'voucher_code',
        'voucher_discount',
        'voucher_type',
        'voucher_start_date',
        'voucher_expiry_date',
        'voucher_limit',
        'voucher_claim_per_user',
        'voucher_claim_period',
        'voucher_claim_per_period',
        'voucher_image_path',
        'voucher_status',
        'is_unlimited',
        'tnc',
        'how_to_use',
        'voucher_claim_points',
    ];

    protected $casts = [
        'voucher_status' => 'boolean',
        'is_unlimited' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id', 'vendor_id');
    }

    public function voucher_images()
    {
        return $this->hasMany(VoucherImages::class, 'voucher_id', 'voucher_id');
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(
            Memberships::class,
            'voucher_memberships',
            'voucher_id',
            'membership_id'
        );
    }
}
