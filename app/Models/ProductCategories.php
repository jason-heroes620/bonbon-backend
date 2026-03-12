<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategories extends Model
{
    //
    protected $table = 'product_categories';
    protected $primaryKey = 'product_category_id';
    protected $fillable = [
        'product_id',
        'category_id',
    ];

    public $timestamps = true;
}
