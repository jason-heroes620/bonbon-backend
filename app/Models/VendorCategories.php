<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorCategories extends Model
{
    use HasFactory;

    protected $table = 'vendor_categories';
    protected $primaryKey = 'vendor_category_id';

    protected $fillable = [
        'vendor_id',
        'category_id',
    ];
}
