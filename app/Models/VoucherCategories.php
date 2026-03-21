<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherCategories extends Model
{
    protected $table = 'voucher_categories';
    protected $primaryKey = 'voucher_category_id';

    protected $fillable = [
        'voucher_id',
        'category_id',
    ];

    public $timestamps = false;
}
