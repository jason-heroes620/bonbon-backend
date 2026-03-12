<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDiscounts extends Model
{
    protected $table = 'user_discounts';
    protected $primaryKey = 'user_discount_id';

    protected $fillable = [
        'user_id',
        'discount_code_id',
    ];
}
