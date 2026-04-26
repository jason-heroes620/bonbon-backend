<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherMemberships extends Model
{
    protected $table = 'voucher_memberships';
    protected $primaryKey = 'voucher_membership_id';
    protected $fillable = [
        'voucher_id',
        'membership_id',
    ];
}
