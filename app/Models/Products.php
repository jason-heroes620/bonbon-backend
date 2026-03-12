<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Products extends Model
{
    use HasUuids;

    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = [
        'product_code',
        'product_name',
        'product_code',
        'product_sku',
        'product_description',
        'stock_quantity',
        'product_weight',
        'product_dimensions',
        'is_featured',
        'is_visible',
        'is_taxable',
        'tax_rate_id',
        'retail_price',
        'sale_price',
        'is_active',
        'is_unlimited',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'is_unlimited' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Categories::class,
            'product_categories',
            'product_id',
            'category_id',
        );
    }
}
