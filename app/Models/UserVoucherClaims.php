<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVoucherClaims extends Model
{
    //
    protected $table = 'user_voucher_claims';
    protected $primaryKey = 'user_voucher_claim_id';
    protected $fillable = ['user_voucher_id', 'claimed_at'];

    public $timestamps = true;

    protected $hidden = ['created_at', 'updated_at'];
}
