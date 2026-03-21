<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserVouchers extends Model
{
    use HasUuids;

    protected $table = 'user_vouchers';
    protected $primaryKey = 'user_voucher_id';
    protected $fillable = [
        'user_id',
        'voucher_id',
        'is_valid',
    ];

    protected $casts = [
        'is_used' => 'boolean',
    ];
}
