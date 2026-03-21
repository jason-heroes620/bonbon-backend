<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherImages extends Model
{
    //
    protected $table = 'voucher_images';
    protected $primaryKey = 'voucher_image_id';

    protected $fillable = [
        'voucher_id',
        'voucher_image_path',
    ];
}
